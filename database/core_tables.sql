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