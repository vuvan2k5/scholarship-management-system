<?php
// ============================================================
// admin/eligibility_rules/toggle.php
// Toggle is_active between 1 and 0.
// Redirects back to referrer (index or program view).
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

$stmt = $pdo->prepare("SELECT id, rule_type, is_active FROM eligibility_rules WHERE id = ?");
$stmt->execute([$id]);
$rule = $stmt->fetch();

if (!$rule) {
    setFlash('error', 'Rule not found.');
    header('Location: index.php');
    exit;
}

$newActive = $rule['is_active'] ? 0 : 1;
$pdo->prepare("
    UPDATE eligibility_rules
    SET is_active = ?, updated_by = ?, updated_at = NOW()
    WHERE id = ?
")->execute([$newActive, $adminId, $id]);

$statusWord = $newActive ? 'activated' : 'deactivated';
setFlash('success', "Rule #{$id} ({$rule['rule_type']}) has been $statusWord.");

// Redirect back to where the request came from
$back = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . $back);
exit;
