-- ============================================================
-- feature_upgrade.sql
-- Migration cho 4 tính năng nâng cao phía Student
-- Chạy 1 lần trong phpMyAdmin hoặc MySQL CLI
-- ============================================================

USE scholarship_system;

-- ── 1. Bảng Kho Tài Liệu Sinh Viên (Document Wallet) ────────
CREATE TABLE IF NOT EXISTS student_documents (
    id            INT(11) AUTO_INCREMENT PRIMARY KEY,
    student_id    INT(11) NOT NULL,
    document_type VARCHAR(100) NOT NULL COMMENT 'Loại tài liệu: GPA, IELTS, TOEIC, CCCD, Certificate...',
    display_name  VARCHAR(255) NOT NULL COMMENT 'Tên hiển thị do sinh viên đặt',
    original_name VARCHAR(255) NOT NULL COMMENT 'Tên file gốc',
    stored_name   VARCHAR(255) NOT NULL COMMENT 'Tên file đã đổi để tránh trùng',
    file_path     VARCHAR(500) NOT NULL COMMENT 'Đường dẫn tương đối từ web root',
    file_size     INT(11) DEFAULT 0,
    file_type     VARCHAR(100) DEFAULT NULL COMMENT 'MIME type',
    uploaded_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ── 2. Cột notes cho applications (ghi chú khi lưu nháp) ────
ALTER TABLE applications
    ADD COLUMN IF NOT EXISTS draft_notes TEXT DEFAULT NULL
        COMMENT 'Ghi chú khi lưu nháp, xoá khi nộp chính thức';

-- ── 3. Bảng liên kết: đơn ứng tuyển dùng tài liệu từ wallet ─
--     (1 evidence row có thể trỏ đến 1 wallet doc thay vì file upload)
ALTER TABLE application_evidence
    ADD COLUMN IF NOT EXISTS wallet_doc_id INT(11) DEFAULT NULL
        COMMENT 'NULL = file upload mới; không NULL = lấy từ student_documents',
    ADD CONSTRAINT fk_evidence_wallet
        FOREIGN KEY (wallet_doc_id) REFERENCES student_documents(id)
        ON DELETE SET NULL;
