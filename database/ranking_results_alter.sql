-- ============================================================
-- database/ranking_results_alter.sql
-- Upgrades ranking_results with publication, award, tie-break.
-- Creates ranking_run_history for audit trail.
-- Run once in phpMyAdmin or MySQL CLI.
-- ============================================================

USE scholarship_system;

-- ── 1. Extend ranking_results ────────────────────────────────
ALTER TABLE ranking_results
    ADD COLUMN IF NOT EXISTS awarded         TINYINT(1)   NOT NULL DEFAULT 0
        COMMENT '1 = within slots (Awarded), 0 = Not Awarded'      AFTER recommended,
    ADD COLUMN IF NOT EXISTS tie_break_reason VARCHAR(100) DEFAULT NULL
        COMMENT 'e.g. Higher GPA / Earlier Submission'             AFTER awarded,
    ADD COLUMN IF NOT EXISTS published       TINYINT(1)   NOT NULL DEFAULT 0
        COMMENT '1 = results published to students'                AFTER tie_break_reason,
    ADD COLUMN IF NOT EXISTS published_at    DATETIME     DEFAULT NULL AFTER published,
    ADD COLUMN IF NOT EXISTS published_by    INT(11)      DEFAULT NULL AFTER published_at,
    ADD COLUMN IF NOT EXISTS generated_at    DATETIME     DEFAULT NULL AFTER published_by,
    ADD COLUMN IF NOT EXISTS generated_by    INT(11)      DEFAULT NULL AFTER generated_at;

-- FK constraints (safe — use IF NOT EXISTS pattern via try in PHP)
ALTER TABLE ranking_results
    ADD CONSTRAINT IF NOT EXISTS fk_rr_published_by
        FOREIGN KEY (published_by) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT IF NOT EXISTS fk_rr_generated_by
        FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL;

-- ── 2. Ranking run history ────────────────────────────────────
CREATE TABLE IF NOT EXISTS ranking_run_history (
    id             INT(11)      AUTO_INCREMENT PRIMARY KEY,
    program_id     INT(11)      DEFAULT NULL
        COMMENT 'NULL = all programs',
    total_ranked   INT(11)      NOT NULL DEFAULT 0,
    awarded_count  INT(11)      NOT NULL DEFAULT 0,
    slots_used     INT(11)      NOT NULL DEFAULT 0,
    generated_by   INT(11)      NOT NULL,
    generated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes          VARCHAR(255) DEFAULT NULL,

    FOREIGN KEY (program_id)  REFERENCES scholarship_programs(id) ON DELETE SET NULL,
    FOREIGN KEY (generated_by) REFERENCES users(id)              ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_rrh_program ON ranking_run_history (program_id);
CREATE INDEX IF NOT EXISTS idx_rrh_gen_at  ON ranking_run_history (generated_at);
