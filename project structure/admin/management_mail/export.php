<?php
// ============================================================
// admin/management_mail/export.php
// Export email history log to Excel (XLS) or PDF.
// Filters: status, program_id, search (same as index.php)
// ============================================================

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo    = getDB();
$format = in_array($_GET['format'] ?? '', ['excel', 'pdf']) ? $_GET['format'] : 'excel';

// ── Filters ───────────────────────────────────────────────────
$filterStatus  = trim($_GET['status']     ?? '');
$filterProgram = (int)($_GET['program_id'] ?? 0);
$searchLog     = trim($_GET['search']     ?? '');

$where  = [];
$params = [];

if ($filterStatus)  { $where[] = "ml.status = ?";     $params[] = $filterStatus; }
if ($filterProgram) { $where[] = "ml.program_id = ?"; $params[] = $filterProgram; }
if ($searchLog) {
    $like        = "%$searchLog%";
    $where[]     = "(u.full_name LIKE ? OR ml.subject LIKE ?)";
    $params[]    = $like;
    $params[]    = $like;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT
        ml.id,
        ml.subject,
        ml.status,
        ml.has_attachment,
        ml.attachment_type,
        ml.error_message,
        ml.sent_at,
        ml.created_at,
        u.full_name      AS recipient_name,
        u.student_code,
        u.email          AS recipient_email,
        sp.name          AS program_name,
        mt.name          AS template_name,
        mt.template_type,
        adm.full_name    AS sent_by_name,
        ml.body_html
    FROM mail_log ml
    JOIN users u                          ON ml.recipient_id = u.id
    LEFT JOIN scholarship_programs sp     ON ml.program_id   = sp.id
    LEFT JOIN mail_templates mt           ON ml.template_id  = mt.id
    LEFT JOIN users adm                   ON ml.sent_by      = adm.id
    $whereSql
    ORDER BY ml.created_at DESC
    LIMIT 5000
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$exportDate = date('d-M-Y_H-i');
$filename   = "mail_history_{$exportDate}";

// ──────────────────────────────────────────────────────────────
// EXCEL (TSV → .xls)
// ──────────────────────────────────────────────────────────────
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel

    $cols = [
        'ID', 'Recipient Name', 'Student ID', 'Email',
        'Program', 'Template', 'Template Type',
        'Subject', 'Status', 'Attachment',
        'Sent By', 'Sent At', 'Error',
    ];
    echo implode("\t", $cols) . "\n";

    foreach ($rows as $r) {
        $attachLabel = match($r['attachment_type']) {
            'certificate' => 'Certificate PDF',
            'document'    => 'Supporting Docs',
            default       => 'None',
        };
        $typeLabel = match($r['template_type'] ?? '') {
            'award_notification'     => 'Award Notification',
            'rejection_notification' => 'Rejection Notification',
            'document_request'       => 'Document Request',
            'interview_invitation'   => 'Interview Invitation',
            default                  => 'Custom',
        };
        $line = [
            '#' . $r['id'],
            $r['recipient_name'],
            $r['student_code']  ?? '',
            $r['recipient_email'] ?? '',
            $r['program_name']  ?? '',
            $r['template_name'] ?? 'Custom',
            $typeLabel,
            $r['subject'],
            ucfirst($r['status']),
            $attachLabel,
            $r['sent_by_name']  ?? '—',
            $r['sent_at']       ? date('d/m/Y H:i', strtotime($r['sent_at'])) : '—',
            $r['error_message'] ? strip_tags($r['error_message']) : '',
        ];
        echo implode("\t", array_map(
            fn($v) => str_replace(["\t", "\n", "\r"], ' ', (string)$v),
            $line
        )) . "\n";
    }
    exit;
}

// ──────────────────────────────────────────────────────────────
// PDF (HTML → print)
// ──────────────────────────────────────────────────────────────
$statCounts = [
    'total'   => count($rows),
    'sent'    => count(array_filter($rows, fn($r) => $r['status'] === 'sent')),
    'failed'  => count(array_filter($rows, fn($r) => $r['status'] === 'failed')),
    'pending' => count(array_filter($rows, fn($r) => $r['status'] === 'pending')),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Management Mail History – <?= date('d M Y') ?></title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body   { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11.5px;
             color: #1e293b; padding: 24px; }
    h1     { font-size: 20px; font-weight: 800; margin-bottom: 4px; }
    .sub   { color: #64748b; font-size: 12px; margin-bottom: 18px; }

    /* Summary bar */
    .stats { display: flex; gap: 14px; margin-bottom: 18px; flex-wrap: wrap; }
    .stat  { background: #f1f5f9; border-radius: 8px; padding: 8px 16px; text-align: center; }
    .stat b{ display: block; font-size: 20px; font-weight: 800; }
    .stat s{ font-size: 10.5px; color: #64748b; }

    table  { width: 100%; border-collapse: collapse; }
    th     { background: #1e3a5f; color: #fff; font-size: 10px;
             text-transform: uppercase; letter-spacing: .04em;
             padding: 7px 8px; text-align: left; }
    td     { padding: 7px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    tr:nth-child(even) td { background: #f8fafc; }

    .sent    { color: #15803d; font-weight: 700; }
    .failed  { color: #b91c1c; font-weight: 700; }
    .pending { color: #92400e; font-weight: 700; }
    .attach  { color: #2563eb; font-size: 10px; }

    .footer  { margin-top: 20px; font-size: 10.5px; color: #94a3b8; text-align: center; }
    @media print {
      body { padding: 0; }
      @page { margin: 12mm; size: A4 landscape; }
    }
  </style>
</head>
<body onload="window.print()">
  <h1>Management Mail — History Report</h1>
  <div class="sub">
    Generated <?= date('d M Y, H:i') ?>
    <?= $filterStatus  ? " · Status: " . ucfirst($filterStatus) : '' ?>
    <?= $filterProgram ? " · Program filter applied" : '' ?>
    <?= $searchLog     ? " · Search: \"" . htmlspecialchars($searchLog) . "\"" : '' ?>
  </div>

  <!-- Summary -->
  <div class="stats">
    <?php foreach ([
        ['Total',   $statCounts['total'],   '#2563eb'],
        ['Sent',    $statCounts['sent'],    '#15803d'],
        ['Failed',  $statCounts['failed'],  '#b91c1c'],
        ['Pending', $statCounts['pending'], '#92400e'],
    ] as [$lbl, $val, $c]): ?>
      <div class="stat">
        <b style="color:<?= $c ?>"><?= $val ?></b>
        <s><?= $lbl ?></s>
      </div>
    <?php endforeach; ?>
  </div>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Recipient</th>
        <th>Program</th>
        <th>Subject</th>
        <th>Template</th>
        <th>Status</th>
        <th>Attachment</th>
        <th>Sent By</th>
        <th>Sent At</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="9" style="text-align:center;padding:20px;color:#94a3b8;">No records found.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r):
          $cls = match($r['status']) {
              'sent'    => 'sent',
              'failed'  => 'failed',
              default   => 'pending',
          };
          $attachLabel = match($r['attachment_type']) {
              'certificate' => '📎 Certificate',
              'document'    => '📎 Document',
              default       => '—',
          };
        ?>
          <tr>
            <td style="color:#94a3b8;"><?= $r['id'] ?></td>
            <td>
              <strong><?= htmlspecialchars($r['recipient_name']) ?></strong>
              <?php if ($r['student_code']): ?>
                <br><span style="color:#94a3b8;font-size:10px;"><?= htmlspecialchars($r['student_code']) ?></span>
              <?php endif; ?>
            </td>
            <td style="font-size:11px;"><?= htmlspecialchars($r['program_name'] ?? '—') ?></td>
            <td style="max-width:180px;"><?= htmlspecialchars($r['subject']) ?></td>
            <td style="font-size:10.5px;color:#64748b;">
              <?= htmlspecialchars($r['template_name'] ?? 'Custom') ?>
            </td>
            <td><span class="<?= $cls ?>"><?= ucfirst($r['status']) ?></span>
              <?php if ($r['error_message']): ?>
                <br><span style="color:#b91c1c;font-size:9.5px;">
                  <?= htmlspecialchars(substr(strip_tags($r['error_message']), 0, 40)) ?>…
                </span>
              <?php endif; ?>
            </td>
            <td class="attach"><?= $attachLabel ?></td>
            <td style="font-size:10.5px;"><?= htmlspecialchars($r['sent_by_name'] ?? '—') ?></td>
            <td style="white-space:nowrap;font-size:10.5px;">
              <?= $r['sent_at'] ? date('d M Y H:i', strtotime($r['sent_at'])) : '—' ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="footer">
    Scholarship Management System &nbsp;·&nbsp;
    Management Mail Export &nbsp;·&nbsp;
    <?= date('d M Y H:i') ?>
  </div>
</body>
</html>
<?php exit; ?>
