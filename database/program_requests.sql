-- ============================================================
-- database/program_requests.sql
-- Reviewer proposals for scholarship program changes
-- Admin reviews and approves/rejects each request
-- Run once in phpMyAdmin or MySQL CLI before using the module
-- ============================================================

USE scholarship_system;

CREATE TABLE IF NOT EXISTS program_requests (
    id           INT(11)      AUTO_INCREMENT PRIMARY KEY,

    -- The reviewer who made the request
    reviewer_id  INT(11)      NOT NULL,

    -- Request type: add | update | suspend | delete
    request_type ENUM('add','update','suspend','delete') NOT NULL DEFAULT 'add',

    -- Target program (NULL for 'add' requests until admin creates it)
    program_id   INT(11)      DEFAULT NULL,

    -- Proposed program data (JSON snapshot of proposed fields)
    proposed_data JSON        DEFAULT NULL
        COMMENT 'JSON: {name, description, budget, slots, start_date, end_date, status}',

    -- Reason / justification from reviewer
    reason       TEXT         DEFAULT NULL,

    -- Workflow
    status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',

    -- Admin response
    admin_id     INT(11)      DEFAULT NULL,
    admin_note   TEXT         DEFAULT NULL,
    responded_at DATETIME     DEFAULT NULL,

    -- Timestamps
    requested_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (reviewer_id) REFERENCES users(id)              ON DELETE CASCADE,
    FOREIGN KEY (program_id)  REFERENCES scholarship_programs(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id)    REFERENCES users(id)              ON DELETE SET NULL
);

-- Indexes for fast lookups
CREATE INDEX IF NOT EXISTS idx_pr_status      ON program_requests (status);
CREATE INDEX IF NOT EXISTS idx_pr_reviewer    ON program_requests (reviewer_id);
CREATE INDEX IF NOT EXISTS idx_pr_program     ON program_requests (program_id);
