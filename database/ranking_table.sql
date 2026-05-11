USE scholarship_system;

CREATE TABLE ranking_results (
    id             INT(11) AUTO_INCREMENT PRIMARY KEY,
    application_id INT(11) NOT NULL,
    program_id     INT(11) NOT NULL,
    total_score    DECIMAL(5,2) NOT NULL,
    `rank`         INT NOT NULL,
    recommended    TINYINT(1) DEFAULT 0,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id)     REFERENCES scholarship_programs(id) ON DELETE CASCADE
);

CREATE TABLE disbursements (
    id             INT(11) AUTO_INCREMENT PRIMARY KEY,
    application_id INT(11) NOT NULL,
    amount         DECIMAL(12,2) NOT NULL,
    status         ENUM('pending','approved','paid','failed') DEFAULT 'pending',
    disbursed_at   DATETIME NULL,
    note           TEXT,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

CREATE TABLE award_certificates (
    id               INT(11) AUTO_INCREMENT PRIMARY KEY,
    application_id   INT(11) NOT NULL,
    certificate_code VARCHAR(100) NOT NULL UNIQUE,
    issued_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    issued_by        INT(11),
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by)      REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE reports (
    id           INT(11) AUTO_INCREMENT PRIMARY KEY,
    report_type  ENUM('ranking','disbursement','certificate','summary') NOT NULL,
    generated_by INT(11),
    file_url     VARCHAR(255),
    program_id   INT(11),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (program_id)   REFERENCES scholarship_programs(id) ON DELETE SET NULL
);
