<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pageTitle = 'Review Analytics';

require_once '../config/db.php';
require_once '../includes/auth.php';

requireLogin();
requireRole('reviewer');

require_once '../includes/header.php';
require_once '../includes/navbar.php';
require_once '_reviewer_common.php';

reviewerCss();

$pdo = getDB();

function safeCount($pdo, $sql) {
    try {
        return (int)$pdo->query($sql)->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function safeRows($pdo, $sql) {
    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

$totalApplications = safeCount($pdo, "SELECT COUNT(*) FROM applications");

$statusRows = safeRows($pdo, "
    SELECT status, COUNT(*) AS total
    FROM applications
    GROUP BY status
");

$evidenceRows = safeRows($pdo, "
    SELECT status, COUNT(*) AS total
    FROM application_evidence
    GROUP BY status
");

$programRows = safeRows($pdo, "
    SELECT sp.id, sp.name, COUNT(a.id) AS total
    FROM scholarship_programs sp
    LEFT JOIN applications a ON a.program_id = sp.id
    GROUP BY sp.id, sp.name
    ORDER BY total DESC
");

$topApplicants = safeRows($pdo, "
    SELECT 
        a.id,
        a.status,
        a.submitted_at,
        u.full_name,
        u.student_code,
        sp.name AS program_name,
        COALESCE(profile.gpa,0) AS gpa,
        COALESCE(profile.activities_count,0) AS activities_count,
        COALESCE(profile.failed_subjects,0) AS failed_subjects,
        COALESCE(profile.has_language_cert,0) AS has_language_cert,
        COALESCE(ROUND(SUM(es.score),1),0) AS total_score
    FROM applications a
    JOIN users u ON u.id = a.student_id
    JOIN scholarship_programs sp ON sp.id = a.program_id
    LEFT JOIN student_profiles profile ON profile.student_id = u.id
    LEFT JOIN evaluation_scores es ON es.application_id = a.id
    GROUP BY a.id, a.status, a.submitted_at, u.full_name, u.student_code, sp.name, profile.gpa, profile.activities_count, profile.failed_subjects, profile.has_language_cert
    ORDER BY 
        CASE 
            WHEN a.status IN ('submitted','reviewing','pending','need_more_info') THEN 0
            WHEN a.status = 'approved' THEN 1
            ELSE 2
        END,
        total_score DESC,
        profile.gpa DESC
    LIMIT 12
");

try {
    $avgGpa = $pdo->query("SELECT ROUND(AVG(gpa), 2) FROM student_profiles")->fetchColumn();
    $avgGpa = $avgGpa ?: 0;
} catch (Exception $e) {
    $avgGpa = 0;
}

$certCount = safeCount($pdo, "SELECT COUNT(*) FROM student_profiles WHERE has_language_cert = 1");
$failedCount = safeCount($pdo, "SELECT COUNT(*) FROM student_profiles WHERE failed_subjects > 0");

function findTotal($rows, $key) {
    foreach ($rows as $r) {
        if (($r['status'] ?? '') === $key) return (int)$r['total'];
    }
    return 0;
}

$approved  = findTotal($statusRows, 'approved');
$rejected  = findTotal($statusRows, 'rejected');
$submitted = findTotal($statusRows, 'submitted');
$reviewing = findTotal($statusRows, 'reviewing');
$pending   = findTotal($statusRows, 'pending');
$needInfo  = findTotal($statusRows, 'need_more_info');
$pendingReview = $submitted + $reviewing + $pending + $needInfo;

$evPending  = findTotal($evidenceRows, 'pending') + findTotal($evidenceRows, 'uploaded');
$evAccepted = findTotal($evidenceRows, 'accepted') + findTotal($evidenceRows, 'verified');
$evRejected = findTotal($evidenceRows, 'rejected');

$totalEvidence = $evPending + $evAccepted + $evRejected;
$approvalRate = $totalApplications > 0 ? round(($approved / $totalApplications) * 100, 1) : 0;
$evidencePassRate = $totalEvidence > 0 ? round(($evAccepted / $totalEvidence) * 100, 1) : 0;
$rejectionRate = $totalApplications > 0 ? round(($rejected / $totalApplications) * 100, 1) : 0;

$statusLabels = [];
$statusData = [];
foreach ($statusRows as $row) {
    $statusLabels[] = ucwords(str_replace('_', ' ', $row['status']));
    $statusData[] = (int)$row['total'];
}

$programLabels = [];
$programData = [];
foreach ($programRows as $row) {
    $programLabels[] = $row['name'];
    $programData[] = (int)$row['total'];
}

$scoreLabels = [];
$scoreData = [];
foreach ($topApplicants as $row) {
    $scoreLabels[] = $row['student_code'];
    $scoreData[] = (float)$row['total_score'];
}

$statusSummary = [
    'Submitted' => $submitted,
    'Reviewing' => $reviewing,
    'Approved' => $approved,
    'Rejected' => $rejected,
];

function cleanStatus($status) {
    return ucwords(str_replace('_', ' ', (string)$status));
}

function analyticsStatusClass($status) {
    switch ($status) {
        case 'approved':
            return 'ok';
        case 'rejected':
            return 'bad';
        case 'reviewing':
            return 'warn';
        case 'submitted':
        case 'pending':
        case 'need_more_info':
            return 'info';
        default:
            return 'default';
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root {
    --an-bg: #f4f7fb;
    --an-card: #ffffff;
    --an-text: #0f172a;
    --an-muted: #64748b;
    --an-line: #e2e8f0;
    --an-soft: #f8fafc;
    --an-primary: #2563eb;
    --an-primary-dark: #1d4ed8;
    --an-success: #16a34a;
    --an-warning: #d97706;
    --an-danger: #dc2626;
    --an-shadow: 0 14px 34px rgba(15, 23, 42, .07);
}

html, body {
    overflow-x: hidden;
    background: var(--an-bg);
}

.analytics-page {
    min-height: 100vh;
    background: var(--an-bg);
    color: var(--an-text);
    padding: 24px 26px 70px;
}

.analytics-inner {
    max-width: 100%;
    margin: 0 auto;
}

.an-card {
    background: var(--an-card);
    border: 1px solid var(--an-line);
    border-radius: 22px;
    box-shadow: var(--an-shadow);
}

.an-header {
    padding: 22px 24px;
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: flex-start;
    flex-wrap: wrap;
}

.an-title h2 {
    margin: 0;
    font-size: 26px;
    font-weight: 900;
    letter-spacing: -.4px;
}

.an-title p {
    margin: 6px 0 0;
    color: var(--an-muted);
    font-size: 14px;
    font-weight: 600;
}

.an-tools {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.an-btn {
    min-height: 42px;
    border: 1px solid var(--an-line);
    background: #fff;
    color: var(--an-text);
    border-radius: 14px;
    padding: 9px 14px;
    text-decoration: none;
    font-weight: 850;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    white-space: nowrap;
}

.an-btn:hover {
    color: var(--an-primary);
    background: #f8fafc;
    border-color: #bfdbfe;
}

.an-btn-primary {
    background: var(--an-primary);
    border-color: var(--an-primary);
    color: #fff;
}

.an-btn-primary:hover {
    background: var(--an-primary-dark);
    color: #fff;
}

.metric-card {
    padding: 20px;
    height: 100%;
}

.metric-top {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    align-items: flex-start;
}

.metric-label {
    color: var(--an-muted);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .08em;
    font-weight: 900;
}

.metric-value {
    color: var(--an-text);
    font-size: 34px;
    font-weight: 950;
    margin-top: 5px;
    line-height: 1;
}

.metric-note {
    color: var(--an-muted);
    font-size: 13px;
    margin-top: 12px;
}

.metric-icon {
    width: 46px;
    height: 46px;
    border-radius: 15px;
    background: #eef4ff;
    color: var(--an-primary);
    display: grid;
    place-items: center;
    font-size: 21px;
    flex-shrink: 0;
}

.metric-line {
    height: 6px;
    background: #edf2f7;
    border-radius: 999px;
    overflow: hidden;
    margin-top: 14px;
}

.metric-line span {
    display: block;
    height: 100%;
    background: var(--an-primary);
    border-radius: inherit;
}

.panel {
    padding: 22px;
}

.panel-head {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.panel-title {
    margin: 0;
    font-size: 20px;
    font-weight: 900;
    letter-spacing: -.2px;
}

.panel-sub {
    color: var(--an-muted);
    font-size: 13px;
    margin-top: 3px;
}

.chart-box {
    height: 295px;
    position: relative;
}

.chart-box.small-chart {
    height: 245px;
}

.analytics-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.25fr) minmax(340px, .75fr);
    gap: 18px;
}

.control-card {
    padding: 16px;
    margin-bottom: 18px;
}

.control-grid {
    display: grid;
    grid-template-columns: minmax(260px, 1fr) 190px auto;
    gap: 10px;
    align-items: center;
}

.control-input,
.control-select {
    min-height: 44px;
    border: 1px solid var(--an-line);
    border-radius: 14px;
    padding: 9px 13px;
    outline: none;
    background: #fff;
    width: 100%;
}

.control-input:focus,
.control-select:focus {
    border-color: var(--an-primary);
    box-shadow: 0 0 0 4px rgba(37,99,235,.1);
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

.analytics-table {
    width: 100%;
    margin: 0;
    table-layout: fixed;
}

.analytics-table th {
    color: var(--an-muted);
    background: #f8fafc;
    border-bottom: 1px solid var(--an-line);
    padding: 13px 12px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .08em;
    white-space: nowrap;
}

.analytics-table td {
    padding: 14px 12px;
    border-bottom: 1px solid #edf2f7;
    vertical-align: middle;
}

.analytics-table tr:last-child td {
    border-bottom: 0;
}

.student-cell {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
}

.avatar {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    background: #e0ecff;
    color: var(--an-primary);
    display: grid;
    place-items: center;
    font-weight: 950;
    flex-shrink: 0;
}

.name-text {
    font-weight: 900;
    line-height: 1.2;
}

.sub-text {
    color: var(--an-muted);
    font-size: 12px;
    margin-top: 3px;
}

.program-text {
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 900;
    white-space: nowrap;
}

.status-pill.ok { background: #ecfdf5; color: #15803d; }
.status-pill.bad { background: #fef2f2; color: #b91c1c; }
.status-pill.warn { background: #fffbeb; color: #92400e; }
.status-pill.info { background: #eff6ff; color: #1d4ed8; }
.status-pill.default { background: #f1f5f9; color: #475569; }

.score-mini {
    min-width: 115px;
}

.score-track {
    height: 7px;
    background: #e5e7eb;
    border-radius: 999px;
    overflow: hidden;
    margin-top: 5px;
}

.score-track span {
    display: block;
    height: 100%;
    background: var(--an-primary);
    border-radius: inherit;
}

.side-list {
    display: grid;
    gap: 12px;
}

.side-item {
    border: 1px solid var(--an-line);
    background: #f8fafc;
    border-radius: 16px;
    padding: 14px;
}

.side-row {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    align-items: center;
}

.side-label {
    font-weight: 900;
}

.side-note {
    color: var(--an-muted);
    font-size: 13px;
    margin-top: 3px;
}

.side-value {
    font-size: 24px;
    font-weight: 950;
}

.program-list {
    display: grid;
    gap: 10px;
}

.program-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 54px;
    gap: 12px;
    align-items: center;
}

.program-name {
    font-weight: 800;
    font-size: 13px;
    line-height: 1.25;
}

.program-bar {
    height: 8px;
    background: #e5e7eb;
    border-radius: 999px;
    overflow: hidden;
    margin-top: 6px;
}

.program-bar span {
    display: block;
    height: 100%;
    background: var(--an-primary);
    border-radius: inherit;
}

.empty-state {
    border: 1px dashed #cbd5e1;
    background: #f8fafc;
    border-radius: 18px;
    padding: 28px;
    text-align: center;
    color: var(--an-muted);
}

@media (max-width: 1200px) {
    .analytics-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .analytics-page {
        padding: 16px 14px 60px;
    }

    .control-grid {
        grid-template-columns: 1fr;
    }

    .an-tools {
        width: 100%;
    }

    .an-btn {
        flex: 1;
        justify-content: center;
    }

    .chart-box {
        height: 260px;
    }

    .analytics-table {
        min-width: 760px;
    }
}
</style>

<div class="analytics-page">
    <div class="analytics-inner">

        <div class="an-card an-header">
            <div class="an-title">
                <h2>Review Analytics</h2>
                <p>Overview of application workload, evidence quality, scoring progress, and reviewer action list.</p>
            </div>

            <div class="an-tools">
                <a href="dashboard.php" class="an-btn">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
                <a href="evaluation_scores.php" class="an-btn">
                    <i class="bi bi-star"></i>
                    Evaluate
                </a>
                <button type="button" class="an-btn an-btn-primary" onclick="exportApplicantsCSV()">
                    <i class="bi bi-download"></i>
                    Export CSV
                </button>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-3">
                <div class="an-card metric-card">
                    <div class="metric-top">
                        <div>
                            <div class="metric-label">Applications</div>
                            <div class="metric-value"><?= $totalApplications ?></div>
                        </div>
                        <div class="metric-icon"><i class="bi bi-folder2-open"></i></div>
                    </div>
                    <div class="metric-note">Total submitted applications</div>
                    <div class="metric-line"><span style="width:100%"></span></div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="an-card metric-card">
                    <div class="metric-top">
                        <div>
                            <div class="metric-label">Pending Review</div>
                            <div class="metric-value"><?= $pendingReview ?></div>
                        </div>
                        <div class="metric-icon"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                    <div class="metric-note">Submitted, pending, reviewing, need info</div>
                    <div class="metric-line"><span style="width:<?= $totalApplications > 0 ? min(100, round($pendingReview / $totalApplications * 100)) : 0 ?>%"></span></div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="an-card metric-card">
                    <div class="metric-top">
                        <div>
                            <div class="metric-label">Average GPA</div>
                            <div class="metric-value"><?= e($avgGpa) ?></div>
                        </div>
                        <div class="metric-icon"><i class="bi bi-mortarboard"></i></div>
                    </div>
                    <div class="metric-note">Overall academic profile</div>
                    <div class="metric-line"><span style="width:<?= min(100, round(((float)$avgGpa / 4) * 100)) ?>%"></span></div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="an-card metric-card">
                    <div class="metric-top">
                        <div>
                            <div class="metric-label">Evidence Pass Rate</div>
                            <div class="metric-value"><?= $evidencePassRate ?>%</div>
                        </div>
                        <div class="metric-icon"><i class="bi bi-patch-check"></i></div>
                    </div>
                    <div class="metric-note"><?= $evAccepted ?> accepted / <?= $totalEvidence ?> evidence files</div>
                    <div class="metric-line"><span style="width:<?= min(100, $evidencePassRate) ?>%"></span></div>
                </div>
            </div>
        </div>

        <div class="an-card control-card">
            <div class="control-grid">
                <input id="tableSearch" class="control-input" type="text" placeholder="Search student, code, or program...">

                <select id="statusFilter" class="control-select">
                    <option value="">All Status</option>
                    <option value="submitted">Submitted</option>
                    <option value="pending">Pending</option>
                    <option value="reviewing">Reviewing</option>
                    <option value="need_more_info">Need More Info</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>

                <div class="an-tools">
                    <button type="button" class="an-btn" id="resetFilters">
                        <i class="bi bi-arrow-clockwise"></i>
                        Reset
                    </button>
                    <a class="an-btn" href="evidence_verification.php">
                        <i class="bi bi-shield-check"></i>
                        Evidence
                    </a>
                </div>
            </div>
        </div>

        <div class="analytics-grid mb-3">
            <div class="an-card panel">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Application Status Overview</h3>
                        <div class="panel-sub">A calm doughnut chart for quick workload reading.</div>
                    </div>
                </div>
                <div class="chart-box">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <div class="an-card panel">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Risk Signals</h3>
                        <div class="panel-sub">Reviewer should handle these first.</div>
                    </div>
                </div>

                <div class="side-list">
                    <div class="side-item">
                        <div class="side-row">
                            <div>
                                <div class="side-label">Pending evidence</div>
                                <div class="side-note">Files waiting for verification</div>
                            </div>
                            <div class="side-value"><?= $evPending ?></div>
                        </div>
                    </div>

                    <div class="side-item">
                        <div class="side-row">
                            <div>
                                <div class="side-label">Rejected evidence</div>
                                <div class="side-note">Documents with quality issues</div>
                            </div>
                            <div class="side-value"><?= $evRejected ?></div>
                        </div>
                    </div>

                    <div class="side-item">
                        <div class="side-row">
                            <div>
                                <div class="side-label">Failed subjects</div>
                                <div class="side-note">Applicants needing academic review</div>
                            </div>
                            <div class="side-value"><?= $failedCount ?></div>
                        </div>
                    </div>

                    <div class="side-item">
                        <div class="side-row">
                            <div>
                                <div class="side-label">Language certificates</div>
                                <div class="side-note">Applicants with certificate data</div>
                            </div>
                            <div class="side-value"><?= $certCount ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="analytics-grid mb-3">
            <div class="an-card panel">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Applications by Program</h3>
                        <div class="panel-sub">Horizontal chart keeps long scholarship names readable.</div>
                    </div>
                </div>
                <div class="chart-box">
                    <canvas id="programChart"></canvas>
                </div>
            </div>

            <div class="an-card panel">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Program Distribution</h3>
                        <div class="panel-sub">Clean percentage list, less visual noise.</div>
                    </div>
                </div>

                <div class="program-list">
                    <?php
                    $maxProgram = 0;
                    foreach ($programRows as $pr) {
                        $maxProgram = max($maxProgram, (int)$pr['total']);
                    }
                    foreach ($programRows as $pr):
                        $count = (int)$pr['total'];
                        $pct = $totalApplications > 0 ? round(($count / $totalApplications) * 100) : 0;
                        $bar = $maxProgram > 0 ? round(($count / $maxProgram) * 100) : 0;
                    ?>
                        <div class="program-item">
                            <div>
                                <div class="program-name"><?= e($pr['name']) ?></div>
                                <div class="program-bar"><span style="width:<?= $bar ?>%"></span></div>
                            </div>
                            <strong><?= $pct ?>%</strong>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!$programRows): ?>
                        <div class="empty-state">No program data available.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="an-card panel mb-3">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">Top Applicant Scores</h3>
                    <div class="panel-sub">Score trend from the current highest ranked applicants.</div>
                </div>
            </div>
            <div class="chart-box small-chart">
                <canvas id="scoreChart"></canvas>
            </div>
        </div>

        <div class="an-card panel">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">Applicant Review List</h3>
                    <div class="panel-sub">Filtered list for quick reviewer actions.</div>
                </div>
                <span class="status-pill info" id="resultCounter">Showing <?= count($topApplicants) ?></span>
            </div>

            <div class="table-wrap">
                <table class="table analytics-table" id="applicantTable">
                    <thead>
                        <tr>
                            <th style="width:28%">Student</th>
                            <th style="width:27%">Program</th>
                            <th style="width:18%">Profile</th>
                            <th style="width:12%">Status</th>
                            <th style="width:15%">Score</th>
                            <th style="width:100px" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topApplicants as $app): ?>
                            <?php
                                $status = $app['status'] ?: 'unknown';
                                $statusClass = analyticsStatusClass($status);
                                $searchData = strtolower(
                                    ($app['full_name'] ?? '') . ' ' .
                                    ($app['student_code'] ?? '') . ' ' .
                                    ($app['program_name'] ?? '') . ' ' .
                                    ($app['status'] ?? '')
                                );
                            ?>
                            <tr class="applicant-row" data-status="<?= e($status) ?>" data-search="<?= e($searchData) ?>">
                                <td>
                                    <div class="student-cell">
                                        <div class="avatar"><?= e(strtoupper(substr($app['full_name'] ?? 'A', 0, 1))) ?></div>
                                        <div>
                                            <div class="name-text"><?= e($app['full_name']) ?></div>
                                            <div class="sub-text"><?= e($app['student_code']) ?></div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="program-text"><?= e($app['program_name']) ?></div>
                                </td>

                                <td>
                                    <div class="sub-text">GPA <strong><?= e($app['gpa']) ?></strong> · Activities <?= e($app['activities_count']) ?></div>
                                    <div class="sub-text">Failed <?= e($app['failed_subjects']) ?> · Cert <?= $app['has_language_cert'] ? 'Yes' : 'No' ?></div>
                                </td>

                                <td>
                                    <span class="status-pill <?= $statusClass ?>"><?= e(cleanStatus($status)) ?></span>
                                </td>

                                <td>
                                    <div class="score-mini">
                                        <strong><?= e($app['total_score']) ?>/100</strong>
                                        <div class="score-track">
                                            <span style="width: <?= min(100, (float)$app['total_score']) ?>%"></span>
                                        </div>
                                    </div>
                                </td>

                                <td class="text-end">
                                    <a href="evaluation_scores.php?application_id=<?= (int)$app['id'] ?>" class="an-btn an-btn-primary" style="min-height:36px;padding:7px 12px;border-radius:12px;">
                                        Review
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$topApplicants): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">No applicant data available.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="emptyFilterState" class="empty-state d-none mt-3">
                No matching applicants found.
            </div>
        </div>
    </div>
</div>

<script>
const statusLabels = <?= json_encode($statusLabels) ?>;
const statusData = <?= json_encode($statusData) ?>;
const programLabels = <?= json_encode($programLabels) ?>;
const programData = <?= json_encode($programData) ?>;
const scoreLabels = <?= json_encode($scoreLabels) ?>;
const scoreData = <?= json_encode($scoreData) ?>;

Chart.defaults.font.family = "'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
Chart.defaults.color = '#64748b';
Chart.defaults.plugins.tooltip.backgroundColor = '#0f172a';
Chart.defaults.plugins.tooltip.padding = 12;
Chart.defaults.plugins.tooltip.cornerRadius = 12;

const calmPalette = ['#2563eb', '#94a3b8', '#16a34a', '#dc2626', '#d97706', '#64748b'];

if (document.getElementById('statusChart')) {
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: calmPalette,
                borderColor: '#ffffff',
                borderWidth: 4,
                hoverOffset: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 7,
                        boxHeight: 7,
                        padding: 18,
                        font: { weight: 700 }
                    }
                }
            }
        }
    });
}

if (document.getElementById('programChart')) {
    new Chart(document.getElementById('programChart'), {
        type: 'bar',
        data: {
            labels: programLabels,
            datasets: [{
                label: 'Applications',
                data: programData,
                backgroundColor: '#2563eb',
                borderRadius: 10,
                barThickness: 22,
                maxBarThickness: 28
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: '#edf2f7' },
                    ticks: { precision: 0 }
                },
                y: {
                    grid: { display: false },
                    ticks: {
                        font: { weight: 700 },
                        callback: function(value) {
                            const label = this.getLabelForValue(value);
                            return label.length > 28 ? label.substring(0, 28) + '…' : label;
                        }
                    }
                }
            }
        }
    });
}

if (document.getElementById('scoreChart')) {
    new Chart(document.getElementById('scoreChart'), {
        type: 'line',
        data: {
            labels: scoreLabels,
            datasets: [{
                label: 'Score',
                data: scoreData,
                tension: .32,
                borderWidth: 2.5,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37,99,235,.08)',
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#2563eb',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: { color: '#edf2f7' },
                    ticks: { stepSize: 20 }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { weight: 700 } }
                }
            }
        }
    });
}

const tableSearch = document.getElementById('tableSearch');
const statusFilter = document.getElementById('statusFilter');
const resetFilters = document.getElementById('resetFilters');
const rows = Array.from(document.querySelectorAll('#applicantTable tbody tr.applicant-row'));
const emptyFilterState = document.getElementById('emptyFilterState');
const resultCounter = document.getElementById('resultCounter');

function filterTable() {
    const keyword = tableSearch ? tableSearch.value.toLowerCase().trim() : '';
    const status = statusFilter ? statusFilter.value : '';
    let visible = 0;

    rows.forEach(row => {
        const text = row.dataset.search || '';
        const rowStatus = row.dataset.status || '';
        const matchKeyword = text.includes(keyword);
        const matchStatus = status === '' || rowStatus === status;

        const show = matchKeyword && matchStatus;
        row.style.display = show ? '' : 'none';

        if (show) visible++;
    });

    if (emptyFilterState) {
        emptyFilterState.classList.toggle('d-none', visible > 0);
    }

    if (resultCounter) {
        resultCounter.textContent = 'Showing ' + visible;
    }
}

if (tableSearch) tableSearch.addEventListener('input', filterTable);
if (statusFilter) statusFilter.addEventListener('change', filterTable);

if (resetFilters) {
    resetFilters.addEventListener('click', function() {
        if (tableSearch) tableSearch.value = '';
        if (statusFilter) statusFilter.value = '';
        filterTable();
    });
}

function exportApplicantsCSV() {
    const table = document.getElementById('applicantTable');
    const csv = [];

    if (!table) return;

    table.querySelectorAll('tr').forEach(row => {
        if (row.style.display === 'none') return;

        const cols = row.querySelectorAll('th, td');
        const data = [];

        cols.forEach(col => {
            let text = col.innerText.replace(/\s+/g, ' ').trim();
            text = '"' + text.replace(/"/g, '""') + '"';
            data.push(text);
        });

        csv.push(data.join(','));
    });

    const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');

    link.href = URL.createObjectURL(blob);
    link.download = 'review-analytics-applicants.csv';
    link.click();
}

filterTable();
</script>

<?php require_once '../includes/footer.php'; ?>
