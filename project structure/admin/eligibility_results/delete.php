<?php
// ============================================================
// admin/eligibility_results/delete.php
// ============================================================

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

if (!isset($_GET['id'])) {
    die("Invalid request.");
}

$id = (int)$_GET['id'];

// Delete result
$sql = "DELETE FROM eligibility_results WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

header("Location: index.php");
exit;