-- ============================================================
-- database/eligibility_engine_alter.sql
-- Adds: engine_run_history table to track every batch run.
-- Adds: rule_trace_json column to eligibility_results for
--       per-rule breakdown detail.
-- Run once in phpMyAdmin or MySQL CLI.
-- ============================================================
USE scholarship_system;
-- ── 1. Extend eligibility_results with detailed trace ────────
ALTER TABLE eligibility_results
ADD COLUMN rule_trace JSON DEFAULT NULL COMMENT 'JSON array of per-rule evaluation detail'
AFTER reason,
    ADD COLUMN checked_by INT(11) DEFAULT NULL COMMENT 'FK → users.id (admin who triggered the run)'
AFTER rule_trace,
    ADD CONSTRAINT fk_er_checked_by FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE
SET NULL;
-- ── 2. Engine run history (batch-level) ──────────────────────
CREATE TABLE IF NOT EXISTS engine_run_history (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    -- Which program (NULL = all programs)
    program_id INT(11) DEFAULT NULL,
    -- Run counts
    total_checked INT(11) NOT NULL DEFAULT 0,
    total_passed INT(11) NOT NULL DEFAULT 0,
    total_failed INT(11) NOT NULL DEFAULT 0,
    -- Who triggered it
    executed_by INT(11) NOT NULL,
    -- When
    run_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Notes (e.g. "Re-run after rule update")
    notes VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (program_id) REFERENCES scholarship_programs(id) ON DELETE
    SET NULL,
        FOREIGN KEY (executed_by) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_erh_program ON engine_run_history (program_id);
CREATE INDEX IF NOT EXISTS idx_erh_run_at ON engine_run_history (run_at);