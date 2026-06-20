-- ============================================================
-- MEMBER 2 – APPLICATION + EVALUATION TABLES
-- Yến: applications, student_profiles, evaluation_scores, notifications
-- Đã đồng bộ với ERD (MySQL Workbench)
-- ============================================================

USE scholarship_system;

-- 5. applications – Hồ sơ đăng ký học bổng (trung tâm hệ thống)
CREATE TABLE IF NOT EXISTS applications (
    id            INT(11) AUTO_INCREMENT PRIMARY KEY,
    student_id    INT(11) NOT NULL,
    program_id    INT(11) NOT NULL,
    status        ENUM('draft','submitted','reviewing','eligible','ineligible','approved','rejected','disbursed') DEFAULT 'draft',
    eligible      TINYINT(1) DEFAULT NULL COMMENT '1=đủ điều kiện, 0=không đủ',
    submitted_at  TIMESTAMP NULL DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id)  REFERENCES scholarship_programs(id) ON DELETE CASCADE,
    UNIQUE KEY uq_student_program (student_id, program_id)
);

-- 6. student_profiles – Thông tin sinh viên phục vụ scoring
CREATE TABLE IF NOT EXISTS student_profiles (
    id                INT(11) AUTO_INCREMENT PRIMARY KEY,
    student_id        INT(11) NOT NULL UNIQUE,
    faculty           VARCHAR(100),
    major             VARCHAR(100),
    gpa               DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    activities_count  INT(11) DEFAULT 0 COMMENT 'Số hoạt động ngoại khoá',
    family_income     DECIMAL(15,2) DEFAULT NULL COMMENT 'Thu nhập gia đình (VNĐ/tháng)',
    is_disadvantaged  TINYINT(1) DEFAULT 0 COMMENT '1=hoàn cảnh khó khăn',
    research_count    INT(11) DEFAULT 0 COMMENT 'Số công trình nghiên cứu',
    failed_subjects   INT(11) DEFAULT 0 COMMENT 'Số môn thi lại / trượt',
    has_language_cert TINYINT(1) DEFAULT 0 COMMENT '1=có chứng chỉ ngoại ngữ',
    activities_list   TEXT DEFAULT NULL COMMENT 'JSON array danh sách hoạt động ngoại khóa',
    research_list     TEXT DEFAULT NULL COMMENT 'JSON array danh sách đề tài NCKH',
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 8. evaluation_scores – Điểm từng tiêu chí do reviewer chấm
CREATE TABLE IF NOT EXISTS evaluation_scores (
    id             INT(11) AUTO_INCREMENT PRIMARY KEY,
    application_id INT(11) NOT NULL,
    criteria_id    INT(11) NOT NULL,
    council_id     INT(11) NOT NULL COMMENT 'reviewer/admin chấm điểm',
    score          DECIMAL(6,2) NOT NULL,
    note           TEXT,
    scored_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (criteria_id)    REFERENCES scoring_criteria(id) ON DELETE CASCADE,
    FOREIGN KEY (council_id)     REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_eval (application_id, criteria_id, council_id)
);

-- notifications – Thông báo hệ thống
CREATE TABLE IF NOT EXISTS notifications (
    id         INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id    INT(11) NOT NULL,
    title      VARCHAR(255) NOT NULL,
    message    TEXT NOT NULL,
    type       ENUM('info','success','warning','error') DEFAULT 'info',
    is_read    TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add Foreign Key to eligibility_results referencing applications
ALTER TABLE eligibility_results
ADD CONSTRAINT fk_eligibility_results_applications
FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE;

-- ============================================================
-- application_evidence – Minh chứng nộp kèm hồ sơ học bổng
-- Student upload files (PDF, images, etc.) when applying
-- Reviewer uses file_path to open and verify evidence
-- ============================================================
CREATE TABLE IF NOT EXISTS application_evidence (
    id              INT(11) AUTO_INCREMENT PRIMARY KEY,
    application_id  INT(11) NOT NULL,
    student_id      INT(11) NOT NULL,
    original_name   VARCHAR(255) NOT NULL COMMENT 'Tên file gốc của sinh viên',
    stored_name     VARCHAR(255) NOT NULL COMMENT 'Tên file đã lưu trên server (unique)',
    file_path       VARCHAR(500) NOT NULL COMMENT 'Đường dẫn tương đối: uploads/evidence/...',
    file_size       INT(11) DEFAULT 0 COMMENT 'Kích thước file (bytes)',
    file_type       VARCHAR(100) DEFAULT NULL COMMENT 'MIME type (image/jpeg, application/pdf...)',
    status          ENUM('pending','approved','rejected') DEFAULT 'pending',
    reviewer_comment TEXT DEFAULT NULL COMMENT 'Nhận xét của reviewer về file minh chứng',
    uploaded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id)     REFERENCES users(id) ON DELETE CASCADE
);
