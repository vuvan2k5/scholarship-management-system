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


USE scholarship_system;

INSERT INTO evaluation_scores (
    application_id,
    criteria_id,
    council_id,
    score
)
SELECT
    a.id AS application_id,
    sc.id AS criteria_id,
    52 AS council_id,
    FLOOR(60 + RAND() * 40) AS score
FROM applications a
JOIN scoring_criteria sc
    ON sc.program_id = a.program_id
LEFT JOIN evaluation_scores es
    ON es.application_id = a.id
   AND es.criteria_id = sc.id
WHERE es.id IS NULL;

ALTER TABLE ranking_results AUTO_INCREMENT = 1;

SET @current_program := 0;
SET @current_rank := 0;

INSERT INTO ranking_results (
    application_id,
    program_id,
    total_score,
    `rank`,
    recommended
)
SELECT
    final_rank.application_id,
    final_rank.program_id,
    final_rank.total_score,
    final_rank.rank_no,
    CASE
        WHEN final_rank.rank_no <= sp.slots THEN 1
        ELSE 0
    END AS recommended
FROM (
    SELECT
        score_table.application_id,
        score_table.program_id,
        score_table.total_score,
        @current_rank := CASE
            WHEN @current_program = score_table.program_id
            THEN @current_rank + 1
            ELSE 1
        END AS rank_no,
        @current_program := score_table.program_id
    FROM (
        SELECT
            a.id AS application_id,
            a.program_id,
            ROUND(SUM(es.score * sc.weight / 100), 2) AS total_score
        FROM applications a
        JOIN evaluation_scores es
            ON a.id = es.application_id
        JOIN scoring_criteria sc
            ON es.criteria_id = sc.id
           AND sc.program_id = a.program_id
        GROUP BY a.id, a.program_id
        ORDER BY a.program_id ASC, total_score DESC, a.id ASC
    ) AS score_table
) AS final_rank
JOIN scholarship_programs sp
    ON final_rank.program_id = sp.id;
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
    rr.application_id,
    ROUND(sp.budget / sp.slots, 2) AS amount,
    'approved' AS status,
    NULL AS disbursed_at,
    CONCAT(
        'Approved for scholarship: ',
        sp.name,
        '. Amount = budget / slots.'
    ) AS note
FROM ranking_results rr
JOIN scholarship_programs sp
    ON rr.program_id = sp.id
WHERE rr.recommended = 1;

ALTER TABLE award_certificates AUTO_INCREMENT = 1;

-- Tạo chứng nhận cho sinh viên được đề xuất học bổng
INSERT INTO award_certificates (
    application_id,
    certificate_code,
    issued_at,
    issued_by
)
SELECT
    rr.application_id,

    CONCAT(
        'CERT-',
        LPAD(rr.program_id, 3, '0'),
        '-',
        LPAD(rr.application_id, 5, '0'),
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

FROM ranking_results rr
WHERE rr.recommended = 1;


-- =====================================================
-- KIỂM TRA KẾT QUẢ
-- =====================================================

SELECT * FROM award_certificates;


-- =====================================================
-- XEM CHI TIẾT CHỨNG NHẬN
-- =====================================================

SELECT
    ac.id,
    u.full_name AS student_name,
    sp.name AS scholarship_program,
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

LEFT JOIN users admin_user
    ON ac.issued_by = admin_user.id;

ALTER TABLE reports AUTO_INCREMENT = 1;


-- =====================================================
-- 1. REPORT RANKING
-- Báo cáo xếp hạng học bổng
-- =====================================================

INSERT INTO reports (
    report_type,
    generated_by,
    file_url,
    program_id
)
SELECT
    'ranking' AS report_type,

    (
        SELECT id
        FROM users
        WHERE role = 'admin'
        LIMIT 1
    ) AS generated_by,

    CONCAT(
        '/reports/ranking_program_',
        rr.program_id,
        '.pdf'
    ) AS file_url,

    rr.program_id

FROM ranking_results rr
GROUP BY rr.program_id;


-- =====================================================
-- 2. REPORT DISBURSEMENT
-- Báo cáo giải ngân học bổng
-- =====================================================

INSERT INTO reports (
    report_type,
    generated_by,
    file_url,
    program_id
)
SELECT
    'disbursement' AS report_type,

    (
        SELECT id
        FROM users
        WHERE role = 'admin'
        LIMIT 1
    ) AS generated_by,

    CONCAT(
        '/reports/disbursement_program_',
        rr.program_id,
        '.pdf'
    ) AS file_url,

    rr.program_id

FROM disbursements d
JOIN applications a
    ON d.application_id = a.id
JOIN ranking_results rr
    ON rr.application_id = a.id

GROUP BY rr.program_id;


-- =====================================================
-- 3. REPORT CERTIFICATE
-- Báo cáo chứng nhận học bổng
-- =====================================================

INSERT INTO reports (
    report_type,
    generated_by,
    file_url,
    program_id
)
SELECT
    'certificate' AS report_type,

    (
        SELECT id
        FROM users
        WHERE role = 'admin'
        LIMIT 1
    ) AS generated_by,

    CONCAT(
        '/reports/certificate_program_',
        rr.program_id,
        '.pdf'
    ) AS file_url,

    rr.program_id

FROM award_certificates ac
JOIN applications a
    ON ac.application_id = a.id
JOIN ranking_results rr
    ON rr.application_id = a.id

GROUP BY rr.program_id;


-- =====================================================
-- 4. REPORT SUMMARY
-- Báo cáo tổng hợp toàn hệ thống
-- =====================================================

INSERT INTO reports (
    report_type,
    generated_by,
    file_url,
    program_id
)
VALUES (
    'summary',

    (
        SELECT id
        FROM users
        WHERE role = 'admin'
        LIMIT 1
    ),

    '/reports/system_summary.pdf',

    NULL
);


-- =====================================================
-- KIỂM TRA KẾT QUẢ
-- =====================================================

SELECT * FROM reports;


-- =====================================================
-- XEM CHI TIẾT REPORTS
-- =====================================================

SELECT
    r.id,
    r.report_type,
    admin_user.full_name AS generated_by,
    sp.name AS scholarship_program,
    r.file_url,
    r.created_at

FROM reports r

LEFT JOIN users admin_user
    ON r.generated_by = admin_user.id

LEFT JOIN scholarship_programs sp
    ON r.program_id = sp.id

ORDER BY r.report_type, r.program_id;
