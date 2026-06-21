<?php
// ============================================================
// admin/eligibility_rules/delete.php
// Deletes an eligibility rule with flash feedback.
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

// Fetch for confirmation message
$stmt = $pdo->prepare("
    SELECT er.id, er.rule_type, er.operator, er.value, sp.name AS program_name
    FROM eligibility_rules er
    JOIN scholarship_programs sp ON er.program_id = sp.id
    WHERE er.id = ?
");
$stmt->execute([$id]);
$rule = $stmt->fetch();

if (!$rule) {
    setFlash('error', 'Rule not found.');
    header('Location: index.php');
    exit;
}

$pdo->prepare("DELETE FROM eligibility_rules WHERE id = ?")->execute([$id]);

setFlash('success', "Rule #{$id} ({$rule['rule_type']} {$rule['operator']} {$rule['value']}) removed from \"{$rule['program_name']}\".");
header('Location: index.php');
exit;