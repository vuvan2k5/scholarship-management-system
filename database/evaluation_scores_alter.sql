-- ============================================================
-- database/evaluation_scores_alter.sql
-- Adds: verification_status to evaluation_scores
-- Adds: evaluation_score_history (change log)
-- Run once in phpMyAdmin or MySQL CLI
-- ============================================================

USE scholarship_system;

-- ── 1. Add verification_status to evaluation_scores ─────────
ALTER TABLE evaluation_scores
    ADD COLUMN verification_status
        ENUM('verified','need_clarification','rejected_evidence') NOT NULL DEFAULT 'verified'
        COMMENT 'Reviewer assessment of evidence quality'   AFTER note,
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP                         AFTER verification_status;

-- ── 2. Score change history ──────────────────────────────────
CREATE TABLE IF NOT EXISTS evaluation_score_history (
    id              INT(11)       AUTO_INCREMENT PRIMARY KEY,
    score_id        INT(11)       NOT NULL
        COMMENT 'FK → evaluation_scores.id',
    application_id  INT(11)       NOT NULL,
    criteria_id     INT(11)       NOT NULL,
    reviewer_id     INT(11)       NOT NULL,
    old_score       DECIMAL(6,2)  DEFAULT NULL,
    new_score       DECIMAL(6,2)  NOT NULL,
    old_note        TEXT          DEFAULT NULL,
    new_note        TEXT          DEFAULT NULL,
    old_verification_status VARCHAR(50) DEFAULT NULL,
    new_verification_status VARCHAR(50) DEFAULT NULL,
    changed_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (score_id)       REFERENCES evaluation_scores(id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES applications(id)      ON DELETE CASCADE,
    FOREIGN KEY (criteria_id)    REFERENCES scoring_criteria(id)  ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id)    REFERENCES users(id)             ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_esh_score   ON evaluation_score_history (score_id);
CREATE INDEX IF NOT EXISTS idx_esh_app     ON evaluation_score_history (application_id);
CREATE INDEX IF NOT EXISTS idx_esh_changed ON evaluation_score_history (changed_at);
