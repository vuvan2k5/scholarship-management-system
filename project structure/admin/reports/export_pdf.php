<?php
// ============================================================
// admin/reports/export_pdf.php
// Professional PDF Export — auto-triggers browser print dialog
// User clicks "Export PDF" → this page opens → print dialog
// fires automatically → user clicks "Save as PDF"
// ============================================================

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

$program_id  = $_GET['program_id']  ?? '';
$report_type = $_GET['report_type'] ?? '';

if (!$program_id || !$report_type) die("Invalid request parameters.");

$stmt = $pdo->prepare("SELECT * FROM scholarship_programs WHERE id = ?");
$stmt->execute([$program_id]);
$program = $stmt->fetch();
if (!$program) die("Program not found.");

// ── Fetch data (same as view.php) ─────────────────────────────
$reportData  = [];
$reportTitle = '';

if ($report_type === 'ranking') {
    $reportTitle = 'Ranking Report';
    $stmt = $pdo->prepare("
        SELECT u.student_code, u.full_name, r.`rank`, r.total_score, r.recommended
        FROM ranking_results r
        JOIN applications a ON r.application_id = a.id
        JOIN users u ON a.student_id = u.id
        WHERE a.program_id = ?
        ORDER BY r.`rank` ASC
    ");
    $stmt->execute([$program_id]);
    $reportData = $stmt->fetchAll();

} elseif ($report_type === 'disbursement') {
    $reportTitle = 'Disbursement Report';
    $stmt = $pdo->prepare("
        SELECT u.student_code, u.full_name, r.`rank`, r.total_score, d.amount, d.status
        FROM ranking_results r
        JOIN applications a ON r.application_id = a.id
        JOIN users u ON a.student_id = u.id
        LEFT JOIN disbursements d ON a.id = d.application_id
        WHERE a.program_id = ? AND r.recommended = 1
        ORDER BY r.`rank` ASC
    ");
    $stmt->execute([$program_id]);
    $reportData = $stmt->fetchAll();

} elseif ($report_type === 'summary') {
    $reportTitle = 'Eligibility Summary';
    $stmt = $pdo->prepare("
        SELECT u.student_code, u.full_name, a.status as app_status, e.is_passed, e.reason
        FROM applications a
        JOIN users u ON a.student_id = u.id
        LEFT JOIN eligibility_results e ON a.id = e.application_id
        WHERE a.program_id = ?
        ORDER BY a.id ASC
    ");
    $stmt->execute([$program_id]);
    $reportData = $stmt->fetchAll();

} else {
    die("Unknown report type.");
}

// ── Audit log ─────────────────────────────────────────────────
$pdo->prepare("INSERT INTO reports (report_type, generated_by, program_id) VALUES (?, ?, ?)")
    ->execute([$report_type, $_SESSION['user_id'], $program_id]);

$generatedBy = e($_SESSION['user_name'] ?? 'Admin');
$generatedAt = date('d/m/Y H:i');
$totalRows   = count($reportData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($reportTitle) ?> — <?= e($program['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Crimson+Pro:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <style>
        /* ── Reset & Base ─────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Inter', sans-serif;
            font-size: 12pt;
            color: #1a1a2e;
            background: #f0f0f0;
            padding: 20px;
        }

        /* ── Page wrapper (simulates A4 paper) ───────────── */
        .pdf-page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 4px 30px rgba(0,0,0,0.18);
            padding: 18mm 16mm 16mm;
            position: relative;
        }

        /* ── Screen-only toolbar ─────────────────────────── */
        .toolbar {
            width: 210mm;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .toolbar button, .toolbar a {
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
            font-family: 'Inter', sans-serif;
        }
        .btn-pdf  { background: #dc2626; color: #fff; }
        .btn-back { background: #e2e8f0; color: #475569; }
        .btn-pdf:hover  { background: #b91c1c; }
        .btn-back:hover { background: #cbd5e1; }
        .toolbar-note { font-size: 12px; color: #64748b; margin-left: auto; }

        /* ── Header Band ─────────────────────────────────── */
        .pdf-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);
            color: #fff;
            padding: 20px 24px;
            margin: -18mm -16mm 0;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .pdf-header-seal {
            width: 60px; height: 60px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.5);
            display: flex; align-items: center; justify-content: center;
            font-size: 28px;
            flex-shrink: 0;
        }
        .pdf-header-text { flex: 1; }
        .pdf-header-inst {
            font-size: 10pt;
            letter-spacing: 2px;
            text-transform: uppercase;
            opacity: 0.8;
            font-weight: 500;
            margin-bottom: 4px;
        }
        .pdf-header-title {
            font-size: 20pt;
            font-weight: 700;
            line-height: 1.1;
            letter-spacing: -0.3px;
        }
        .pdf-header-sub {
            font-size: 11pt;
            opacity: 0.85;
            margin-top: 2px;
            font-weight: 400;
        }
        .pdf-header-date {
            text-align: right;
            font-size: 10pt;
            opacity: 0.8;
            line-height: 1.6;
        }

        /* ── Meta info band ──────────────────────────────── */
        .pdf-meta {
            display: flex;
            justify-content: space-between;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 18px;
            margin-bottom: 22px;
            font-size: 10.5pt;
        }
        .pdf-meta dt { font-weight: 600; color: #475569; font-size: 9pt; text-transform: uppercase; letter-spacing: 0.5px; }
        .pdf-meta dd { color: #1e293b; font-weight: 500; margin-bottom: 4px; }
        .pdf-meta dl { margin: 0; }

        /* ── Table ───────────────────────────────────────── */
        .pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5pt;
            margin-bottom: 24px;
        }
        .pdf-table thead tr {
            background: #1e3a5f;
            color: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .pdf-table thead th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 9.5pt;
            letter-spacing: 0.3px;
        }
        .pdf-table tbody tr { border-bottom: 1px solid #e2e8f0; }
        .pdf-table tbody tr:nth-child(even) {
            background: #f8fafc;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .pdf-table tbody tr:hover { background: #eff6ff; }
        .pdf-table td {
            padding: 9px 12px;
            vertical-align: middle;
        }
        .pdf-table tfoot td {
            padding: 10px 12px;
            font-weight: 600;
            background: #f1f5f9;
            font-size: 10pt;
            border-top: 2px solid #1e3a5f;
        }

        /* ── Status Pills ─────────────────────────────────── */
        .pill {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 9pt;
            font-weight: 600;
        }
        .pill-green  { background: #d1fae5; color: #065f46; }
        .pill-yellow { background: #fef3c7; color: #92400e; }
        .pill-red    { background: #fee2e2; color: #991b1b; }
        .pill-blue   { background: #dbeafe; color: #1e40af; }
        .pill-gray   { background: #f1f5f9; color: #475569; }
        
        .rank-badge {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px;
            border-radius: 50%;
            background: #1e3a5f; color: #fff;
            font-weight: 700; font-size: 11pt;
        }
        .rank-badge.top { background: linear-gradient(135deg, #d97706, #f59e0b); }

        /* ── Signature Row ───────────────────────────────── */
        .pdf-signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
        }
        .sig-block { text-align: center; width: 180px; }
        .sig-line  { border-top: 1.5px solid #1e3a5f; padding-top: 8px; margin-top: 50px; }
        .sig-name  { font-weight: 700; font-size: 10pt; color: #1e3a5f; }
        .sig-title { font-size: 9pt; color: #64748b; margin-top: 3px; }

        /* ── Footer ─────────────────────────────────────── */
        .pdf-footer {
            position: absolute;
            bottom: 10mm;
            left: 16mm; right: 16mm;
            display: flex;
            justify-content: space-between;
            font-size: 8.5pt;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
        }

        /* ── No-data ─────────────────────────────────────── */
        .no-data {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
            font-style: italic;
            border: 2px dashed #e2e8f0;
            border-radius: 8px;
        }

        /* ══════════════════════════════════════════════════
           PRINT MEDIA — this is what the PDF looks like
           ══════════════════════════════════════════════════ */
        @media print {
            body { background: none; padding: 0; }
            .toolbar { display: none !important; }
            .pdf-page {
                width: 100%;
                min-height: auto;
                margin: 0;
                box-shadow: none;
                padding: 14mm 12mm 12mm;
            }
            .pdf-header {
                margin: -14mm -12mm 20px;
            }
            .pdf-table tbody tr:nth-child(even) { background: #f8fafc !important; }
            .pdf-table thead tr { background: #1e3a5f !important; }
        }
        @page {
            size: A4 portrait;
            margin: 0;
        }
    </style>
</head>
<body>

<!-- ── Screen toolbar (hidden on print) ───────────────────── -->
<div class="toolbar">
    <button class="btn-pdf" onclick="window.print()">
        📄 Save as PDF / Print
    </button>
    <a href="index.php" class="btn-back">← Back</a>
    <span class="toolbar-note">
        💡 In the print dialog, select <strong>Destination → Save as PDF</strong>
    </span>
</div>

<div class="pdf-page">

    <!-- ── Header band ──────────────────────────────────── -->
    <div class="pdf-header">
        <div class="pdf-header-seal">🎓</div>
        <div class="pdf-header-text">
            <div class="pdf-header-inst">Scholarship Management System</div>
            <div class="pdf-header-title"><?= e($reportTitle) ?></div>
            <div class="pdf-header-sub"><?= e($program['name']) ?></div>
        </div>
        <div class="pdf-header-date">
            Generated: <?= $generatedAt ?><br>
            By: <?= $generatedBy ?>
        </div>
    </div>

    <!-- ── Meta band ────────────────────────────────────── -->
    <div class="pdf-meta">
        <dl>
            <dt>Program</dt>
            <dd><?= e($program['name']) ?></dd>
        </dl>
        <dl>
            <dt>Budget</dt>
            <dd><?= number_format($program['budget'], 0, ',', '.') ?> VND</dd>
        </dl>
        <dl>
            <dt>Slots</dt>
            <dd><?= e($program['slots']) ?></dd>
        </dl>
        <dl>
            <dt>Total Records</dt>
            <dd><?= $totalRows ?></dd>
        </dl>
        <dl>
            <dt>Report Type</dt>
            <dd><?= ucfirst($report_type) ?></dd>
        </dl>
    </div>

    <!-- ── Data table ───────────────────────────────────── -->
    <?php if (empty($reportData)): ?>
        <div class="no-data">No data available for this report.</div>
    <?php else: ?>

    <table class="pdf-table">

        <?php if ($report_type === 'ranking'): ?>
        <thead>
            <tr>
                <th width="60">Rank</th>
                <th>Student Code</th>
                <th>Full Name</th>
                <th>Total Score</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reportData as $row): ?>
            <tr>
                <td class="text-center">
                    <span class="rank-badge <?= $row['rank'] <= 3 ? 'top' : '' ?>">
                        <?= e($row['rank']) ?>
                    </span>
                </td>
                <td><?= e($row['student_code']) ?></td>
                <td><strong><?= e($row['full_name']) ?></strong></td>
                <td><?= number_format((float)$row['total_score'], 2) ?></td>
                <td>
                    <?php if ($row['recommended']): ?>
                        <span class="pill pill-green">✓ Recommended</span>
                    <?php else: ?>
                        <span class="pill pill-yellow">⏳ Waiting</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Total: <?= $totalRows ?> candidates</td>
                <td>Avg: <?= number_format(array_sum(array_column($reportData, 'total_score')) / max(1, $totalRows), 2) ?></td>
                <td><?= count(array_filter($reportData, fn($r) => $r['recommended'])) ?> recommended</td>
            </tr>
        </tfoot>

        <?php elseif ($report_type === 'disbursement'): ?>
        <thead>
            <tr>
                <th width="60">Rank</th>
                <th>Student Code</th>
                <th>Full Name</th>
                <th>Amount (VND)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reportData as $row):
                $amount = $row['amount'] ?? ($program['budget'] / max(1, $program['slots']));
            ?>
            <tr>
                <td class="text-center">
                    <span class="rank-badge top"><?= e($row['rank']) ?></span>
                </td>
                <td><?= e($row['student_code']) ?></td>
                <td><strong><?= e($row['full_name']) ?></strong></td>
                <td><?= number_format((float)$amount, 0, ',', '.') ?></td>
                <td>
                    <?php
                    $s = strtolower($row['status'] ?? 'pending');
                    $pill = match($s) {
                        'paid'     => 'pill-green',
                        'approved' => 'pill-blue',
                        'failed'   => 'pill-red',
                        default    => 'pill-gray',
                    };
                    ?>
                    <span class="pill <?= $pill ?>"><?= ucfirst($s) ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Total Recipients: <?= $totalRows ?></td>
                <td><?= number_format(array_sum(array_column($reportData, 'amount')), 0, ',', '.') ?> VND</td>
                <td>Total Disbursed</td>
            </tr>
        </tfoot>

        <?php elseif ($report_type === 'summary'): ?>
        <thead>
            <tr>
                <th>Student Code</th>
                <th>Full Name</th>
                <th>App Status</th>
                <th>Eligibility</th>
                <th>Reason</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reportData as $row): ?>
            <tr>
                <td><?= e($row['student_code']) ?></td>
                <td><strong><?= e($row['full_name']) ?></strong></td>
                <td><span class="pill pill-gray"><?= ucfirst(e($row['app_status'])) ?></span></td>
                <td>
                    <?php if ($row['is_passed'] === 1): ?>
                        <span class="pill pill-green">✓ Passed</span>
                    <?php elseif ($row['is_passed'] === 0): ?>
                        <span class="pill pill-red">✗ Failed</span>
                    <?php else: ?>
                        <span class="pill pill-gray">Pending</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:9.5pt;color:#64748b;"><?= e($row['reason'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">Total: <?= $totalRows ?></td>
                <td></td>
                <td>
                    Passed: <?= count(array_filter($reportData, fn($r) => $r['is_passed'] === 1)) ?>
                    / Failed: <?= count(array_filter($reportData, fn($r) => $r['is_passed'] === 0)) ?>
                </td>
                <td></td>
            </tr>
        </tfoot>
        <?php endif; ?>

    </table>
    <?php endif; ?>

    <!-- ── Signatures ───────────────────────────────────── -->
    <div class="pdf-signatures">
        <div class="sig-block">
            <div class="sig-line">
                <div class="sig-name"><?= $generatedBy ?></div>
                <div class="sig-title">Scholarship Committee</div>
            </div>
        </div>
        <div class="sig-block">
            <div class="sig-line">
                <div class="sig-name">University President</div>
                <div class="sig-title">Academic Authority</div>
            </div>
        </div>
    </div>

    <!-- ── Page footer ──────────────────────────────────── -->
    <div class="pdf-footer">
        <span>Scholarship Management System · Confidential Document</span>
        <span><?= e($program['name']) ?> · <?= $generatedAt ?></span>
    </div>

</div>

<script>
    // Auto-trigger print dialog when page fully loads
    // Only auto-print if opened from the "Export PDF" button (not direct URL)
    if (document.referrer && document.referrer.includes('index.php')) {
        window.addEventListener('load', () => {
            setTimeout(() => window.print(), 600);
        });
    }
</script>
</body>
</html>
