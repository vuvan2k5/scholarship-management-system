<?php
// ============================================================
// includes/notifications.php
// Centralized helper for sending automatic system notifications
// ============================================================

if (!function_exists('sendNotification')) {
    /**
     * Insert a notification into the notifications table.
     *
     * @param PDO    $pdo
     * @param int    $userId   Target user (student)
     * @param string $title
     * @param string $message
     * @param string $type     'info' | 'success' | 'warning' | 'error'
     */
    function sendNotification(PDO $pdo, int $userId, string $title, string $message, string $type = 'info'): void {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, is_read)
            VALUES (?, ?, ?, ?, 0)
        ");
        $stmt->execute([$userId, $title, $message, $type]);
    }
}

if (!function_exists('notifyApplicationApproved')) {
    /**
     * Send "Application Approved" notification to the student.
     */
    function notifyApplicationApproved(PDO $pdo, int $applicationId): void {
        $stmt = $pdo->prepare("
            SELECT a.student_id, u.full_name, sp.name AS program_name
            FROM applications a
            JOIN users u ON a.student_id = u.id
            JOIN scholarship_programs sp ON a.program_id = sp.id
            WHERE a.id = ?
        ");
        $stmt->execute([$applicationId]);
        $app = $stmt->fetch();
        if (!$app) return;

        sendNotification(
            $pdo,
            (int)$app['student_id'],
            '🎉 Application Approved',
            "Congratulations {$app['full_name']}! Your scholarship application for \"{$app['program_name']}\" has been APPROVED. Please check for further instructions.",
            'success'
        );
    }
}

if (!function_exists('notifyApplicationRejected')) {
    /**
     * Send "Application Rejected" notification to the student.
     */
    function notifyApplicationRejected(PDO $pdo, int $applicationId): void {
        $stmt = $pdo->prepare("
            SELECT a.student_id, u.full_name, sp.name AS program_name
            FROM applications a
            JOIN users u ON a.student_id = u.id
            JOIN scholarship_programs sp ON a.program_id = sp.id
            WHERE a.id = ?
        ");
        $stmt->execute([$applicationId]);
        $app = $stmt->fetch();
        if (!$app) return;

        sendNotification(
            $pdo,
            (int)$app['student_id'],
            '❌ Application Rejected',
            "Dear {$app['full_name']}, we regret to inform you that your application for \"{$app['program_name']}\" has been rejected. Please contact the scholarship committee for more information.",
            'error'
        );
    }
}

if (!function_exists('notifyScholarshipPaid')) {
    /**
     * Send "Scholarship Paid" notification to the student.
     */
    function notifyScholarshipPaid(PDO $pdo, int $disbursementId): void {
        $stmt = $pdo->prepare("
            SELECT d.amount, a.student_id, u.full_name, sp.name AS program_name
            FROM disbursements d
            JOIN applications a ON d.application_id = a.id
            JOIN users u ON a.student_id = u.id
            JOIN scholarship_programs sp ON a.program_id = sp.id
            WHERE d.id = ?
        ");
        $stmt->execute([$disbursementId]);
        $row = $stmt->fetch();
        if (!$row) return;

        $amount = number_format((float)$row['amount'], 0, ',', '.');

        sendNotification(
            $pdo,
            (int)$row['student_id'],
            '💰 Scholarship Payment Received',
            "Dear {$row['full_name']}, your scholarship disbursement of {$amount} VND for \"{$row['program_name']}\" has been successfully PAID. Please check your registered account.",
            'success'
        );
    }
}
