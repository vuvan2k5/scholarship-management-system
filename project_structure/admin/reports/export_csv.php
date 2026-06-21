<?php
// ============================================================
// admin/reports/export_csv.php
// ============================================================

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

$program_id = $_GET['program_id'] ?? '';
$report_type = $_GET['report_type'] ?? '';

if (!$program_id || !$report_type) {
    die("Invalid request parameters.");
}

// Fetch program details
$stmt = $pdo->prepare("SELECT * FROM scholarship_programs WHERE id = ?");
$stmt->execute([$program_id]);
$program = $stmt->fetch();

if (!$program) {
    die("Program not found.");
}

$filename = "{$report_type}_report_program_{$program_id}_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
// Add BOM to fix UTF-8 in Excel
fputs($output, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));

if ($report_type === 'ranking') {
    fputcsv($output, ['Rank', 'Student Code', 'Full Name', 'Total Score', 'Status']);
    
    $stmt = $pdo->prepare("
        SELECT 
            r.`rank`, 
            u.student_code, 
            u.full_name, 
            r.total_score, 
            r.recommended 
        FROM ranking_results r
        JOIN applications a ON r.application_id = a.id
        JOIN users u ON a.student_id = u.id
        WHERE a.program_id = ?
        ORDER BY r.`rank` ASC
    ");
    $stmt->execute([$program_id]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = $row['recommended'] ? 'Recommended' : 'Waiting';
        fputcsv($output, [
            $row['rank'],
            $row['student_code'],
            $row['full_name'],
            $row['total_score'],
            $status
        ]);
    }

} elseif ($report_type === 'disbursement') {
    fputcsv($output, ['Rank', 'Student Code', 'Full Name', 'Amount (VND)', 'Status']);
    
    $stmt = $pdo->prepare("
        SELECT 
            r.`rank`, 
            u.student_code, 
            u.full_name, 
            d.amount, 
            d.status as disb_status
        FROM ranking_results r
        JOIN applications a ON r.application_id = a.id
        JOIN users u ON a.student_id = u.id
        LEFT JOIN disbursements d ON a.id = d.application_id
        WHERE a.program_id = ? AND r.recommended = 1
        ORDER BY r.`rank` ASC
    ");
    $stmt->execute([$program_id]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $amount = $row['amount'] ?? ($program['budget'] / $program['slots']);
        $status = ucfirst($row['disb_status'] ?? 'Pending');
        fputcsv($output, [
            $row['rank'],
            $row['student_code'],
            $row['full_name'],
            $amount,
            $status
        ]);
    }

} elseif ($report_type === 'summary') {
    fputcsv($output, ['Student Code', 'Full Name', 'Application Status', 'Eligibility', 'Reason']);
    
    $stmt = $pdo->prepare("
        SELECT 
            u.student_code, 
            u.full_name, 
            a.status as app_status, 
            e.is_passed, 
            e.reason 
        FROM applications a
        JOIN users u ON a.student_id = u.id
        LEFT JOIN eligibility_results e ON a.id = e.application_id
        WHERE a.program_id = ?
        ORDER BY a.id ASC
    ");
    $stmt->execute([$program_id]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['is_passed'] === 1) $eligibility = 'Passed';
        elseif ($row['is_passed'] === 0) $eligibility = 'Failed';
        else $eligibility = 'Pending';
        
        fputcsv($output, [
            $row['student_code'],
            $row['full_name'],
            ucfirst($row['app_status']),
            $eligibility,
            $row['reason'] ?? '-'
        ]);
    }
}

// Log the report generation
$stmtLog = $pdo->prepare("INSERT INTO reports (report_type, generated_by, program_id) VALUES (?, ?, ?)");
$stmtLog->execute([$report_type, $_SESSION['user_id'], $program_id]);

fclose($output);
exit;
