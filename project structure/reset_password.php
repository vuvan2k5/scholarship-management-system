<?php
require_once __DIR__ . '/config/db.php';

$pdo = getDB();

$hash = password_hash('123456', PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
$stmt->execute([$hash, 'admin@scholarship.edu.vn']);

echo "Done. Password is 123456";