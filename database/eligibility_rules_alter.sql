-- ============================================================
-- database/eligibility_rules_alter.sql
-- Adds: is_active, updated_by, updated_at columns
-- Adds: eligibility_rule_requests table for reviewer proposals
-- Run once in phpMyAdmin or MySQL CLI
-- ============================================================

USE scholarship_system;

-- ── 1. Extend eligibility_rules ─────────────────────────────
ALTER TABLE eligibility_rules
    ADD COLUMN is_active   TINYINT(1)  NOT NULL DEFAULT 1
        COMMENT '1 = Active, 0 = Inactive'              AFTER value,
    ADD COLUMN updated_by  INT(11)     DEFAULT NULL
        COMMENT 'FK → users.id (admin who last changed)' AFTER is_active,
    ADD COLUMN updated_at  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP                       AFTER updated_by,
    ADD CONSTRAINT fk_er_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL;

-- ── 2. Reviewer rule-change requests ────────────────────────
CREATE TABLE IF NOT EXISTS eligibility_rule_requests (
    id              INT(11)      AUTO_INCREMENT PRIMARY KEY,

    -- Who proposed the change
    reviewer_id     INT(11)      NOT NULL,

    -- Which rule they want to change (NULL = request to add a new rule)
    rule_id         INT(11)      DEFAULT NULL,

    -- Which program the proposed rule belongs to
    program_id      INT(11)      NOT NULL,

    -- Current rule snapshot (JSON) for display
    current_data    JSON         DEFAULT NULL
        COMMENT 'Snapshot of the rule at request time',

    -- Proposed new values
    proposed_rule_type VARCHAR(100) DEFAULT NULL,
    proposed_operator  VARCHAR(10)  DEFAULT NULL,
    proposed_value     VARCHAR(100) DEFAULT NULL,

    -- Reviewer justification
    reason          TEXT         DEFAULT NULL,

    -- Workflow
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',

    -- Admin response
    admin_id        INT(11)      DEFAULT NULL,
    admin_note      TEXT         DEFAULT NULL,
    responded_at    DATETIME     DEFAULT NULL,

    -- Timestamps
    requested_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (reviewer_id) REFERENCES users(id)               ON DELETE CASCADE,
    FOREIGN KEY (rule_id)     REFERENCES eligibility_rules(id)   ON DELETE SET NULL,
    FOREIGN KEY (program_id)  REFERENCES scholarship_programs(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id)    REFERENCES users(id)               ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_err_status   ON eligibility_rule_requests (status);
CREATE INDEX IF NOT EXISTS idx_err_program  ON eligibility_rule_requests (program_id);
CREATE INDEX IF NOT EXISTS idx_err_reviewer ON eligibility_rule_requests (reviewer_id);
