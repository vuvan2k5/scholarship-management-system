<?php

require_once __DIR__ . '/../../config/db.php';

$pdo = getDB();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id) {
    $stmt = $pdo->prepare('DELETE FROM notifications WHERE id = ?');
    $stmt->execute([$id]);
}

header('Location: index.php');
exit;
?>
