<?php
// ============================================================
// admin/scholarship_programs/delete.php
// Admin deletes a program. Blocked if applications exist.
// ============================================================
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header('Location: index.php');
    exit;
}

// Fetch program for flash message
$stmt = $pdo->prepare("SELECT name FROM scholarship_programs WHERE id = ?");
$stmt->execute([$id]);
$prog = $stmt->fetch();

if (!$prog) {
    setFlash('error', 'Program not found.');
    header('Location: index.php');
    exit;
}

// Safety check: block deletion if applications exist
$chk = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE program_id = ?");
$chk->execute([$id]);
$appCount = (int)$chk->fetchColumn();

if ($appCount > 0) {
    setFlash('error', "Cannot delete \"{$prog['name']}\": {$appCount} application(s) are linked to this program. Close or suspend it instead.");
    header('Location: index.php');
    exit;
}

// Safe to delete — cascade removes eligibility_rules and scoring_criteria (FK CASCADE)
$pdo->prepare("DELETE FROM scholarship_programs WHERE id = ?")->execute([$id]);

setFlash('success', "Program \"{$prog['name']}\" has been deleted.");
header('Location: index.php');
exit;