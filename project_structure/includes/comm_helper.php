<?php
// ============================================================
// includes/comm_helper.php
// Shared helper functions for the Communication Center.
// NO SMTP — all communications are internal system messages.
// ============================================================

if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/db.php';

/**
 * Auto-create tables if they don't exist yet.
 */
function ensureCommTables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id             INT(11)      AUTO_INCREMENT PRIMARY KEY,
            sender_id      INT(11)      NOT NULL,
            recipient_id   INT(11)      DEFAULT NULL,
            subject        VARCHAR(255) NOT NULL,
            body           TEXT         NOT NULL,
            message_type   ENUM('direct','broadcast','system_alert','reply') NOT NULL DEFAULT 'direct',
            parent_id      INT(11)      DEFAULT NULL,
            is_read        TINYINT(1)   NOT NULL DEFAULT 0,
            read_at        DATETIME     DEFAULT NULL,
            has_attachment TINYINT(1)   NOT NULL DEFAULT 0,
            attachment_note VARCHAR(255) DEFAULT NULL,
            created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id)    REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS message_recipients (
            id           INT(11)    AUTO_INCREMENT PRIMARY KEY,
            message_id   INT(11)    NOT NULL,
            recipient_id INT(11)    NOT NULL,
            is_read      TINYINT(1) NOT NULL DEFAULT 0,
            read_at      DATETIME   DEFAULT NULL,
            FOREIGN KEY (message_id)   REFERENCES messages(id) ON DELETE CASCADE,
            FOREIGN KEY (recipient_id) REFERENCES users(id)    ON DELETE CASCADE
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comm_templates (
            id            INT(11)      AUTO_INCREMENT PRIMARY KEY,
            name          VARCHAR(150) NOT NULL,
            template_type ENUM('scholarship_awarded','scholarship_rejected','certificate_available','program_updated','eligibility_rules_updated','additional_documents_required','custom') NOT NULL DEFAULT 'custom',
            subject       VARCHAR(255) NOT NULL,
            body          TEXT         NOT NULL,
            is_active     TINYINT(1)   NOT NULL DEFAULT 1,
            created_by    INT(11)      DEFAULT NULL,
            created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
}

/**
 * Seed default templates if table is empty.
 */
function seedCommTemplates(PDO $pdo): void {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM comm_templates")->fetchColumn();
    if ($count > 0) return;

    $templates = [
        ['scholarship_awarded',             '🎉 Scholarship Awarded — {{program_name}}',               "Dear {{student_name}},\n\nYour application for the {{program_name}} scholarship has been APPROVED.\n\nRank: #{{ranking}} · Score: {{score}}\n\nPlease log in to view your award certificate.\n\n— Scholarship Management Office"],
        ['scholarship_rejected',            'Scholarship Result — {{program_name}}',                   "Dear {{student_name}},\n\nThank you for applying to {{program_name}}. After careful review, your application was not selected this round.\n\nRank: #{{ranking}} · Score: {{score}}\n\nWe encourage you to apply again in the next cycle.\n\n— Scholarship Management Office"],
        ['certificate_available',           'Your Certificate is Ready — {{program_name}}',             "Dear {{student_name}},\n\nYour award certificate for {{program_name}} is now available.\n\nLog in to Student Portal → My Results to download it.\n\nCongratulations!\n\n— Scholarship Management Office"],
        ['program_updated',                 'Scholarship Program Updated — {{program_name}}',           "Dear {{student_name}},\n\nThe scholarship program {{program_name}} has been updated. Please review the latest details in the portal.\n\n— Scholarship Management Office"],
        ['eligibility_rules_updated',       'Eligibility Rules Updated — {{program_name}}',             "Dear Reviewer,\n\nEligibility rules for {{program_name}} have been updated. Please review them before proceeding with evaluations.\n\n— Scholarship Management Office"],
        ['additional_documents_required',   'Action Required: Documents — {{program_name}}',            "Dear {{student_name}},\n\nAdditional documents are needed for your {{program_name}} application. Please upload them within 7 business days.\n\n— Scholarship Management Office"],
    ];

    $ins = $pdo->prepare("INSERT INTO comm_templates (template_type, subject, body, name) VALUES (?,?,?,?)");
    $typeLabels = [
        'scholarship_awarded'            => 'Scholarship Awarded',
        'scholarship_rejected'           => 'Scholarship Rejected',
        'certificate_available'          => 'Certificate Available',
        'program_updated'                => 'Program Updated',
        'eligibility_rules_updated'      => 'Eligibility Rules Updated',
        'additional_documents_required'  => 'Additional Documents Required',
    ];
    foreach ($templates as [$type, $subj, $body]) {
        $ins->execute([$type, $subj, $body, $typeLabels[$type] ?? $type]);
    }
}

/**
 * Apply {{variable}} substitution to subject and body.
 */
function renderCommTemplate(string $text, array $vars): string {
    $keys = array_map(fn($k) => '{{' . $k . '}}', array_keys($vars));
    return str_replace($keys, array_values($vars), $text);
}

/**
 * Send a direct internal message from $senderId to $recipientId.
 * Also creates a `notifications` row so the recipient sees it everywhere.
 */
function sendInternalMessage(
    PDO     $pdo,
    int     $senderId,
    int     $recipientId,
    string  $subject,
    string  $body,
    string  $type      = 'direct',
    ?int    $parentId  = null,
    bool    $hasAttach = false,
    ?string $attachNote = null
): int {
    $pdo->prepare("
        INSERT INTO messages
            (sender_id, recipient_id, subject, body, message_type, parent_id, has_attachment, attachment_note)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([$senderId, $recipientId, $subject, $body, $type, $parentId, $hasAttach ? 1 : 0, $attachNote]);

    $msgId = (int)$pdo->lastInsertId();

    // Mirror as notification
    try {
        $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, is_read)
            VALUES (?, ?, ?, 'info', 0)
        ")->execute([$recipientId, $subject, mb_substr(strip_tags($body), 0, 200)]);
    } catch (Exception $e) {}

    return $msgId;
}

/**
 * Broadcast a message to multiple recipients (role-based or award-based).
 * Returns count of recipients reached.
 */
function broadcastInternalMessage(
    PDO    $pdo,
    int    $senderId,
    array  $recipientIds,
    string $subject,
    string $body,
    bool   $hasAttach  = false,
    ?string $attachNote = null
): int {
    if (empty($recipientIds)) return 0;

    // Create the broadcast parent message
    $pdo->prepare("
        INSERT INTO messages
            (sender_id, recipient_id, subject, body, message_type, has_attachment, attachment_note)
        VALUES (?, NULL, ?, ?, 'broadcast', ?, ?)
    ")->execute([$senderId, $subject, $body, $hasAttach ? 1 : 0, $attachNote]);
    $msgId = (int)$pdo->lastInsertId();

    $mrIns  = $pdo->prepare("INSERT INTO message_recipients (message_id, recipient_id) VALUES (?, ?)");
    $notIns = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, 'info', 0)");

    $count = 0;
    foreach (array_unique($recipientIds) as $rid) {
        $mrIns->execute([$msgId, $rid]);
        try { $notIns->execute([$rid, $subject, mb_substr(strip_tags($body), 0, 200)]); } catch(Exception $e) {}
        $count++;
    }

    return $count;
}

/**
 * Resolve a notifications row to the root message id of a conversation, if any.
 * Returns null for system-only alerts (ranking, eligibility, etc.).
 */
function resolveNotificationThreadRoot(PDO $pdo, int $userId, array $notification): ?int {
    $title     = trim((string)($notification['title'] ?? ''));
    $createdAt = (string)($notification['created_at'] ?? '');
    if ($title === '' || $createdAt === '') {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT id, parent_id
        FROM messages
        WHERE recipient_id = ?
          AND subject = ?
        ORDER BY ABS(TIMESTAMPDIFF(SECOND, created_at, ?))
        LIMIT 1
    ");
    $stmt->execute([$userId, $title, $createdAt]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return !empty($row['parent_id']) ? (int)$row['parent_id'] : (int)$row['id'];
    }

    $bStmt = $pdo->prepare("
        SELECT m.id, m.parent_id
        FROM message_recipients mr
        JOIN messages m ON m.id = mr.message_id
        WHERE mr.recipient_id = ?
          AND m.subject = ?
        ORDER BY ABS(TIMESTAMPDIFF(SECOND, m.created_at, ?))
        LIMIT 1
    ");
    $bStmt->execute([$userId, $title, $createdAt]);
    $bRow = $bStmt->fetch(PDO::FETCH_ASSOC);
    if ($bRow) {
        return !empty($bRow['parent_id']) ? (int)$bRow['parent_id'] : (int)$bRow['id'];
    }

    return null;
}

/**
 * Load a thread root message if the user is a participant.
 */
function loadThreadRootForUser(PDO $pdo, int $msgId, int $userId): ?array {
    $accessSql = "(m.sender_id = ? OR m.recipient_id = ?
        OR EXISTS (
            SELECT 1 FROM message_recipients mr
            WHERE mr.message_id = m.id AND mr.recipient_id = ?
        ))";

    $stmt = $pdo->prepare("
        SELECT m.*,
               s.full_name AS sender_name, s.role AS sender_role, s.email AS sender_email,
               r.full_name AS recipient_name, r.role AS recipient_role
        FROM messages m
        JOIN users s ON m.sender_id = s.id
        LEFT JOIN users r ON m.recipient_id = r.id
        WHERE m.id = ?
          AND {$accessSql}
    ");
    $stmt->execute([$msgId, $userId, $userId, $userId]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$msg) {
        return null;
    }

    if (!empty($msg['parent_id'])) {
        $rootStmt = $pdo->prepare("
            SELECT m.*,
                   s.full_name AS sender_name, s.role AS sender_role, s.email AS sender_email,
                   r.full_name AS recipient_name, r.role AS recipient_role
            FROM messages m
            JOIN users s ON m.sender_id = s.id
            LEFT JOIN users r ON m.recipient_id = r.id
            WHERE m.id = ?
              AND {$accessSql}
        ");
        $rootStmt->execute([(int)$msg['parent_id'], $userId, $userId, $userId]);
        $root = $rootStmt->fetch(PDO::FETCH_ASSOC);
        if ($root) {
            return $root;
        }
    }

    return $msg;
}

/**
 * Mark a message as read for the given user.
 */
function markMessageRead(PDO $pdo, int $msgId, int $userId): void {
    // Direct message
    $pdo->prepare("
        UPDATE messages
        SET is_read = 1, read_at = NOW()
        WHERE id = ? AND recipient_id = ? AND is_read = 0
    ")->execute([$msgId, $userId]);
    // Broadcast delivery row
    $pdo->prepare("
        UPDATE message_recipients
        SET is_read = 1, read_at = NOW()
        WHERE message_id = ? AND recipient_id = ? AND is_read = 0
    ")->execute([$msgId, $userId]);
}

/**
 * Get unread message count for a user (inbox only).
 */
function getUnreadMessageCount(PDO $pdo, int $userId): int {
    $direct = (int)$pdo->prepare("
        SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND is_read = 0 AND message_type != 'broadcast'
    ")->execute([$userId]) ? $pdo->query("SELECT COUNT(*) FROM messages WHERE recipient_id = $userId AND is_read = 0 AND message_type != 'broadcast'")->fetchColumn() : 0;

    $broadcast = (int)$pdo->prepare("
        SELECT COUNT(*) FROM message_recipients WHERE recipient_id = ? AND is_read = 0
    ")->execute([$userId]) ? $pdo->query("SELECT COUNT(*) FROM message_recipients WHERE recipient_id = $userId AND is_read = 0")->fetchColumn() : 0;

    return $direct + $broadcast;
}

/**
 * System-triggered notification helper.
 * Called by ranking publish, certificate generation, program updates, rule changes.
 */
function systemNotify(PDO $pdo, int $senderId, array $recipientIds, string $subject, string $body): void {
    foreach (array_unique($recipientIds) as $rid) {
        try {
            $pdo->prepare("
                INSERT INTO messages (sender_id, recipient_id, subject, body, message_type)
                VALUES (?, ?, ?, ?, 'system_alert')
            ")->execute([$senderId, $rid, $subject, $body]);

            $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read)
                VALUES (?, ?, ?, 'info', 0)
            ")->execute([$rid, $subject, mb_substr(strip_tags($body), 0, 200)]);
        } catch (Exception $e) {}
    }
}
