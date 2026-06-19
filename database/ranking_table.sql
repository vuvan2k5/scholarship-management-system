USE scholarship_system;

CREATE TABLE ranking_results (
    id               INT(11) AUTO_INCREMENT PRIMARY KEY,
    application_id   INT(11) NOT NULL,
    total_score      DECIMAL(5,2) NOT NULL,
    `rank`           INT NOT NULL,
    recommended      TINYINT(1) DEFAULT 0,
    awarded          TINYINT(1) NOT NULL DEFAULT 0,
    tie_break_reason VARCHAR(100) DEFAULT NULL,
    published        TINYINT(1) NOT NULL DEFAULT 0,
    published_at     DATETIME DEFAULT NULL,
    published_by     INT(11) DEFAULT NULL,
    generated_at     DATETIME DEFAULT NULL,
    generated_by     INT(11) DEFAULT NULL,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
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


USE scholarship_system;

DELETE FROM ranking_results;
ALTER TABLE ranking_results AUTO_INCREMENT = 1;

INSERT INTO ranking_results (
    application_id,
    total_score,
    `rank`,
    recommended
)
SELECT
    x.application_id,
    x.total_score,
    x.rank_no,
    CASE 
        WHEN x.rank_no <= sp.slots THEN 1 
        ELSE 0 
    END AS recommended
FROM (
    SELECT
        s.application_id,
        s.program_id,
        s.total_score,
        ROW_NUMBER() OVER (
            PARTITION BY s.program_id
            ORDER BY s.total_score DESC, s.application_id ASC
        ) AS rank_no
    FROM (
        SELECT
            a.id AS application_id,
            a.program_id,
            ROUND(SUM(es.score * sc.weight / 100), 2) AS total_score
        FROM applications a
        JOIN evaluation_scores es 
            ON es.application_id = a.id
        JOIN scoring_criteria sc 
            ON sc.id = es.criteria_id
           AND sc.program_id = a.program_id
        GROUP BY a.id, a.program_id
    ) s
) x
JOIN scholarship_programs sp 
    ON sp.id = x.program_id;

USE scholarship_system;

DELETE FROM disbursements;
ALTER TABLE disbursements AUTO_INCREMENT = 1;

INSERT INTO disbursements (
    application_id,
    amount,
    status,
    disbursed_at,
    note
)
SELECT
    final.application_id,
    final.amount,
    'approved',
    NULL,
    final.note
FROM (
    SELECT
        rr.application_id,
        a.program_id,
        rr.`rank`,
        sp.budget AS amount,
        sp.slots,
        CONCAT(
            'Approved for ',
            sp.name,
            '. Amount: ',
            FORMAT(sp.budget, 0),
            ' VND. Rank: ',
            rr.`rank`,
            '. Slot: ',
            sp.slots
        ) AS note,
        ROW_NUMBER() OVER (
            PARTITION BY a.program_id
            ORDER BY rr.`rank` ASC, rr.total_score DESC, rr.application_id ASC
        ) AS selected_order
    FROM ranking_results rr
    JOIN applications a
        ON rr.application_id = a.id
    JOIN scholarship_programs sp
        ON a.program_id = sp.id
    WHERE rr.recommended = 1
) AS final
WHERE final.selected_order <= final.slots;

USE scholarship_system;

-- ============================================================
-- MEMBER 3 – AWARD_CERTIFICATES
-- Cấp chứng nhận cho sinh viên đã được giải ngân học bổng
-- Dữ liệu bám theo disbursements, applications, users, scholarship_programs
-- ============================================================

CREATE TABLE IF NOT EXISTS award_certificates (
    id               INT(11) AUTO_INCREMENT PRIMARY KEY,
    application_id   INT(11) NOT NULL,
    certificate_code VARCHAR(100) NOT NULL UNIQUE,
    issued_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    issued_by        INT(11),

    FOREIGN KEY (application_id) 
    REFERENCES applications(id) 
    ON DELETE CASCADE,

    FOREIGN KEY (issued_by) 
    REFERENCES users(id) 
    ON DELETE SET NULL
);

-- Xóa dữ liệu cũ để tránh trùng khi chạy lại
DELETE FROM award_certificates;
ALTER TABLE award_certificates AUTO_INCREMENT = 1;

-- Tạo chứng nhận cho sinh viên đã có trong bảng disbursements
INSERT INTO award_certificates (
    application_id,
    certificate_code,
    issued_at,
    issued_by
)
SELECT
    d.application_id,

    CONCAT(
        'CERT-',
        LPAD(sp.id, 3, '0'),
        '-',
        LPAD(d.application_id, 5, '0'),
        '-',
        DATE_FORMAT(NOW(), '%Y%m%d%H%i%s')
    ) AS certificate_code,

    NOW() AS issued_at,

    (
        SELECT id
        FROM users
        WHERE role = 'admin'
        LIMIT 1
    ) AS issued_by

FROM disbursements d

JOIN applications a
    ON d.application_id = a.id

JOIN scholarship_programs sp
    ON a.program_id = sp.id

WHERE d.status IN ('approved', 'paid');

-- ============================================================
-- KIỂM TRA BẢNG AWARD_CERTIFICATES
-- ============================================================

SELECT * FROM award_certificates;

-- ============================================================
-- XEM CHI TIẾT CHỨNG NHẬN
-- ============================================================

SELECT
    ac.id,
    ac.application_id,
    u.full_name AS student_name,
    u.student_code,
    sp.name AS scholarship_program,
    d.amount,
    d.status AS disbursement_status,
    ac.certificate_code,
    ac.issued_at,
    admin_user.full_name AS issued_by

FROM award_certificates ac

JOIN applications a
    ON ac.application_id = a.id

JOIN users u
    ON a.student_id = u.id

JOIN scholarship_programs sp
    ON a.program_id = sp.id

JOIN disbursements d
    ON ac.application_id = d.application_id

LEFT JOIN users admin_user
    ON ac.issued_by = admin_user.id

ORDER BY sp.id ASC, ac.issued_at ASC;

USE scholarship_system;

DELETE FROM reports;
ALTER TABLE reports AUTO_INCREMENT = 1;

-- ============================================================
-- RANDOM REPORT 1
-- ============================================================

INSERT INTO reports (
    report_type,
    generated_by,
    file_url,
    program_id
)
SELECT
    'ranking',

    (
        SELECT id
        FROM users
        WHERE role = 'student'
        AND id <= 50
        ORDER BY RAND()
        LIMIT 1
    ),

    CONCAT(
        '/reports/student_question_ranking_student_',
        a.student_id,
        '.pdf'
    ),

    a.program_id

FROM ranking_results rr

JOIN applications a
    ON rr.application_id = a.id

JOIN users u
    ON a.student_id = u.id

WHERE rr.recommended = 0
AND u.role = 'student'
AND u.id <= 50

ORDER BY RAND()
LIMIT 1;

-- ============================================================
-- RANDOM REPORT 2
-- ============================================================

INSERT INTO reports (
    report_type,
    generated_by,
    file_url,
    program_id
)
SELECT
    'disbursement',

    (
        SELECT id
        FROM users
        WHERE role = 'student'
        AND id <= 50
        ORDER BY RAND()
        LIMIT 1
    ),

    CONCAT(
        '/reports/student_question_disbursement_student_',
        a.student_id,
        '.pdf'
    ),

    a.program_id

FROM disbursements d

JOIN applications a
    ON d.application_id = a.id

JOIN users u
    ON a.student_id = u.id

WHERE u.role = 'student'
AND u.id <= 50

ORDER BY RAND()
LIMIT 1;

-- ============================================================
-- RANDOM REPORT 3
-- ============================================================

INSERT INTO reports (
    report_type,
    generated_by,
    file_url,
    program_id
)
SELECT
    'certificate',

    (
        SELECT id
        FROM users
        WHERE role = 'student'
        AND id <= 50
        ORDER BY RAND()
        LIMIT 1
    ),

    CONCAT(
        '/reports/student_question_certificate_student_',
        a.student_id,
        '.pdf'
    ),

    a.program_id

FROM award_certificates ac

JOIN applications a
    ON ac.application_id = a.id

JOIN users u
    ON a.student_id = u.id

WHERE u.role = 'student'
AND u.id <= 50

ORDER BY RAND()
LIMIT 1;

-- ============================================================
-- XEM REPORTS
-- ============================================================

SELECT * FROM reports;

