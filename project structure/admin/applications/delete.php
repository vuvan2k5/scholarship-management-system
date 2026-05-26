<?php
// ============================================================
// admin/applications/delete.php
// ============================================================

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id) {
    $stmt = $pdo->prepare('DELETE FROM applications WHERE id = ?');
    $stmt->execute([$id]);
}

header('Location: index.php');
exit;
