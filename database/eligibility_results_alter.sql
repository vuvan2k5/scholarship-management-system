-- ============================================================
-- database/eligibility_results_alter.sql
-- Adds: reviewer_verification_status to eligibility_results
-- Creates: reviewer_verifications table (reviewer assignment log)
-- Run once in phpMyAdmin or MySQL CLI
-- ============================================================

USE scholarship_system;

-- ── 1. Add reviewer verification status to eligibility_results
ALTER TABLE eligibility_results
    ADD COLUMN IF NOT EXISTS reviewer_verification_status
        ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending'
        COMMENT 'Reviewer verification outcome for this eligibility result'
        AFTER checked_by;

-- ── 2. Reviewer verifications log ───────────────────────────
CREATE TABLE IF NOT EXISTS reviewer_verifications (
    id                  INT(11)      AUTO_INCREMENT PRIMARY KEY,
    eligibility_id      INT(11)      NOT NULL
        COMMENT 'FK → eligibility_results.id',
    reviewer_id         INT(11)      NOT NULL
        COMMENT 'FK → users.id (the reviewer)',
    status              ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    notes               TEXT         DEFAULT NULL,
    verified_at         DATETIME     DEFAULT NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (eligibility_id) REFERENCES eligibility_results(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id)    REFERENCES users(id)               ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_rv_eligibility ON reviewer_verifications (eligibility_id);
CREATE INDEX IF NOT EXISTS idx_rv_reviewer    ON reviewer_verifications (reviewer_id);
CREATE INDEX IF NOT EXISTS idx_rv_status      ON reviewer_verifications (status);
