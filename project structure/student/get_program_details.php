<?php
// ============================================================
// student/get_program_details.php  –  AJAX endpoint
// Returns JSON: { program, rules, criteria, profile }
// Called by apply.php loadProgramDetails() when student selects a program
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requireRole('student');

header('Content-Type: application/json; charset=utf-8');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['error' => 'Invalid program ID']);
    exit;
}

$pdo = getDB();

// Fetch program
$stmtProg = $pdo->prepare("SELECT * FROM scholarship_programs WHERE id = ? AND status = 'open'");
$stmtProg->execute([$id]);
$program = $stmtProg->fetch(PDO::FETCH_ASSOC);

if (!$program) {
    echo json_encode(['error' => 'Program not found or not open']);
    exit;
}

// Fetch eligibility rules
$stmtR = $pdo->prepare("SELECT * FROM eligibility_rules WHERE program_id = ?");
$stmtR->execute([$id]);
$rules = $stmtR->fetchAll(PDO::FETCH_ASSOC);

// Fetch scoring criteria
$stmtC = $pdo->prepare("SELECT * FROM scoring_criteria WHERE program_id = ?");
$stmtC->execute([$id]);
$criteria = $stmtC->fetchAll(PDO::FETCH_ASSOC);

// Fetch student profile (for quick eligibility preview in sidebar)
$studentId = currentUserId();
$stmtP = $pdo->prepare("SELECT * FROM student_profiles WHERE student_id = ?");
$stmtP->execute([$studentId]);
$profile = $stmtP->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'program'  => $program,
    'rules'    => $rules,
    'criteria' => $criteria,
    'profile'  => $profile ?: null,
]);
