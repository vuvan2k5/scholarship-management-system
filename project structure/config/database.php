<?php
// ============================================================
// config/database.php  –  Legacy mysqli bridge
// Provides $conn for files that still use mysqli_*
// ============================================================

require_once __DIR__ . '/db.php';   // ensures DB_* constants are defined

if (!isset($conn) || !$conn) {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if (!$conn) {
        http_response_code(503);
        die('<p style="font-family:sans-serif;color:#c00;padding:20px">
             <strong>mysqli connection failed:</strong> ' . mysqli_connect_error() . '</p>');
    }

    mysqli_set_charset($conn, DB_CHARSET);
}
