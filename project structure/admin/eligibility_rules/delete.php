<?php
// ============================================================
// admin/eligibility_rules/delete.php
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

// Delete rule
$sql = "DELETE FROM eligibility_rules WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);

header("Location: index.php");
exit;