<?php
// ============================================================
// admin/award_certificates/delete.php
// ============================================================

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("DELETE FROM award_certificates WHERE id = ?");
$stmt->execute([$id]);

header('Location: index.php');
exit;
