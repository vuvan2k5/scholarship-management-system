<?php
// ============================================================
// config/db.php  –  PDO Database Connection (Singleton)
// ============================================================

if (!defined('DB_HOST'))    define('DB_HOST',    'localhost');
if (!defined('DB_NAME'))    define('DB_NAME',    'scholarship_system');
if (!defined('DB_USER'))    define('DB_USER',    'root');
if (!defined('DB_PASS'))    define('DB_PASS',    '');       // XAMPP default: empty
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO connection.
 * Usage: $pdo = getDB();
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
            // Show a friendly error without leaking credentials
            http_response_code(503);
            die('
<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Database Error</title>
<style>body{font-family:system-ui,sans-serif;background:#f1f5f9;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{background:#fff;padding:40px;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.1);max-width:480px;text-align:center}
h2{color:#dc2626}p{color:#64748b}</style></head>
<body><div class="box">
<h2>⚠️ Database Connection Failed</h2>
<p>Could not connect to MySQL. Please make sure XAMPP MySQL is running and the database <strong>scholarship_system</strong> exists.</p>
</div></body></html>');
        }
    }

    return $pdo;
}
