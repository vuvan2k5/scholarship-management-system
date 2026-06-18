-- ============================================================
-- database/communication_center.sql
-- Internal communication tables for Communication Center module.
-- NO SMTP / NO external email — everything stays internal.
-- ============================================================

USE scholarship_system;

-- ── 1. Messages (Inbox / Outbox thread store) ─────────────────
CREATE TABLE IF NOT EXISTS messages (
    id            INT(11)      AUTO_INCREMENT PRIMARY KEY,
    sender_id     INT(11)      NOT NULL,
    recipient_id  INT(11)      DEFAULT NULL  COMMENT 'NULL = broadcast',
    subject       VARCHAR(255) NOT NULL,
    body          TEXT         NOT NULL,
    message_type  ENUM('direct','broadcast','system_alert','reply') NOT NULL DEFAULT 'direct',
    parent_id     INT(11)      DEFAULT NULL  COMMENT 'FK → messages.id for threaded replies',
    is_read       TINYINT(1)   NOT NULL DEFAULT 0,
    read_at       DATETIME     DEFAULT NULL,
    has_attachment TINYINT(1)  NOT NULL DEFAULT 0,
    attachment_note VARCHAR(255) DEFAULT NULL COMMENT 'e.g. "Certificate PDF attached"',
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (sender_id)    REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id)    REFERENCES messages(id) ON DELETE SET NULL
);

-- ── 2. Broadcast targets (one-to-many message delivery) ───────
CREATE TABLE IF NOT EXISTS message_recipients (
    id           INT(11)   AUTO_INCREMENT PRIMARY KEY,
    message_id   INT(11)   NOT NULL,
    recipient_id INT(11)   NOT NULL,
    is_read      TINYINT(1) NOT NULL DEFAULT 0,
    read_at      DATETIME  DEFAULT NULL,

    FOREIGN KEY (message_id)   REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id)    ON DELETE CASCADE
);

-- ── 3. Communication templates (internal) ────────────────────
CREATE TABLE IF NOT EXISTS comm_templates (
    id            INT(11)       AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150)  NOT NULL,
    template_type ENUM(
        'scholarship_awarded',
        'scholarship_rejected',
        'certificate_available',
        'program_updated',
        'eligibility_rules_updated',
        'additional_documents_required',
        'custom'
    ) NOT NULL DEFAULT 'custom',
    subject       VARCHAR(255)  NOT NULL,
    body          TEXT          NOT NULL COMMENT 'Supports {{student_name}} {{program_name}} {{ranking}} {{score}}',
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    created_by    INT(11)       DEFAULT NULL,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── 4. Indexes ────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_msg_sender    ON messages (sender_id);
CREATE INDEX IF NOT EXISTS idx_msg_recipient ON messages (recipient_id);
CREATE INDEX IF NOT EXISTS idx_msg_parent    ON messages (parent_id);
CREATE INDEX IF NOT EXISTS idx_msg_type      ON messages (message_type);
CREATE INDEX IF NOT EXISTS idx_msg_created   ON messages (created_at);
CREATE INDEX IF NOT EXISTS idx_mr_msg        ON message_recipients (message_id);
CREATE INDEX IF NOT EXISTS idx_mr_recip      ON message_recipients (recipient_id);

-- ── 5. Seed default communication templates ───────────────────
INSERT IGNORE INTO comm_templates (id, name, template_type, subject, body) VALUES

(1, 'Scholarship Awarded', 'scholarship_awarded',
 '🎉 Congratulations — Scholarship Awarded: {{program_name}}',
 'Dear {{student_name}},

We are delighted to inform you that your application for the {{program_name}} scholarship has been APPROVED.

Your Details:
• Rank: #{{ranking}}
• Final Score: {{score}}

Please log in to the Student Portal to view your award certificate and disbursement information.

If you have any questions, please reply to this message.

— Scholarship Management Office'),

(2, 'Scholarship Rejected', 'scholarship_rejected',
 'Scholarship Application Result — {{program_name}}',
 'Dear {{student_name}},

Thank you for applying to the {{program_name}} scholarship program.

After careful evaluation by our review committee, we regret to inform you that your application was not selected in this round.

Your Details:
• Rank: #{{ranking}}
• Final Score: {{score}}

We encourage you to apply again in the next scholarship cycle. Please feel free to reply to this message if you have questions.

— Scholarship Management Office'),

(3, 'Certificate Available', 'certificate_available',
 'Your Scholarship Certificate is Ready — {{program_name}}',
 'Dear {{student_name}},

Your award certificate for the {{program_name}} scholarship is now available.

Please log in to the Student Portal → My Results to download your certificate.

Congratulations again on your achievement!

— Scholarship Management Office'),

(4, 'Program Updated', 'program_updated',
 'Scholarship Program Updated — {{program_name}}',
 'Dear {{student_name}},

Please be advised that the scholarship program {{program_name}} has been updated.

Please log in to the portal to review the latest program details, eligibility requirements, and application deadlines.

If you have any questions, please reply to this message.

— Scholarship Management Office'),

(5, 'Eligibility Rules Updated', 'eligibility_rules_updated',
 'Eligibility Rules Updated — {{program_name}}',
 'Dear Reviewer,

The eligibility rules for the {{program_name}} scholarship program have been updated.

Please review the updated rules in the system before proceeding with further evaluations.

— Scholarship Management Office'),

(6, 'Additional Documents Required', 'additional_documents_required',
 'Action Required: Additional Documents — {{program_name}}',
 'Dear {{student_name}},

To proceed with the evaluation of your {{program_name}} scholarship application, we require additional supporting documents.

Please log in to the Student Portal and upload the requested documents within 7 business days.

If you need clarification, please reply to this message.

— Scholarship Management Office');
