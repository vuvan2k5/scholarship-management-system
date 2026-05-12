CREATE DATABASE scholarship_system;
USE scholarship_system;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,

    full_name VARCHAR(255) NOT NULL,

    email VARCHAR(255) NOT NULL UNIQUE,

    password_hash VARCHAR(255) NOT NULL,

    role ENUM('student', 'reviewer', 'admin') NOT NULL,

    student_code VARCHAR(50) UNIQUE NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE scholarship_programs (
    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(255) NOT NULL,

    description TEXT,

    budget DECIMAL(12,2) NOT NULL,

    slots INT NOT NULL,

    start_date DATE,

    end_date DATE,

    status ENUM('open', 'closed') DEFAULT 'open'
);
CREATE TABLE eligibility_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,

    program_id INT NOT NULL,

    rule_type VARCHAR(100) NOT NULL,

    operator VARCHAR(10) NOT NULL,

    value VARCHAR(100) NOT NULL,

    FOREIGN KEY (program_id)
    REFERENCES scholarship_programs(id)
    ON DELETE CASCADE
);
CREATE TABLE scoring_criteria (
    id INT AUTO_INCREMENT PRIMARY KEY,

    program_id INT NOT NULL,

    criterion_name VARCHAR(100) NOT NULL,

    weight DECIMAL(5,2) NOT NULL,

    max_score DECIMAL(5,2) DEFAULT 100,

    FOREIGN KEY (program_id)
    REFERENCES scholarship_programs(id)
    ON DELETE CASCADE
);
CREATE TABLE eligibility_results (
    id INT AUTO_INCREMENT PRIMARY KEY,

    application_id INT NOT NULL,

    is_passed TINYINT(1) NOT NULL,

    reason TEXT,

    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- SAMPLE DATA FOR CORE MODULE
-- Scholarship Management System

USE scholarship_system;

-- =====================================
-- USERS (50 RECORDS)
-- =====================================

INSERT INTO users (full_name, email, password_hash, role, student_code) VALUES
('Nguyen Van An', 'an01@student.edu.vn', 'hashed_password', 'student', 'SV001'),
('Tran Minh Hoang', 'hoang02@student.edu.vn', 'hashed_password', 'student', 'SV002'),
('Le Thu Ha', 'ha03@student.edu.vn', 'hashed_password', 'student', 'SV003'),
('Pham Gia Bao', 'bao04@student.edu.vn', 'hashed_password', 'student', 'SV004'),
('Vo Thanh Dat', 'dat05@student.edu.vn', 'hashed_password', 'student', 'SV005'),
('Bui Quoc Khanh', 'khanh06@student.edu.vn', 'hashed_password', 'student', 'SV006'),
('Dang Ngoc Linh', 'linh07@student.edu.vn', 'hashed_password', 'student', 'SV007'),
('Hoang Minh Quan', 'quan08@student.edu.vn', 'hashed_password', 'student', 'SV008'),
('Do Thi Mai', 'mai09@student.edu.vn', 'hashed_password', 'student', 'SV009'),
('Phan Duc Huy', 'huy10@student.edu.vn', 'hashed_password', 'student', 'SV010'),
('Nguyen Tuan Kiet', 'kiet11@student.edu.vn', 'hashed_password', 'student', 'SV011'),
('Tran Bao Chau', 'chau12@student.edu.vn', 'hashed_password', 'student', 'SV012'),
('Le Minh Tri', 'tri13@student.edu.vn', 'hashed_password', 'student', 'SV013'),
('Pham Thanh Tung', 'tung14@student.edu.vn', 'hashed_password', 'student', 'SV014'),
('Vo Ngoc Anh', 'anh15@student.edu.vn', 'hashed_password', 'student', 'SV015'),
('Bui Thanh Nhan', 'nhan16@student.edu.vn', 'hashed_password', 'student', 'SV016'),
('Dang Hoang Long', 'long17@student.edu.vn', 'hashed_password', 'student', 'SV017'),
('Hoang Quynh Nhu', 'nhu18@student.edu.vn', 'hashed_password', 'student', 'SV018'),
('Do Minh Tam', 'tam19@student.edu.vn', 'hashed_password', 'student', 'SV019'),
('Phan Gia Huy', 'huy20@student.edu.vn', 'hashed_password', 'student', 'SV020'),
('Nguyen Duc Tai', 'tai21@student.edu.vn', 'hashed_password', 'student', 'SV021'),
('Tran Ha My', 'my22@student.edu.vn', 'hashed_password', 'student', 'SV022'),
('Le Bao Ngoc', 'ngoc23@student.edu.vn', 'hashed_password', 'student', 'SV023'),
('Pham Thanh Ha', 'ha24@student.edu.vn', 'hashed_password', 'student', 'SV024'),
('Vo Minh Duc', 'duc25@student.edu.vn', 'hashed_password', 'student', 'SV025'),
('Bui Tuan Anh', 'anh26@student.edu.vn', 'hashed_password', 'student', 'SV026'),
('Dang Gia Han', 'han27@student.edu.vn', 'hashed_password', 'student', 'SV027'),
('Hoang Thanh Son', 'son28@student.edu.vn', 'hashed_password', 'student', 'SV028'),
('Do Thi Lan', 'lan29@student.edu.vn', 'hashed_password', 'student', 'SV029'),
('Phan Minh Khoa', 'khoa30@student.edu.vn', 'hashed_password', 'student', 'SV030'),
('Nguyen Anh Tuan', 'tuan31@student.edu.vn', 'hashed_password', 'student', 'SV031'),
('Tran Kim Ngan', 'ngan32@student.edu.vn', 'hashed_password', 'student', 'SV032'),
('Le Thanh Binh', 'binh33@student.edu.vn', 'hashed_password', 'student', 'SV033'),
('Pham Hoang Nam', 'nam34@student.edu.vn', 'hashed_password', 'student', 'SV034'),
('Vo Bao Tram', 'tram35@student.edu.vn', 'hashed_password', 'student', 'SV035'),
('Bui Minh Thu', 'thu36@student.edu.vn', 'hashed_password', 'student', 'SV036'),
('Dang Quoc Viet', 'viet37@student.edu.vn', 'hashed_password', 'student', 'SV037'),
('Hoang Gia Linh', 'linh38@student.edu.vn', 'hashed_password', 'student', 'SV038'),
('Do Thanh Phuc', 'phuc39@student.edu.vn', 'hashed_password', 'student', 'SV039'),
('Phan Duc Minh', 'minh40@student.edu.vn', 'hashed_password', 'student', 'SV040'),
('Nguyen Thi Yen', 'yen41@student.edu.vn', 'hashed_password', 'student', 'SV041'),
('Tran Minh Chau', 'chau42@student.edu.vn', 'hashed_password', 'student', 'SV042'),
('Le Hoang Vu', 'vu43@student.edu.vn', 'hashed_password', 'student', 'SV043'),
('Pham Ngoc Han', 'han44@student.edu.vn', 'hashed_password', 'student', 'SV044'),
('Vo Quang Huy', 'huy45@student.edu.vn', 'hashed_password', 'student', 'SV045'),
('Bui Thanh Tung', 'tung46@student.edu.vn', 'hashed_password', 'student', 'SV046'),
('Dang Minh Anh', 'anh47@student.edu.vn', 'hashed_password', 'student', 'SV047'),
('Hoang Bao Long', 'long48@student.edu.vn', 'hashed_password', 'student', 'SV048'),
('Do Gia Huy', 'huy49@student.edu.vn', 'hashed_password', 'student', 'SV049'),
('Phan Thanh Dat', 'dat50@student.edu.vn', 'hashed_password', 'student', 'SV050');

-- ADMIN & REVIEWER ACCOUNTS
INSERT INTO users (full_name, email, password_hash, role, student_code) VALUES
('System Admin', 'admin@scholarship.edu.vn', 'admin123', 'admin', NULL),
('Reviewer One', 'reviewer1@scholarship.edu.vn', 'review123', 'reviewer', NULL),
('Reviewer Two', 'reviewer2@scholarship.edu.vn', 'reviewer123', 'reviewer', NULL);

-- =====================================
-- SCHOLARSHIP PROGRAMS
-- =====================================

INSERT INTO scholarship_programs
(name, description, budget, slots, start_date, end_date, status)
VALUES

(
'International Talent Scholarship',
'Scholarship for internationally competitive students.',
20000000,
3,
'2026-03-01',
'2026-09-01',
'open'
),

(
'Research Innovation Scholarship',
'Support scholarship for students participating in scientific research.',
15000000,
5,
'2026-01-10',
'2026-07-15',
'open'
),

(
'Academic Excellence Scholarship',
'Scholarship for students with outstanding academic performance.',
12000000,
5,
'2026-01-01',
'2026-06-30',
'open'
),

(
'Community Leadership Scholarship',
'Scholarship for active students contributing to community activities.',
8000000,
6,
'2026-02-01',
'2026-08-01',
'open'
),

(
'Financial Support Scholarship',
'Scholarship for students with financial difficulties.',
2000000,
10,
'2026-01-15',
'2026-07-30',
'open'
);

-- =====================================
-- ELIGIBILITY RULES
-- =====================================

INSERT INTO eligibility_rules (program_id, rule_type, operator, value) VALUES
(1, 'gpa', '>=', '3.5'),
(1, 'activities', '>=', '5'),
(1, 'failed_subjects', '=', '0'),

(2, 'gpa', '>=', '3.2'),
(2, 'research_projects', '>=', '1'),
(2, 'failed_subjects', '=', '0'),

(3, 'activities', '>=', '10'),
(3, 'discipline', '=', 'good'),

(4, 'financial_status', '=', 'difficult'),
(4, 'gpa', '>=', '2.8'),

(5, 'english_score', '>=', '850'),
(5, 'gpa', '>=', '3.7'),
(5, 'research_projects', '>=', '2');

-- =====================================
-- SCORING CRITERIA
-- =====================================

INSERT INTO scoring_criteria
(program_id, criterion_name, weight, max_score)
VALUES
(1, 'GPA', 40.00, 100),
(1, 'Research', 25.00, 100),
(1, 'Activities', 20.00, 100),
(1, 'Financial Condition', 15.00, 100),

(2, 'Research Output', 50.00, 100),
(2, 'GPA', 30.00, 100),
(2, 'Presentation', 20.00, 100),

(3, 'Community Activities', 50.00, 100),
(3, 'Leadership', 30.00, 100),
(3, 'Discipline', 20.00, 100),

(4, 'Financial Difficulty', 50.00, 100),
(4, 'Academic Performance', 30.00, 100),
(4, 'Activities', 20.00, 100),

(5, 'International Awards', 40.00, 100),
(5, 'Research', 30.00, 100),
(5, 'English Proficiency', 30.00, 100);

-- =====================================
-- ELIGIBILITY RESULTS
-- =====================================

INSERT INTO eligibility_results
(application_id, is_passed, reason)
VALUES
(1, 1, 'Student satisfies all eligibility requirements.'),
(2, 1, 'Student satisfies GPA and activity conditions.'),
(3, 0, 'GPA below minimum requirement.'),
(4, 1, 'Eligible for scholarship program.'),
(5, 0, 'Insufficient extracurricular activities.'),
(6, 1, 'Passed all validation checks.'),
(7, 1, 'Eligible based on academic performance.'),
(8, 0, 'Failed subject detected.'),
(9, 1, 'Student meets scholarship criteria.'),
(10, 1, 'Approved after eligibility review.');
