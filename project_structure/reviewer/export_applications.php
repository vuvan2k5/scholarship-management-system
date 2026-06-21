<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

requireLogin();
requireRole('council', 'reviewer');

$pdo = getDB();

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$programId = trim($_GET['program_id'] ?? '');

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(u.full_name LIKE ? OR u.email LIKE ? OR a.id = ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = is_numeric($q) ? (int)$q : 0;
}

if ($status !== '') {
    $where[] = "a.status = ?";
    $params[] = $status;
}

if ($programId !== '') {
    $where[] = "a.program_id = ?";
    $params[] = $programId;
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
    SELECT
        a.id,
        u.full_name,
        u.email,
        sp.name AS program_name,
        a.status,
        a.submitted_at
    FROM applications a
    JOIN users u ON a.student_id = u.id
    JOIN scholarship_programs sp ON a.program_id = sp.id
    $whereSql
    ORDER BY a.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=applications_export.csv');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'ID',
    'Student Name',
    'Email',
    'Scholarship',
    'Status',
    'Submitted At'
]);

foreach ($applications as $app) {
    fputcsv($output, [
        $app['id'],
        $app['full_name'],
        $app['email'],
        $app['program_name'],
        $app['status'],
        $app['submitted_at']
    ]);
}

fclose($output);
exit;