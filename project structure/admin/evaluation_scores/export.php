<?php
// ============================================================
// admin/evaluation_scores/export.php
// Export evaluation scores to Excel (XLS) or PDF (HTML print).
// Admin view-only — no data modification.
// ============================================================

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

$format     = in_array($_GET['format'] ?? '', ['excel','pdf']) ? $_GET['format'] : 'excel';
$appId      = (int)($_GET['app_id']      ?? 0);
$reviewerId = (int)($_GET['reviewer_id'] ?? 0);

// ── Filter params from index page ─────────────────────────────
$search        = trim($_GET['search']      ?? '');
$filterProgram = (int)($_GET['program_id'] ?? 0);

// ── Build query ───────────────────────────────────────────────
$where  = [];
$params = [];

if ($appId > 0) {
    $where[]  = "es.application_id = ?";
    $params[] = $appId;
    if ($reviewerId > 0) {
        $where[]  = "es.council_id = ?";
        $params[] = $reviewerId;
    }
} else {
    if ($search !== '') {
        $like     = "%$search%";
        $where[]  = "(su.full_name LIKE ? OR sp.name LIKE ?)";
        $params[] = $like; $params[] = $like;
    }
    if ($filterProgram > 0) {
        $where[]  = "a.program_id = ?";
        $params[] = $filterProgram;
    }
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("
    SELECT
        es.id, es.score, es.note, es.scored_at, es.verification_status,
        a.id          AS app_id,
        su.full_name  AS student_name,
        su.student_code,
        sp.name       AS program_name,
        sc.criterion_name,
        sc.weight,
        sc.max_score  AS criterion_max,
        u.full_name   AS reviewer_name,
        ROUND(es.score * sc.weight / 100, 2) AS weighted_score
    FROM evaluation_scores es
    JOIN applications a        ON es.application_id = a.id
    JOIN users su              ON a.student_id = su.id
    JOIN scholarship_programs sp ON a.program_id = sp.id
    JOIN scoring_criteria sc   ON es.criteria_id = sc.id
    JOIN users u               ON es.council_id  = u.id
    $whereSql
    ORDER BY a.id ASC, sc.weight DESC
    LIMIT 3000
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$exportDate = date('d-M-Y_H-i');
$filename   = "evaluation_scores_{$exportDate}";

// ──────────────────────────────────────────────────────────────
// EXCEL EXPORT (TSV/XLS)
// ──────────────────────────────────────────────────────────────
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF"; // BOM

    $cols = [
        'Score ID','App #','Student Name','Student ID','Program',
        'Criterion','Weight (%)','Max Score','Reviewer Score','Weighted Score',
        'Reviewer','Evidence Status','Note','Scored At',
    ];
    echo implode("\t", $cols) . "\n";

    foreach ($rows as $r) {
        $verifLabel = match($r['verification_status'] ?? 'verified') {
            'need_clarification' => 'Need Clarification',
            'rejected_evidence'  => 'Rejected Evidence',
            default              => 'Verified',
        };
        $line = [
            '#'.$r['id'],
            '#'.$r['app_id'],
            $r['student_name'],
            $r['student_code'] ?: '',
            $r['program_name'],
            $r['criterion_name'],
            $r['weight'],
            $r['criterion_max'],
            $r['score'],
            $r['weighted_score'],
            $r['reviewer_name'],
            $verifLabel,
            str_replace(["\t","\n","\r"], ' ', $r['note'] ?? ''),
            $r['scored_at'] ? date('d/m/Y H:i', strtotime($r['scored_at'])) : '',
        ];
        echo implode("\t", $line) . "\n";
    }
    exit;
}

// ──────────────────────────────────────────────────────────────
// PDF EXPORT (HTML print-to-PDF)
// ──────────────────────────────────────────────────────────────
// Group by application
$grouped = [];
foreach ($rows as $r) {
    $grouped[$r['app_id']]['info'] = [
        'app_id'       => $r['app_id'],
        'student_name' => $r['student_name'],
        'student_code' => $r['student_code'],
        'program_name' => $r['program_name'],
    ];
    $grouped[$r['app_id']]['rows'][] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Evaluation Scores – <?= date('d M Y') ?></title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11.5px; color: #1e293b; padding: 20px; }
    h1 { font-size: 19px; font-weight: 800; margin-bottom: 3px; }
    .subtitle { color: #64748b; font-size: 11.5px; margin-bottom: 20px; }
    .app-header { background: #f1f5f9; border-radius: 6px; padding: 8px 12px; margin: 14px 0 6px;
                  font-weight: 700; font-size: 13px; page-break-inside: avoid; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th { background: #1e3a5f; color: #fff; font-size: 10px; text-transform: uppercase;
         letter-spacing: .04em; padding: 7px 8px; text-align: left; }
    td { padding: 7px 8px; border-bottom: 1px solid #e2e8f0; font-size: 11.5px; vertical-align: top; }
    tr:nth-child(even) td { background: #f8fafc; }
    .total-row td { background: #e0f2fe; font-weight: 700; }
    .badge-v  { color: #15803d; background: #dcfce7; padding: 1px 6px; border-radius: 99px; font-size:10px; font-weight:700; }
    .badge-nc { color: #92400e; background: #fef3c7; padding: 1px 6px; border-radius: 99px; font-size:10px; font-weight:700; }
    .badge-re { color: #b91c1c; background: #fee2e2; padding: 1px 6px; border-radius: 99px; font-size:10px; font-weight:700; }
    .footer { margin-top: 20px; font-size: 10.5px; color: #94a3b8; text-align: center; }
    @media print {
      body { padding: 0; }
      @page { margin: 12mm; size: A4 landscape; }
      .app-header { page-break-before: auto; }
    }
  </style>
</head>
<body onload="window.print()">
  <h1>Evaluation Scores Report</h1>
  <div class="subtitle">
    Generated on <?= date('d M Y, H:i') ?> &nbsp;·&nbsp;
    <?= count($grouped) ?> application<?= count($grouped) !== 1 ? 's' : '' ?>,
    <?= count($rows) ?> score record<?= count($rows) !== 1 ? 's' : '' ?>
  </div>

  <?php foreach ($grouped as $appRow): ?>
    <?php
    $info = $appRow['info'];
    $rs   = $appRow['rows'];
    $total = array_sum(array_column($rs, 'weighted_score'));
    ?>
    <div class="app-header">
      App #<?= htmlspecialchars($info['app_id']) ?> &nbsp;·&nbsp;
      <?= htmlspecialchars($info['student_name']) ?>
      <?= $info['student_code'] ? '(' . htmlspecialchars($info['student_code']) . ')' : '' ?>
      &nbsp;·&nbsp; <?= htmlspecialchars($info['program_name']) ?>
    </div>
    <table>
      <thead>
        <tr>
          <th>Criterion</th><th>Weight</th><th>Max</th>
          <th>Score</th><th>Weighted</th><th>Reviewer</th><th>Evidence</th><th>Note</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rs as $r):
          $vs = $r['verification_status'] ?? 'verified';
          $vcls = match($vs) { 'need_clarification'=>'badge-nc', 'rejected_evidence'=>'badge-re', default=>'badge-v' };
          $vl   = match($vs) { 'need_clarification'=>'Need Clarif.', 'rejected_evidence'=>'Rejected', default=>'Verified' };
        ?>
          <tr>
            <td><?= htmlspecialchars($r['criterion_name']) ?></td>
            <td><?= htmlspecialchars($r['weight']) ?>%</td>
            <td><?= htmlspecialchars($r['criterion_max']) ?></td>
            <td><strong><?= number_format((float)$r['score'], 2) ?></strong></td>
            <td><?= number_format((float)$r['weighted_score'], 2) ?></td>
            <td><?= htmlspecialchars($r['reviewer_name']) ?></td>
            <td><span class="<?= $vcls ?>"><?= $vl ?></span></td>
            <td style="max-width:120px;"><?= htmlspecialchars($r['note'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        <tr class="total-row">
          <td colspan="4" style="text-align:right;">Total Weighted Score:</td>
          <td><?= number_format($total, 2) ?></td>
          <td colspan="3"></td>
        </tr>
      </tbody>
    </table>
  <?php endforeach; ?>

  <div class="footer">
    Scholarship Management System &nbsp;·&nbsp; Evaluation Scores Export &nbsp;·&nbsp;
    <?= date('d M Y H:i') ?>
  </div>
</body>
</html>
<?php exit; ?>
