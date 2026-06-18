-- ============================================================
-- database/management_mail.sql
-- Creates tables for Management Mail module:
--   mail_templates  – reusable email templates with variables
--   mail_log        – full history of every sent email
-- Run once in phpMyAdmin or MySQL CLI
-- ============================================================

USE scholarship_system;

-- ── 1. Mail Templates ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS mail_templates (
    id          INT(11)      AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL COMMENT 'Admin-facing template name',
    template_type ENUM(
        'award_notification',
        'rejection_notification',
        'document_request',
        'interview_invitation',
        'custom'
    ) NOT NULL DEFAULT 'custom',
    subject     VARCHAR(255) NOT NULL,
    body_html   TEXT         NOT NULL COMMENT 'HTML body — supports {{variables}}',
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_by  INT(11)      DEFAULT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── 2. Mail Log (email history) ───────────────────────────────
CREATE TABLE IF NOT EXISTS mail_log (
    id            INT(11)      AUTO_INCREMENT PRIMARY KEY,
    template_id   INT(11)      DEFAULT NULL COMMENT 'FK → mail_templates.id (NULL = ad-hoc)',
    recipient_id  INT(11)      NOT NULL COMMENT 'FK → users.id (student)',
    application_id INT(11)     DEFAULT NULL COMMENT 'FK → applications.id',
    program_id    INT(11)      DEFAULT NULL,
    subject       VARCHAR(255) NOT NULL,
    body_html     TEXT         NOT NULL COMMENT 'Rendered body (variables replaced)',
    status        ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    error_message TEXT         DEFAULT NULL,
    has_attachment TINYINT(1)  NOT NULL DEFAULT 0,
    attachment_type ENUM('certificate','document','none') DEFAULT 'none',
    sent_by       INT(11)      DEFAULT NULL COMMENT 'Admin user who sent it',
    sent_at       DATETIME     DEFAULT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (template_id)    REFERENCES mail_templates(id)        ON DELETE SET NULL,
    FOREIGN KEY (recipient_id)   REFERENCES users(id)                 ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES applications(id)          ON DELETE SET NULL,
    FOREIGN KEY (program_id)     REFERENCES scholarship_programs(id)  ON DELETE SET NULL,
    FOREIGN KEY (sent_by)        REFERENCES users(id)                 ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_ml_recipient  ON mail_log (recipient_id);
CREATE INDEX IF NOT EXISTS idx_ml_status     ON mail_log (status);
CREATE INDEX IF NOT EXISTS idx_ml_program    ON mail_log (program_id);
CREATE INDEX IF NOT EXISTS idx_ml_sent_at    ON mail_log (sent_at);

-- ── 3. Seed default templates ─────────────────────────────────
INSERT IGNORE INTO mail_templates (id, name, template_type, subject, body_html, is_active) VALUES

(1, 'Award Notification', 'award_notification',
 '🎉 Scholarship Award — {{program_name}}',
'<p>Dear <strong>{{student_name}}</strong>,</p>
<p>We are delighted to inform you that your application for the <strong>{{program_name}}</strong> scholarship has been <strong>approved</strong>.</p>
<ul>
  <li><strong>Rank:</strong> #{{rank}}</li>
  <li><strong>Final Score:</strong> {{score}}</li>
  <li><strong>Student ID:</strong> {{student_id}}</li>
</ul>
<p>Please log in to the scholarship portal to view your award certificate and disbursement details.</p>
<p>Congratulations and best wishes for your academic journey!</p>
<p>— Scholarship Management Office</p>', 1),

(2, 'Rejection Notification', 'rejection_notification',
 'Scholarship Application Result — {{program_name}}',
'<p>Dear <strong>{{student_name}}</strong>,</p>
<p>Thank you for applying to the <strong>{{program_name}}</strong> scholarship program.</p>
<p>After careful evaluation, we regret to inform you that your application was not selected in this round.</p>
<ul>
  <li><strong>Rank:</strong> #{{rank}}</li>
  <li><strong>Final Score:</strong> {{score}}</li>
</ul>
<p>We encourage you to apply again in the next scholarship cycle. Please don''t hesitate to contact us if you have any questions.</p>
<p>— Scholarship Management Office</p>', 1),

(3, 'Document Request', 'document_request',
 'Additional Documents Required — {{program_name}}',
'<p>Dear <strong>{{student_name}}</strong>,</p>
<p>We are reviewing your application for the <strong>{{program_name}}</strong> scholarship (Student ID: {{student_id}}).</p>
<p>To proceed with the evaluation, we require the following additional documents:</p>
<ul>
  <li>[List required documents here]</li>
</ul>
<p>Please upload the requested documents through the scholarship portal within <strong>7 business days</strong>.</p>
<p>— Scholarship Management Office</p>', 1),

(4, 'Interview Invitation', 'interview_invitation',
 'Interview Invitation — {{program_name}} Scholarship',
'<p>Dear <strong>{{student_name}}</strong>,</p>
<p>Congratulations on reaching the interview stage of the <strong>{{program_name}}</strong> scholarship selection process!</p>
<p>You are invited to attend an interview on:</p>
<ul>
  <li><strong>Date:</strong> [Interview Date]</li>
  <li><strong>Time:</strong> [Interview Time]</li>
  <li><strong>Location:</strong> [Interview Location / Online Link]</li>
</ul>
<p>Please confirm your attendance by replying to this email before [Confirmation Deadline].</p>
<p>— Scholarship Management Office</p>', 1);
