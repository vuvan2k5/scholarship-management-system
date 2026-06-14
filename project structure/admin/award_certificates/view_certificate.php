<?php
// ============================================================
// admin/award_certificates/view_certificate.php
// Printable Certificate - Works as PDF via browser print
// ============================================================

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT 
        ac.*,
        u.full_name AS student_name,
        u.student_code,
        sp.name AS program_name,
        sp.description AS program_desc,
        sp.start_date,
        sp.end_date,
        sp.budget,
        sp.slots,
        rr.rank AS student_rank,
        rr.total_score,
        issuer.full_name AS issued_by_name
    FROM award_certificates ac
    INNER JOIN applications a ON ac.application_id = a.id
    INNER JOIN users u ON a.student_id = u.id
    INNER JOIN scholarship_programs sp ON a.program_id = sp.id
    LEFT JOIN ranking_results rr ON rr.application_id = a.id
    LEFT JOIN users issuer ON issuer.id = ac.issued_by
    WHERE ac.id = ?
");
$stmt->execute([$id]);
$cert = $stmt->fetch();

if (!$cert) {
    die("<p style='font-family:sans-serif;text-align:center;padding:50px;'>Certificate not found.</p>");
}

$issuedDate = date('F j, Y', strtotime($cert['issued_at']));
$programPeriod = date('M Y', strtotime($cert['start_date'])) . ' – ' . date('M Y', strtotime($cert['end_date']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate - <?= htmlspecialchars($cert['student_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=EB+Garamond:ital,wght@0,400;0,500;1,400&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #e8e4d9;
            font-family: 'Open Sans', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            padding: 30px 20px;
        }

        /* ── Print Controls (hidden when printing) ── */
        .no-print {
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
        }
        .no-print button, .no-print a {
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.2s;
        }
        .btn-print { background: #2563eb; color: #fff; }
        .btn-print:hover { background: #1d4ed8; }
        .btn-back { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .btn-back:hover { background: #e2e8f0; }

        /* ── Certificate Paper ── */
        .certificate {
            width: 960px;
            min-height: 680px;
            background: #fffdf5;
            position: relative;
            padding: 50px 70px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        /* Ornate Border */
        .certificate::before {
            content: '';
            position: absolute;
            inset: 12px;
            border: 1px solid #c9a84c;
            pointer-events: none;
        }
        .certificate::after {
            content: '';
            position: absolute;
            inset: 18px;
            border: 3px double #c9a84c;
            pointer-events: none;
        }

        /* Corner ornaments */
        .corner {
            position: absolute;
            width: 60px;
            height: 60px;
            font-size: 28px;
            color: #c9a84c;
            opacity: 0.85;
            line-height: 1;
        }
        .corner-tl { top: 22px; left: 22px; }
        .corner-tr { top: 22px; right: 22px; transform: scaleX(-1); }
        .corner-bl { bottom: 22px; left: 22px; transform: scaleY(-1); }
        .corner-br { bottom: 22px; right: 22px; transform: scale(-1); }

        /* ── Seal ── */
        .seal {
            width: 90px; height: 90px;
            border-radius: 50%;
            background: radial-gradient(circle at 35% 35%, #f5c842, #b8860b);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(184,134,11,0.4), inset 0 1px 3px rgba(255,255,255,0.3);
        }
        .seal-icon { font-size: 40px; }

        /* ── Typography ── */
        .institution {
            font-family: 'Cinzel', serif;
            font-size: 13px;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: #7a6930;
            margin-bottom: 8px;
        }
        .cert-title {
            font-family: 'Cinzel', serif;
            font-size: 42px;
            font-weight: 700;
            color: #1a1209;
            letter-spacing: 2px;
            margin-bottom: 6px;
            line-height: 1.1;
        }
        .cert-subtitle {
            font-family: 'Cinzel', serif;
            font-size: 16px;
            color: #7a6930;
            letter-spacing: 3px;
            margin-bottom: 30px;
        }

        .divider {
            width: 340px;
            height: 2px;
            background: linear-gradient(to right, transparent, #c9a84c, transparent);
            margin: 12px auto;
        }

        .cert-presented {
            font-family: 'EB Garamond', serif;
            font-size: 17px;
            color: #555;
            margin-bottom: 6px;
        }

        .student-name {
            font-family: 'EB Garamond', serif;
            font-size: 44px;
            font-weight: 500;
            font-style: italic;
            color: #1a3a6b;
            margin: 8px 0 4px;
            line-height: 1.1;
        }

        .student-code {
            font-size: 13px;
            color: #888;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .cert-body {
            font-family: 'EB Garamond', serif;
            font-size: 17px;
            color: #444;
            max-width: 700px;
            line-height: 1.8;
            margin-bottom: 24px;
        }
        .cert-body strong {
            color: #1a1209;
            font-weight: 600;
        }

        /* ── Stats Row ── */
        .cert-stats {
            display: flex;
            gap: 0;
            margin: 0 auto 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: #faf7ee;
        }
        .stat-box {
            padding: 14px 28px;
            text-align: center;
            border-right: 1px solid #ddd;
        }
        .stat-box:last-child { border-right: none; }
        .stat-box .lbl { font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px; color: #888; }
        .stat-box .val { font-size: 20px; font-weight: 700; color: #1a3a6b; margin-top: 2px; }

        /* ── Footer Signatures ── */
        .cert-footer {
            display: flex;
            justify-content: space-between;
            width: 100%;
            margin-top: auto;
            padding-top: 20px;
        }
        .sig-block { text-align: center; min-width: 180px; }
        .sig-line { border-top: 1px solid #333; padding-top: 8px; margin-top: 40px; }
        .sig-name { font-weight: 600; font-size: 14px; color: #1a1209; }
        .sig-role { font-size: 12px; color: #777; margin-top: 2px; letter-spacing: 1px; }

        .cert-code {
            position: absolute;
            bottom: 36px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 11px;
            color: #aaa;
            letter-spacing: 2px;
            font-family: monospace;
        }

        /* ── Print Media ── */
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; padding: 0; }
            .certificate {
                width: 100%;
                min-height: 100vh;
                box-shadow: none;
            }
            @page {
                size: A4 landscape;
                margin: 0;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">
        🖨️ Print / Save as PDF
    </button>
    <a href="index.php" class="btn-back">← Back to Certificates</a>
</div>

<div class="certificate">
    <!-- Corner ornaments -->
    <span class="corner corner-tl">❧</span>
    <span class="corner corner-tr">❧</span>
    <span class="corner corner-bl">❧</span>
    <span class="corner corner-br">❧</span>

    <!-- Seal -->
    <div class="seal">
        <span class="seal-icon">🎓</span>
    </div>

    <div class="institution">Scholarship Management System</div>
    <div class="cert-title">Certificate of Award</div>
    <div class="cert-subtitle">Academic Excellence</div>
    
    <div class="divider"></div>

    <div class="cert-presented">This certificate is proudly presented to</div>
    <div class="student-name"><?= htmlspecialchars($cert['student_name']) ?></div>
    <div class="student-code"><?= htmlspecialchars($cert['student_code']) ?></div>

    <div class="cert-body">
        in recognition of outstanding academic achievement and successful fulfillment of all eligibility requirements for the
        <br><strong><?= htmlspecialchars($cert['program_name']) ?></strong><br>
        scholarship program, for the period of <strong><?= $programPeriod ?></strong>.
    </div>

    <!-- Stats -->
    <div class="cert-stats">
        <div class="stat-box">
            <div class="lbl">Ranking</div>
            <div class="val">#<?= htmlspecialchars($cert['student_rank'] ?? 'N/A') ?></div>
        </div>
        <div class="stat-box">
            <div class="lbl">Score</div>
            <div class="val"><?= number_format((float)($cert['total_score'] ?? 0), 1) ?></div>
        </div>
        <div class="stat-box">
            <div class="lbl">Date Issued</div>
            <div class="val" style="font-size:14px;"><?= $issuedDate ?></div>
        </div>
    </div>

    <!-- Signatures -->
    <div class="cert-footer">
        <div class="sig-block">
            <div class="sig-line">
                <div class="sig-name"><?= htmlspecialchars($cert['issued_by_name'] ?? 'System Admin') ?></div>
                <div class="sig-role">Scholarship Committee</div>
            </div>
        </div>
        <div class="sig-block">
            <div class="sig-line">
                <div class="sig-name">University President</div>
                <div class="sig-role">Academic Authority</div>
            </div>
        </div>
    </div>

    <!-- Certificate Code -->
    <div class="cert-code"><?= htmlspecialchars($cert['certificate_code']) ?></div>
</div>

</body>
</html>
