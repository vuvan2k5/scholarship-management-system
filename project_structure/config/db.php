<?php
// ============================================================
// config/db.php  –  Kết nối Database (PDO)
// Dùng chung cho toàn bộ nhóm – KHÔNG sửa tên hàm/biến
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'scholarship_system');
define('DB_USER',    'root');
define('DB_PASS',    '');          // XAMPP mặc định để trống
define('DB_CHARSET', 'utf8mb4');

/**
 * Trả về một PDO connection duy nhất (Singleton).
 * Gọi bằng: $pdo = getDB();
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST
             . ';dbname='    . DB_NAME
             . ';charset='   . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Hiển thị lỗi thân thiện, không lộ thông tin nhạy cảm
            die('<div style="font-family:sans-serif;padding:20px;color:#c00">
                    <strong>Lỗi kết nối database.</strong><br>
                    Kiểm tra lại XAMPP đã bật MySQL chưa, và tên DB có đúng không.
                 </div>');
        }
    }
    return $pdo;
}
