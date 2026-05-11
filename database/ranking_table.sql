CREATE TABLE ranking_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    total_score DECIMAL(5,2) NOT NULL,
    `rank` INT NOT NULL,
    recommended TINYINT(1) DEFAULT 0
);

CREATE TABLE disbursements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('pending', 'approved', 'paid', 'failed') DEFAULT 'pending',
    disbursed_at DATETIME NULL,
    note TEXT
);

CREATE TABLE award_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    certificate_code VARCHAR(100) NOT NULL UNIQUE,
    issued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    issued_by INT
);

CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_type ENUM('ranking', 'disbursement', 'certificate', 'summary') NOT NULL,
    generated_by INT,
    file_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    program_id INT
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
