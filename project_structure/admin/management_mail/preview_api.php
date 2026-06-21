<?php
// ============================================================
// admin/management_mail/preview_api.php
// AJAX endpoint: returns a rendered email preview using
// real student + application data for the given application_id.
// No DB writes — read-only.
// ============================================================

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

header('Content-Type: application/json; charset=utf-8');

$pdo       = getDB();
$tplId     = (int)($_GET['template_id']    ?? 0);
$appId     = (int)($_GET['application_id'] ?? 0);
$rawSubj   = trim($_GET['subject']   ?? '');
$rawBody   = trim($_GET['body_html'] ?? '');

// ── Load template if ID given ─────────────────────────────────
if ($tplId > 0 && !$rawSubj && !$rawBody) {
    $t = $pdo->prepare("SELECT subject, body_html FROM mail_templates WHERE id = ?");
    $t->execute([$tplId]);
    $tpl     = $t->fetch();
    $rawSubj = $tpl['subject']   ?? '';
    $rawBody = $tpl['body_html'] ?? '';
}

// ── Resolve variable values ───────────────────────────────────
$vars = [
    '{{student_name}}' => 'Nguyễn Văn An',   // fallback samples
    '{{student_id}}'   => 'SV2024001',
    '{{program_name}}' => 'Excellence Scholarship',
    '{{rank}}'         => '—',
    '{{score}}'        => '—',
];

if ($appId > 0) {
    $stmt = $pdo->prepare("
        SELECT
            u.full_name,
            u.student_code,
            sp.name         AS program_name,
            rr.rank         AS rank_no,
            rr.total_score
        FROM applications a
        JOIN users u                ON a.student_id = u.id
        JOIN scholarship_programs sp ON a.program_id  = sp.id
        LEFT JOIN ranking_results rr ON rr.application_id = a.id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$appId]);
    $row = $stmt->fetch();

    if ($row) {
        $vars['{{student_name}}'] = $row['full_name']    ?? '—';
        $vars['{{student_id}}']   = $row['student_code'] ?? '—';
        $vars['{{program_name}}'] = $row['program_name'] ?? '—';
        $vars['{{rank}}']         = $row['rank_no']      ?? '—';
        $vars['{{score}}']        = $row['total_score']  ? number_format((float)$row['total_score'], 2) : '—';
    }
}

// ── Apply variable substitution ───────────────────────────────
$renderedSubject = str_replace(array_keys($vars), array_values($vars), $rawSubj);
$renderedBody    = str_replace(array_keys($vars), array_values($vars), $rawBody);

echo json_encode([
    'subject'  => $renderedSubject,
    'body_html'=> $renderedBody,
    'vars_used'=> $vars,
]);
exit;
