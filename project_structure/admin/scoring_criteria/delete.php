<?php
// ============================================================
// admin/scoring_criteria/delete.php
// Deletes a scoring criterion with flash feedback.
// Warns if deleting will leave the program's weight unbalanced.
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

$stmt = $pdo->prepare("
    SELECT sc.id, sc.criterion_name, sc.weight, sc.program_id, sc.is_active,
           sp.name AS program_name
    FROM scoring_criteria sc
    JOIN scholarship_programs sp ON sc.program_id = sp.id
    WHERE sc.id = ?
");
$stmt->execute([$id]);
$c = $stmt->fetch();

if (!$c) {
    setFlash('error', 'Scoring criterion not found.');
    header('Location: index.php');
    exit;
}

$pdo->prepare("DELETE FROM scoring_criteria WHERE id = ?")->execute([$id]);

// Calculate new total weight for the program
$newTotal = $pdo->prepare("
    SELECT COALESCE(SUM(weight), 0) FROM scoring_criteria
    WHERE program_id = ? AND is_active = 1
");
$newTotal->execute([$c['program_id']]);
$total = (float)$newTotal->fetchColumn();

if (abs($total - 100) < 0.01) {
    setFlash('success', "Criterion \"{$c['criterion_name']}\" deleted. Program weight still balances at 100%.");
} else {
    setFlash('warning', "Criterion \"{$c['criterion_name']}\" deleted. Program \"{$c['program_name']}\" active weight is now " . number_format($total, 1) . "% — please rebalance.");
}

header('Location: index.php');
exit;