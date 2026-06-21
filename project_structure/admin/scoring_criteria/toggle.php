<?php
// ============================================================
// admin/scoring_criteria/toggle.php
// Toggle is_active between 1 and 0.
// Flashes the new weight total for the affected program.
// ============================================================
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo     = getDB();
$id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$adminId = currentUserId();

if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, criterion_name, is_active, program_id FROM scoring_criteria WHERE id = ?");
$stmt->execute([$id]);
$c = $stmt->fetch();

if (!$c) {
    setFlash('error', 'Criterion not found.');
    header('Location: index.php');
    exit;
}

$newActive = $c['is_active'] ? 0 : 1;
$pdo->prepare("
    UPDATE scoring_criteria
    SET is_active = ?, updated_by = ?, updated_at = NOW()
    WHERE id = ?
")->execute([$newActive, $adminId, $id]);

// Recalculate weight total
$wtStmt = $pdo->prepare("
    SELECT COALESCE(SUM(weight), 0) FROM scoring_criteria
    WHERE program_id = ? AND is_active = 1
");
$wtStmt->execute([$c['program_id']]);
$newTotal = (float)$wtStmt->fetchColumn();

$word = $newActive ? 'activated' : 'deactivated';
$balanceNote = abs($newTotal - 100) < 0.01
    ? ' Program weight is balanced at 100% ✓'
    : ' Program active weight is now ' . number_format($newTotal, 1) . '%.';

setFlash($newActive ? 'success' : 'warning',
    "Criterion \"{$c['criterion_name']}\" {$word}.{$balanceNote}");

$back = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . $back);
exit;
