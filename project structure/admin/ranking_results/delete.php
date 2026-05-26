<?php
// ============================================================
// admin/ranking_results/delete.php
// ============================================================

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("DELETE FROM ranking_results WHERE id = ?");
$stmt->execute([$id]);

header('Location: index.php');
exit;
