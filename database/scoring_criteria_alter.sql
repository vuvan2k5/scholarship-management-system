-- ============================================================
-- database/scoring_criteria_alter.sql
-- Adds: description, is_active, updated_by, updated_at columns
-- Adds: scoring_criteria_requests table for reviewer proposals
-- Run once in phpMyAdmin or MySQL CLI
-- ============================================================

USE scholarship_system;

-- ── 1. Extend scoring_criteria ───────────────────────────────
ALTER TABLE scoring_criteria
    ADD COLUMN description TEXT        DEFAULT NULL
        COMMENT 'Detailed description of what this criterion measures'  AFTER criterion_name,
    ADD COLUMN is_active   TINYINT(1)  NOT NULL DEFAULT 1
        COMMENT '1 = Active (used in scoring), 0 = Inactive'           AFTER max_score,
    ADD COLUMN updated_by  INT(11)     DEFAULT NULL
        COMMENT 'FK → users.id (admin who last changed)'               AFTER is_active,
    ADD COLUMN updated_at  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP                                     AFTER updated_by,
    ADD CONSTRAINT fk_sc_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL;

-- ── 2. Reviewer scoring change requests ─────────────────────
CREATE TABLE IF NOT EXISTS scoring_criteria_requests (
    id                    INT(11)       AUTO_INCREMENT PRIMARY KEY,

    -- Who proposed
    reviewer_id           INT(11)       NOT NULL,

    -- Which criterion (NULL = new criterion proposal)
    criterion_id          INT(11)       DEFAULT NULL,

    -- Which program
    program_id            INT(11)       NOT NULL,

    -- Snapshot of current values at request time
    current_criterion_name VARCHAR(100) DEFAULT NULL,
    current_weight         DECIMAL(5,2) DEFAULT NULL,
    current_max_score      DECIMAL(5,2) DEFAULT NULL,

    -- Proposed values
    proposed_criterion_name VARCHAR(100) DEFAULT NULL,
    proposed_weight         DECIMAL(5,2) DEFAULT NULL,
    proposed_max_score      DECIMAL(5,2) DEFAULT NULL,
    proposed_description    TEXT         DEFAULT NULL,

    -- Justification
    reason                TEXT          DEFAULT NULL,

    -- Workflow
    status                ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',

    -- Admin response
    admin_id              INT(11)       DEFAULT NULL,
    admin_note            TEXT          DEFAULT NULL,
    responded_at          DATETIME      DEFAULT NULL,

    -- Timestamps
    requested_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (reviewer_id)   REFERENCES users(id)              ON DELETE CASCADE,
    FOREIGN KEY (criterion_id)  REFERENCES scoring_criteria(id)   ON DELETE SET NULL,
    FOREIGN KEY (program_id)    REFERENCES scholarship_programs(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id)      REFERENCES users(id)              ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_scr_status    ON scoring_criteria_requests (status);
CREATE INDEX IF NOT EXISTS idx_scr_program   ON scoring_criteria_requests (program_id);
CREATE INDEX IF NOT EXISTS idx_scr_reviewer  ON scoring_criteria_requests (reviewer_id);
