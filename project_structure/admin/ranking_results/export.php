<?php
// ============================================================
// admin/ranking_results/export.php
// Export ranking results to Excel (XLS) or PDF (print-to-PDF).
// Supports single program or all programs.
// ============================================================

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo    = getDB();
$format = in_array($_GET['format'] ?? '', ['excel','pdf']) ? $_GET['format'] : 'excel';
$pid    = (int)($_GET['program_id'] ?? 0);

$where  = $pid ? "WHERE a.program_id = $pid" : '';

$rows = $pdo->query("
    SELECT
        rr.rank       AS rank_no,
        rr.total_score,
        rr.awarded,
        rr.tie_break_reason,
        rr.published,
        rr.published_at,
        rr.generated_at,
        a.id          AS app_id,
        a.submitted_at,
        a.program_id,
        u.full_name   AS student_name,
        u.student_code,
        sp.name       AS program_name,
        sp.slots,
        COALESCE(spr.gpa, 0) AS gpa,
        ac.certificate_code
    FROM ranking_results rr
    JOIN applications a         ON rr.application_id = a.id
    JOIN users u                ON a.student_id = u.id
    JOIN scholarship_programs sp ON a.program_id = sp.id
    LEFT JOIN student_profiles spr ON spr.student_id = a.student_id
    LEFT JOIN award_certificates ac ON ac.application_id = a.id
    $where
    ORDER BY sp.id ASC, rr.rank ASC
    LIMIT 2000
")->fetchAll();

$exportDate = date('d-M-Y_H-i');
$filename   = 'ranking_results_' . $exportDate;

// ──────────────────────────────────────────────────────────────
// EXCEL
// ──────────────────────────────────────────────────────────────
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF";

    $cols = [
        'Rank','App #','Student Name','Student ID',
        'Program','Slots','GPA','Total Score',
        'Award Status','Tie-Break Reason','Certificate Code',
        'Published','Generated At'
    ];
    echo implode("\t", $cols) . "\n";

    foreach ($rows as $r) {
        $awardLabel   = $r['awarded'] ? 'Awarded' : 'Not Awarded';
        $publishLabel = $r['published'] ? 'Yes' : 'No';
        $line = [
            '#'.$r['rank_no'],
            '#'.$r['app_id'],
            $r['student_name'],
            $r['student_code'] ?: '',
            $r['program_name'],
            $r['slots'],
            number_format((float)$r['gpa'], 2),
            number_format((float)$r['total_score'], 2),
            $awardLabel,
            $r['tie_break_reason'] ?: '',
            $r['certificate_code'] ?: '',
            $publishLabel,
            $r['generated_at'] ? date('d/m/Y H:i', strtotime($r['generated_at'])) : '',
        ];
        echo implode("\t", array_map(fn($v) => str_replace(["\t","\n","\r"], ' ', $v), $line)) . "\n";
    }
    exit;
}

// ──────────────────────────────────────────────────────────────
// PDF (HTML print-to-PDF)
// ──────────────────────────────────────────────────────────────
// Group by program
$grouped = [];
foreach ($rows as $r) {
    $grouped[$r['program_name']]['slots'] = $r['slots'];
    $grouped[$r['program_name']]['published'] = $r['published'];
    $grouped[$r['program_name']]['rows'][] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Ranking Results – <?= date('d M Y') ?></title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #1e293b; padding: 24px; }
    h1 { font-size: 20px; font-weight: 800; margin-bottom: 4px; }
    .subtitle { color: #64748b; font-size: 12px; margin-bottom: 20px; }
    .prog-header { background: #1e3a5f; color: #fff; padding: 9px 12px; border-radius: 6px 6px 0 0;
                   font-weight: 700; font-size: 13px; margin-top: 18px; page-break-inside: avoid; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f1f5f9; color: #475569; font-size: 10.5px; text-transform: uppercase;
         letter-spacing: .04em; padding: 7px 10px; border: 1px solid #e2e8f0; text-align: left; }
    td { padding: 7px 10px; border: 1px solid #e2e8f0; font-size: 11.5px; vertical-align: middle; }
    tr:nth-child(even) td { background: #f8fafc; }
    .awarded { background: #f0fdf4 !important; }
    .rank-badge { display: inline-block; width: 28px; height: 28px; border-radius: 50%;
                  text-align: center; line-height: 28px; font-weight: 900; font-size: 12px; }
    .rank-1 { background: #fef3c7; color: #92400e; }
    .rank-2 { background: #f1f5f9; color: #475569; }
    .rank-3 { background: #fef3c7; color: #7c3aed; }
    .rank-n { background: #e2e8f0; color: #64748b; }
    .aw-yes { color: #15803d; font-weight: 700; }
    .aw-no  { color: #b91c1c; }
    .cert   { font-family: monospace; font-size: 10px; color: #15803d; }
    .footer { margin-top: 24px; font-size: 11px; color: #94a3b8; text-align: center; }
    @media print {
      body { padding: 0; }
      @page { margin: 12mm; size: A4 landscape; }
      .prog-header { page-break-before: auto; }
    }
  </style>
</head>
<body onload="window.print()">
  <h1>Ranking Results Report</h1>
  <div class="subtitle">
    Generated on <?= date('d M Y, H:i') ?>
    <?= $pid ? ' · Program filter applied' : ' · All programs' ?>
    &nbsp;·&nbsp; <?= count($rows) ?> record<?= count($rows) !== 1 ? 's' : '' ?>
  </div>

  <?php foreach ($grouped as $progName => $pg): ?>
    <?php
    $pgAwarded = count(array_filter($pg['rows'], fn($r) => $r['awarded'] == 1));
    ?>
    <div class="prog-header">
      <?= htmlspecialchars($progName) ?>
      &nbsp;·&nbsp; <?= $pg['slots'] ?> slots
      &nbsp;·&nbsp; <?= count($pg['rows']) ?> ranked
      &nbsp;·&nbsp; <?= $pgAwarded ?> awarded
      <?= $pg['published'] ? '&nbsp;· ✓ Published' : '' ?>
    </div>
    <table>
      <thead>
        <tr>
          <th>Rank</th><th>App #</th><th>Student</th><th>GPA</th>
          <th>Score</th><th>Award</th><th>Tie-Break</th><th>Certificate</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pg['rows'] as $r): ?>
          <tr class="<?= $r['awarded'] ? 'awarded' : '' ?>">
            <td>
              <span class="rank-badge <?= $r['rank_no'] === 1 ? 'rank-1' : ($r['rank_no'] === 2 ? 'rank-2' : ($r['rank_no'] === 3 ? 'rank-3' : 'rank-n')) ?>">
                #<?= htmlspecialchars($r['rank_no']) ?>
              </span>
            </td>
            <td>#<?= htmlspecialchars($r['app_id']) ?></td>
            <td>
              <strong><?= htmlspecialchars($r['student_name']) ?></strong>
              <?php if ($r['student_code']): ?>
                <br><span style="color:#64748b;font-size:10px;"><?= htmlspecialchars($r['student_code']) ?></span>
              <?php endif; ?>
            </td>
            <td><?= number_format((float)$r['gpa'], 2) ?></td>
            <td><strong><?= number_format((float)$r['total_score'], 2) ?></strong></td>
            <td>
              <?php if ($r['awarded']): ?>
                <span class="aw-yes">✓ Awarded</span>
              <?php else: ?>
                <span class="aw-no">Not Awarded</span>
              <?php endif; ?>
            </td>
            <td style="font-size:10.5px;color:#64748b;"><?= htmlspecialchars($r['tie_break_reason'] ?? '—') ?></td>
            <td class="cert"><?= htmlspecialchars($r['certificate_code'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endforeach; ?>

  <div class="footer">
    Scholarship Management System &nbsp;·&nbsp; Ranking Results Export &nbsp;·&nbsp;
    <?= date('d M Y H:i') ?>
  </div>
</body>
</html>
<?php exit; ?>
