<?php
// ============================================================
// admin/scholarship_programs/suspend.php
// Toggle program status: open ↔ suspended
// Supports ?reopen=1 to restore to 'open'
// ============================================================
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo    = getDB();
$id     = isset($_GET['id'])     ? (int)$_GET['id']     : 0;
$reopen = isset($_GET['reopen']) ? (bool)$_GET['reopen'] : false;

if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT name, status FROM scholarship_programs WHERE id = ?");
$stmt->execute([$id]);
$prog = $stmt->fetch();

if (!$prog) {
    setFlash('error', 'Program not found.');
    header('Location: index.php');
    exit;
}

if ($reopen) {
    $newStatus = 'open';
    $msg       = "Program \"{$prog['name']}\" has been re-opened.";
} else {
    $newStatus = 'suspended';
    $msg       = "Program \"{$prog['name']}\" has been suspended.";
}

$pdo->prepare("UPDATE scholarship_programs SET status = ? WHERE id = ?")
    ->execute([$newStatus, $id]);

setFlash('success', $msg);

// Redirect back to the page that triggered the action (index or view)
$back = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . $back);
exit;
