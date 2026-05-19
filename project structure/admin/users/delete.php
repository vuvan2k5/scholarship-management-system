<?php

require_once '../../config/db.php';

require_once '../../includes/auth.php';

requireLogin();

requireRole('admin');

$pdo = getDB();

$id = $_GET['id'] ?? null;

if (!$id) {

    die('Invalid User ID');
}

$sql = "
    DELETE FROM users
    WHERE id = ?
";

$stmt = $pdo->prepare($sql);

$stmt->execute([$id]);

header('Location: index.php');

exit;