<?php
// ============================================================
// admin/eligibility_results/export.php
// Export eligibility results to Excel (CSV) or PDF (HTML print).
// Applies the same filters as index.php.
// Admin view-only: no data modification.
// ============================================================

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

$format  = in_array($_GET['format'] ?? '', ['excel','pdf']) ? $_GET['format'] : 'excel';

// ── Single record export (from view.php) ─────────────────────
$singleId = (int)($_GET['id'] ?? 0);

// ── Filters (same as index.php) ──────────────────────────────
$search        = trim($_GET['search']       ?? '');
$filterResult  = trim($_GET['result']       ?? '');
$filterVerif   = trim($_GET['verif_status'] ?? '');
$filterProgram = (int)($_GET['program_id']  ?? 0);
$filterDate    = trim($_GET['eval_date']    ?? '');

// ── Build WHERE ───────────────────────────────────────────────
$where  = [];
$params = [];

if ($singleId > 0) {
    $where[]  = "er.id = ?";
    $params[] = $singleId;
} else {
    if ($search !== '') {
        $like = "%$search%";
        $where[]  = "(u.full_name LIKE ? OR u.student_code LIKE ? OR sp.name LIKE ?)";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($filterResult === 'pass') $where[] = "er.is_passed = 1";
    if ($filterResult === 'fail') $where[] = "er.is_passed = 0";
    if ($filterVerif !== '')     { $where[] = "er.reviewer_verification_status = ?"; $params[] = $filterVerif; }
    if ($filterProgram > 0)      { $where[] = "a.program_id = ?"; $params[] = $filterProgram; }
    if ($filterDate !== '')      { $where[] = "DATE(er.checked_at) = ?"; $params[] = $filterDate; }
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("
    SELECT
        er.id,
        er.is_passed,
        er.reason,
        er.rule_trace,
        er.checked_at,
        er.reviewer_verification_status,
        a.id          AS app_id,
        u.full_name   AS student_name,
        u.student_code,
        sp.name       AS program_name
    FROM eligibility_results er
    JOIN applications a  ON er.application_id = a.id
    JOIN users u         ON a.student_id = u.id
    JOIN scholarship_programs sp ON a.program_id = sp.id
    $whereSql
    ORDER BY er.checked_at DESC, er.id DESC
    LIMIT 2000
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$exportDate = date('d-M-Y_H-i');
$filename   = "eligibility_results_{$exportDate}";

// ──────────────────────────────────────────────────────────────
// EXCEL EXPORT  (CSV → .xls extension for direct Excel open)
// ──────────────────────────────────────────────────────────────
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');

    // BOM for Excel UTF-8
    echo "\xEF\xBB\xBF";

    // Header row
    $cols = [
        'Result ID', 'App ID', 'Student Name', 'Student ID',
        'Program', 'Eligibility', 'Fail Reasons', 'Reviewer Verification', 'Evaluated At',
    ];
    echo implode("\t", $cols) . "\n";

    foreach ($rows as $r) {
        $failText = '';
        if (!$r['is_passed'] && str_starts_with($r['reason'] ?? '', 'Failed criteria:')) {
            $txt      = trim(substr($r['reason'], strlen('Failed criteria:')));
            $failText = implode(' | ', array_filter(array_map('trim', explode(';', $txt))));
        }

        $verifLabel = match($r['reviewer_verification_status'] ?? 'pending') {
            'verified' => 'Verified',
            'rejected' => 'Rejected',
            default    => 'Pending',
        };

        $cols = [
            '#' . $r['id'],
            '#' . $r['app_id'],
            $r['student_name'],
            $r['student_code'] ?: '',
            $r['program_name'],
            $r['is_passed'] ? 'PASS' : 'FAIL',
            $failText,
            $verifLabel,
            $r['checked_at'] ? date('d/m/Y H:i', strtotime($r['checked_at'])) : '',
        ];
        echo implode("\t", array_map(fn($v) => str_replace(["\t","\n","\r"], ' ', $v), $cols)) . "\n";
    }
    exit;
}

// ──────────────────────────────────────────────────────────────
// PDF EXPORT  (HTML rendered → browser print-to-PDF)
// ──────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Eligibility Results – <?= date('d M Y') ?></title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #1e293b; padding: 24px; }
    h1 { font-size: 20px; font-weight: 800; margin-bottom: 4px; }
    .subtitle { color: #64748b; font-size: 12px; margin-bottom: 20px; }
    .badge-pass { color: #15803d; background: #dcfce7; padding: 2px 8px; border-radius: 99px; font-weight: 700; font-size: 11px; }
    .badge-fail { color: #b91c1c; background: #fee2e2; padding: 2px 8px; border-radius: 99px; font-weight: 700; font-size: 11px; }
    .badge-verif { color: #1d4ed8; background: #dbeafe; padding: 2px 8px; border-radius: 99px; font-weight: 700; font-size: 11px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th { background: #f1f5f9; color: #475569; font-weight: 700; font-size: 10.5px;
         text-transform: uppercase; letter-spacing: .04em; padding: 8px 10px;
         border: 1px solid #e2e8f0; text-align: left; }
    td { padding: 8px 10px; border: 1px solid #e2e8f0; vertical-align: top; font-size: 11.5px; }
    tr:nth-child(even) td { background: #f8fafc; }
    .fail-list { margin: 0; padding-left: 14px; color: #b91c1c; }
    .footer { margin-top: 24px; font-size: 11px; color: #94a3b8; text-align: center; }
    @media print {
      body { padding: 0; }
      @page { margin: 15mm; size: A4 landscape; }
    }
  </style>
</head>
<body onload="window.print()">

  <h1>Eligibility Results Report</h1>
  <div class="subtitle">
    Generated on <?= date('d M Y, H:i') ?> &nbsp;·&nbsp;
    <?= count($rows) ?> record<?= count($rows) !== 1 ? 's' : '' ?>
    <?php if ($singleId > 0): ?> (Result #<?= $singleId ?>)<?php endif; ?>
  </div>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>App #</th>
        <th>Student</th>
        <th>Program</th>
        <th>Eligibility</th>
        <th>Fail Reasons</th>
        <th>Verification</th>
        <th>Evaluated At</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r):
        $failParts = [];
        if (!$r['is_passed'] && str_starts_with($r['reason'] ?? '', 'Failed criteria:')) {
            $txt = trim(substr($r['reason'], strlen('Failed criteria:')));
            $failParts = array_values(array_filter(array_map('trim', explode(';', $txt))));
        }
        $verifLabel = match($r['reviewer_verification_status'] ?? 'pending') {
            'verified' => 'Verified', 'rejected' => 'Rejected', default => 'Pending'
        };
      ?>
        <tr>
          <td>#<?= htmlspecialchars($r['id']) ?></td>
          <td>#<?= htmlspecialchars($r['app_id']) ?></td>
          <td>
            <strong><?= htmlspecialchars($r['student_name']) ?></strong><br>
            <span style="color:#64748b;"><?= htmlspecialchars($r['student_code'] ?: '') ?></span>
          </td>
          <td><?= htmlspecialchars($r['program_name']) ?></td>
          <td>
            <?php if ($r['is_passed']): ?>
              <span class="badge-pass">PASS</span>
            <?php else: ?>
              <span class="badge-fail">FAIL</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($r['is_passed']): ?>
              <span style="color:#15803d;">✓ All criteria met</span>
            <?php elseif ($failParts): ?>
              <ul class="fail-list">
                <?php foreach ($failParts as $fp) echo '<li>'.htmlspecialchars($fp).'</li>'; ?>
              </ul>
            <?php else: ?>
              <?= htmlspecialchars($r['reason'] ?? '—') ?>
            <?php endif; ?>
          </td>
          <td><span class="badge-verif"><?= htmlspecialchars($verifLabel) ?></span></td>
          <td style="white-space:nowrap;">
            <?= $r['checked_at'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($r['checked_at']))) : '—' ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="footer">
    Scholarship Management System &nbsp;·&nbsp; Eligibility Results Export &nbsp;·&nbsp;
    Page generated <?= date('d M Y H:i') ?>
  </div>
</body>
</html>
<?php exit; ?>
