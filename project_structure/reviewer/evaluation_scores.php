<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pageTitle = 'Evaluation Scores';

require_once '../config/db.php';
require_once '../includes/auth.php';

requireLogin();
requireRole('reviewer');

$pdo = getDB();
$reviewerId = (int)($_SESSION['user_id'] ?? 0);

function calculateAutoScore($criterionName, $maxScore, $profile) {
    $name = strtolower((string)$criterionName);
    $maxScore = (float)$maxScore;

    $gpa = (float)($profile['gpa'] ?? 0);
    $activities = (int)($profile['activities_count'] ?? 0);
    $failed = (int)($profile['failed_subjects'] ?? 0);
    $cert = (int)($profile['has_language_cert'] ?? 0);

    if (strpos($name, 'gpa') !== false || strpos($name, 'academic') !== false || strpos($name, 'performance') !== false) {
        if ($gpa >= 3.6) return $maxScore;
        if ($gpa >= 3.2) return $maxScore * 0.85;
        if ($gpa >= 2.8) return $maxScore * 0.65;
        if ($gpa >= 2.5) return $maxScore * 0.45;
        return $maxScore * 0.25;
    }

    if (strpos($name, 'activity') !== false || strpos($name, 'activities') !== false || strpos($name, 'extracurricular') !== false || strpos($name, 'community') !== false || strpos($name, 'leadership') !== false) {
        if ($activities >= 5) return $maxScore;
        if ($activities >= 3) return $maxScore * 0.75;
        if ($activities >= 1) return $maxScore * 0.45;
        return 0;
    }

    if (strpos($name, 'certificate') !== false || strpos($name, 'language') !== false || strpos($name, 'english') !== false || strpos($name, 'proficiency') !== false) {
        return $cert ? $maxScore : 0;
    }

    if (strpos($name, 'failed') !== false || strpos($name, 'discipline') !== false) {
        if ($failed === 0) return $maxScore;
        if ($failed === 1) return $maxScore * 0.6;
        if ($failed === 2) return $maxScore * 0.3;
        return 0;
    }

    if (strpos($name, 'research') !== false || strpos($name, 'project') !== false || strpos($name, 'output') !== false) {
        if ($gpa >= 3.6 && $activities >= 3) return $maxScore;
        if ($gpa >= 3.2) return $maxScore * 0.75;
        if ($gpa >= 2.8) return $maxScore * 0.5;
        return $maxScore * 0.25;
    }

    return $maxScore * 0.5;
}

function calculateWeightedScore($rawScore, $maxScore, $weight) {
    if ((float)$maxScore <= 0) return 0;
    return round(((float)$rawScore / (float)$maxScore) * (float)$weight, 1);
}

function autoNote($criterionName, $profile) {
    $name = strtolower((string)$criterionName);

    if (strpos($name, 'gpa') !== false || strpos($name, 'academic') !== false) {
        return 'Auto suggestion based on GPA: ' . ($profile['gpa'] ?? 0);
    }

    if (strpos($name, 'activity') !== false || strpos($name, 'extracurricular') !== false || strpos($name, 'community') !== false || strpos($name, 'leadership') !== false) {
        return 'Auto suggestion based on activities count: ' . ($profile['activities_count'] ?? 0);
    }

    if (strpos($name, 'certificate') !== false || strpos($name, 'language') !== false || strpos($name, 'english') !== false) {
        return !empty($profile['has_language_cert']) ? 'Student has language certificate.' : 'Student does not have language certificate.';
    }

    if (strpos($name, 'failed') !== false || strpos($name, 'discipline') !== false) {
        return 'Auto suggestion based on failed subjects: ' . ($profile['failed_subjects'] ?? 0);
    }

    return 'Auto suggestion by system rule.';
}

function displayText($text) {
    return ucwords(str_replace('_', ' ', (string)$text));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicationId = (int)($_POST['application_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    $decisionComment = trim($_POST['decision_comment'] ?? '');

    $reviewerScores = $_POST['reviewer_score'] ?? [];
    $reviewerNotes = $_POST['reviewer_note'] ?? [];
    $acceptAuto = $_POST['accept_auto'] ?? [];

    if ($applicationId > 0) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    profile.gpa,
                    profile.activities_count,
                    profile.failed_subjects,
                    profile.has_language_cert
                FROM applications a
                JOIN users u ON u.id = a.student_id
                LEFT JOIN student_profiles profile ON profile.student_id = u.id
                WHERE a.id = ?
            ");
            $stmt->execute([$applicationId]);
            $profile = $stmt->fetch();

            if (!$profile) {
                $profile = [
                    'gpa' => 0,
                    'activities_count' => 0,
                    'failed_subjects' => 0,
                    'has_language_cert' => 0
                ];
            }

            $stmt = $pdo->prepare("
                SELECT sc.*
                FROM scoring_criteria sc
                JOIN applications a ON a.program_id = sc.program_id
                WHERE a.id = ?
                ORDER BY sc.id ASC
            ");
            $stmt->execute([$applicationId]);
            $criteria = $stmt->fetchAll();

            foreach ($criteria as $c) {
                $criteriaId = (int)$c['id'];
                $weight = (float)$c['weight'];

                $rawAutoScore = round(calculateAutoScore($c['criterion_name'], $c['max_score'], $profile), 1);
                $autoWeightedScore = calculateWeightedScore($rawAutoScore, $c['max_score'], $c['weight']);

                if (isset($acceptAuto[$criteriaId])) {
                    $finalScore = $autoWeightedScore;
                    $note = autoNote($c['criterion_name'], $profile) . ' Reviewer accepted auto score.';
                } else {
                    $manualScore = isset($reviewerScores[$criteriaId]) ? (float)$reviewerScores[$criteriaId] : $autoWeightedScore;
                    $finalScore = max(0, min($manualScore, $weight));
                    $note = trim($reviewerNotes[$criteriaId] ?? '');

                    if ($note === '') {
                        $note = 'Reviewer edited score manually.';
                    }
                }

                $stmt = $pdo->prepare("
                    INSERT INTO evaluation_scores 
                        (application_id, criteria_id, council_id, score, note)
                    VALUES 
                        (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        score = VALUES(score),
                        note = VALUES(note),
                        scored_at = NOW()
                ");
                $stmt->execute([
                    $applicationId,
                    $criteriaId,
                    $reviewerId,
                    round($finalScore, 1),
                    $note
                ]);
            }

            if (in_array($decision, ['approved', 'rejected', 'need_more_info'], true) && $decisionComment !== '') {
                $stmt = $pdo->prepare("
                    INSERT INTO reviewer_decisions 
                        (application_id, reviewer_id, decision, comment, created_at)
                    VALUES 
                        (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$applicationId, $reviewerId, $decision, $decisionComment]);

                if ($decision === 'approved') {
                    $pdo->prepare("UPDATE applications SET status='approved' WHERE id=?")->execute([$applicationId]);
                } elseif ($decision === 'rejected') {
                    $pdo->prepare("UPDATE applications SET status='rejected' WHERE id=?")->execute([$applicationId]);
                } else {
                    $pdo->prepare("UPDATE applications SET status='reviewing' WHERE id=?")->execute([$applicationId]);
                }
            }

            $_SESSION['flash_success'] = 'Review saved successfully.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Save failed: ' . $e->getMessage();
        }

        header('Location: evaluation_scores.php?application_id=' . $applicationId);
        exit;
    }

    $_SESSION['flash_error'] = 'Invalid application.';
    header('Location: evaluation_scores.php');
    exit;
}

require_once '../includes/header.php';
require_once '../includes/navbar.php';
require_once '_reviewer_common.php';

reviewerCss();

$applications = $pdo->query("
    SELECT 
        a.id,
        a.status,
        u.full_name,
        u.student_code,
        sp.name AS program_name,
        profile.gpa,
        profile.activities_count,
        profile.failed_subjects,
        profile.has_language_cert,
        COALESCE(ROUND(SUM(es.score),1),0) AS total_score
    FROM applications a
    JOIN users u ON u.id = a.student_id
    JOIN scholarship_programs sp ON sp.id = a.program_id
    LEFT JOIN student_profiles profile ON profile.student_id = u.id
    LEFT JOIN evaluation_scores es ON es.application_id = a.id
    GROUP BY 
        a.id,
        a.status,
        u.full_name,
        u.student_code,
        sp.name,
        profile.gpa,
        profile.activities_count,
        profile.failed_subjects,
        profile.has_language_cert
    ORDER BY 
        FIELD(a.status,'submitted','reviewing','need_more_info','approved','rejected'),
        a.id DESC
")->fetchAll();

$totalApplications = count($applications);
$pendingApplications = 0;
$approvedApplications = 0;
$rejectedApplications = 0;
$averageScore = 0;

foreach ($applications as $app) {
    if (in_array($app['status'], ['submitted', 'reviewing', 'need_more_info'], true)) {
        $pendingApplications++;
    }

    if ($app['status'] === 'approved') {
        $approvedApplications++;
    }

    if ($app['status'] === 'rejected') {
        $rejectedApplications++;
    }

    $averageScore += (float)$app['total_score'];
}

$averageScore = $totalApplications > 0 ? round($averageScore / $totalApplications, 1) : 0;

$selectedId = (int)($_GET['application_id'] ?? ($applications[0]['id'] ?? 0));

$selected = null;
foreach ($applications as $app) {
    if ((int)$app['id'] === $selectedId) {
        $selected = $app;
        break;
    }
}

$criteria = [];
$currentScores = [];
$decisions = [];

if ($selected) {
    $stmt = $pdo->prepare("
        SELECT sc.*
        FROM scoring_criteria sc
        JOIN applications a ON a.program_id = sc.program_id
        WHERE a.id = ?
        ORDER BY sc.id ASC
    ");
    $stmt->execute([$selectedId]);
    $criteria = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT criteria_id, score, note
        FROM evaluation_scores
        WHERE application_id = ? AND council_id = ?
    ");
    $stmt->execute([$selectedId, $reviewerId]);

    foreach ($stmt->fetchAll() as $row) {
        $currentScores[(int)$row['criteria_id']] = $row;
    }

    $stmt = $pdo->prepare("
        SELECT rd.*, u.full_name AS reviewer_name
        FROM reviewer_decisions rd
        LEFT JOIN users u ON u.id = rd.reviewer_id
        WHERE rd.application_id = ?
        ORDER BY rd.created_at DESC
    ");
    $stmt->execute([$selectedId]);
    $decisions = $stmt->fetchAll();
}
?>

<style>
:root {
    --ev-bg: #f4f7fb;
    --ev-card: #ffffff;
    --ev-text: #0f172a;
    --ev-muted: #64748b;
    --ev-line: #e2e8f0;
    --ev-soft: #f8fafc;
    --ev-primary: #2563eb;
    --ev-primary-dark: #1d4ed8;
    --ev-success: #16a34a;
    --ev-danger: #dc2626;
    --ev-warning: #d97706;
    --ev-shadow: 0 12px 30px rgba(15, 23, 42, .07);
}

html, body {
    overflow-x: hidden;
    background: var(--ev-bg);
}

.eval-page {
    min-height: calc(100vh - 70px);
    background: var(--ev-bg);
    color: var(--ev-text);
    padding: 22px 22px 70px;
}

.eval-wrap {
    width: 100%;
    max-width: 100%;
    margin: 0 auto;
}

.ev-card {
    background: var(--ev-card);
    border: 1px solid var(--ev-line);
    border-radius: 22px;
    box-shadow: var(--ev-shadow);
}

.ev-topbar {
    padding: 22px 24px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
}

.ev-title h2 {
    margin: 0;
    font-size: 28px;
    font-weight: 950;
    letter-spacing: -.5px;
}

.ev-title p {
    margin: 6px 0 0;
    color: var(--ev-muted);
    font-weight: 600;
    font-size: 14px;
}

.ev-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.ev-btn {
    min-height: 42px;
    padding: 9px 14px;
    border-radius: 14px;
    border: 1px solid var(--ev-line);
    background: #fff;
    color: var(--ev-text);
    font-weight: 850;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    white-space: nowrap;
}

.ev-btn:hover {
    background: var(--ev-soft);
    color: var(--ev-primary);
}

.ev-btn-primary {
    background: var(--ev-primary);
    border-color: var(--ev-primary);
    color: #fff;
}

.ev-btn-primary:hover {
    background: var(--ev-primary-dark);
    color: #fff;
}

.ev-btn-danger {
    background: #fff;
    border-color: #fecaca;
    color: #b91c1c;
}

.ev-btn-danger:hover {
    background: #fef2f2;
    color: #991b1b;
}

.kpi-row {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 18px;
}

.kpi-card {
    padding: 18px;
    min-height: 116px;
}

.kpi-label {
    color: var(--ev-muted);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .8px;
    font-weight: 950;
}

.kpi-value {
    font-size: 34px;
    font-weight: 950;
    line-height: 1;
    margin-top: 10px;
}

.kpi-note {
    margin-top: 10px;
    color: var(--ev-muted);
    font-size: 13px;
}

.main-layout {
    display: grid;
    grid-template-columns: 300px minmax(0, 1fr) 300px;
    gap: 18px;
    align-items: start;
}

.panel {
    padding: 20px;
}

.panel-title {
    margin: 0;
    font-size: 20px;
    font-weight: 950;
    letter-spacing: -.2px;
}

.panel-subtitle {
    margin-top: 4px;
    color: var(--ev-muted);
    font-size: 13px;
    font-weight: 600;
}

.search-box {
    position: relative;
    margin: 16px 0 12px;
}

.search-box i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
}

.search-box input,
.note-area,
.score-input {
    border: 1px solid var(--ev-line);
    background: #fff;
    border-radius: 14px;
    outline: none;
}

.search-box input {
    width: 100%;
    min-height: 44px;
    padding: 10px 12px 10px 40px;
    font-weight: 650;
}

.search-box input:focus,
.note-area:focus,
.score-input:focus {
    border-color: var(--ev-primary);
    box-shadow: 0 0 0 4px rgba(37, 99, 235, .10);
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    margin-bottom: 14px;
}

.filter-pill {
    border: 1px solid var(--ev-line);
    background: #fff;
    border-radius: 13px;
    padding: 9px 10px;
    color: #334155;
    font-size: 13px;
    font-weight: 850;
}

.filter-pill.active,
.filter-pill:hover {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: var(--ev-primary);
}

.app-list {
    max-height: calc(100vh - 370px);
    overflow-y: auto;
    padding-right: 3px;
}

.app-card {
    display: block;
    text-decoration: none;
    color: var(--ev-text);
    border: 1px solid var(--ev-line);
    border-radius: 17px;
    padding: 14px;
    margin-bottom: 10px;
    background: #fff;
}

.app-card:hover,
.app-card.active {
    border-color: #93c5fd;
    background: #f8fbff;
}

.app-card.active {
    box-shadow: inset 4px 0 0 var(--ev-primary);
}

.student-name {
    font-weight: 950;
    line-height: 1.25;
}

.student-meta,
.small-muted {
    color: var(--ev-muted);
    font-size: 13px;
}

.status-badge {
    border-radius: 999px;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 900;
    text-transform: capitalize;
    white-space: nowrap;
}

.score-progress {
    height: 7px;
    background: #e5e7eb;
    border-radius: 999px;
    overflow: hidden;
}

.score-progress span {
    display: block;
    height: 100%;
    width: 0;
    background: var(--ev-primary);
    border-radius: inherit;
}

.profile-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 18px;
}

.profile-head h3 {
    margin: 0;
    font-size: 25px;
    font-weight: 950;
    letter-spacing: -.4px;
}

.profile-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
}

.profile-stat {
    border: 1px solid var(--ev-line);
    border-radius: 17px;
    padding: 14px;
    background: #fbfdff;
}

.profile-stat span {
    display: block;
    color: var(--ev-muted);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .7px;
    font-weight: 950;
}

.profile-stat strong {
    display: block;
    margin-top: 4px;
    font-size: 24px;
    font-weight: 950;
}

.quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 12px;
    background: var(--ev-soft);
    border: 1px solid var(--ev-line);
    border-radius: 18px;
    margin: 16px 0;
}

.soft-btn {
    border: 1px solid var(--ev-line);
    background: #fff;
    color: #334155;
    border-radius: 13px;
    padding: 9px 12px;
    font-weight: 850;
}

.soft-btn:hover {
    color: var(--ev-primary);
    border-color: #bfdbfe;
    background: #eff6ff;
}

.criteria-card {
    border: 1px solid var(--ev-line);
    border-radius: 18px;
    background: #fff;
    padding: 16px;
    margin-bottom: 12px;
}

.criteria-top {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    align-items: start;
    margin-bottom: 12px;
}

.criteria-name {
    font-weight: 950;
    font-size: 16px;
}

.auto-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 999px;
    padding: 6px 10px;
    background: #f1f5f9;
    color: #334155;
    font-size: 12px;
    font-weight: 900;
    white-space: nowrap;
}

.criteria-form-row {
    display: grid;
    grid-template-columns: 130px 110px minmax(0, 1fr);
    gap: 12px;
    align-items: center;
}

.score-input {
    min-height: 42px;
    width: 100%;
    padding: 8px 10px;
    text-align: center;
    font-weight: 900;
}

.note-area {
    width: 100%;
    padding: 10px 12px;
    resize: vertical;
    min-height: 84px;
}

.form-check-label {
    font-weight: 850;
    color: #334155;
}

.side-panel {
    position: sticky;
    top: 84px;
}

.total-box {
    border: 1px solid var(--ev-line);
    border-radius: 18px;
    background: #fbfdff;
    padding: 18px;
    text-align: center;
    margin-bottom: 18px;
}

.total-number {
    font-size: 46px;
    font-weight: 950;
    color: var(--ev-primary-dark);
    line-height: 1;
}

.decision-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
    margin: 12px 0;
}

.decision-option {
    cursor: pointer;
}

.decision-option input {
    display: none;
}

.decision-option span {
    display: flex;
    align-items: center;
    gap: 9px;
    border: 1px solid var(--ev-line);
    background: #fff;
    color: #334155;
    border-radius: 14px;
    padding: 12px;
    font-weight: 900;
}

.decision-option input:checked + span {
    border-color: var(--ev-primary);
    background: #eff6ff;
    color: var(--ev-primary-dark);
}

.save-btn {
    width: 100%;
    min-height: 46px;
    border: 0;
    border-radius: 15px;
    color: #fff;
    background: var(--ev-primary);
    font-weight: 950;
}

.save-btn:hover {
    background: var(--ev-primary-dark);
}

.side-link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    min-height: 44px;
    border-radius: 15px;
    border: 1px solid var(--ev-line);
    background: #fff;
    color: #334155;
    text-decoration: none;
    font-weight: 900;
    margin-top: 10px;
}

.side-link:hover {
    background: #eff6ff;
    color: var(--ev-primary);
}

.insight-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.insight-item,
.history-item {
    border: 1px solid var(--ev-line);
    background: #fbfdff;
    border-radius: 16px;
    padding: 13px;
}

.history-item {
    margin-bottom: 10px;
}

.empty-state,
.no-result {
    border: 1px dashed #cbd5e1;
    background: var(--ev-soft);
    border-radius: 17px;
    padding: 26px;
    text-align: center;
    color: var(--ev-muted);
}

.no-result {
    display: none;
    margin-bottom: 12px;
}

@media (max-width: 1280px) {
    .main-layout {
        grid-template-columns: 280px minmax(0, 1fr);
    }
    .side-panel {
        position: static;
        grid-column: 2;
    }
}

@media (max-width: 992px) {
    .eval-page { padding: 16px 12px 60px; }
    .kpi-row,
    .main-layout,
    .profile-stats,
    .insight-grid {
        grid-template-columns: 1fr;
    }
    .side-panel { grid-column: auto; }
    .criteria-form-row { grid-template-columns: 1fr; }
    .app-list { max-height: none; }
}

@media print {
    .sidebar, .navbar, .left-panel, .ev-actions, .quick-actions, .side-link { display: none !important; }
    .eval-page { background: #fff; padding: 0; }
    .ev-card { box-shadow: none; }
    .main-layout { display: block; }
    .side-panel { position: static; margin-top: 16px; }
}
</style>

<div class="eval-page">
    <div class="eval-wrap">

        <div class="ev-card ev-topbar">
            <div class="ev-title">
                <h2>Evaluation Scores</h2>
                <p>Score applications, review student profile, and save the final decision in one workspace.</p>
            </div>
            <div class="ev-actions">
                <button type="button" class="ev-btn" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print
                </button>
                <button type="button" class="ev-btn ev-btn-primary" id="scrollDecisionBtn">
                    <i class="bi bi-send-check"></i> Final Decision
                </button>
            </div>
        </div>

        <div class="kpi-row">
            <div class="ev-card kpi-card">
                <div class="kpi-label">Total Applications</div>
                <div class="kpi-value"><?= e($totalApplications) ?></div>
                <div class="kpi-note">All applications in queue</div>
            </div>
            <div class="ev-card kpi-card">
                <div class="kpi-label">Pending Review</div>
                <div class="kpi-value"><?= e($pendingApplications) ?></div>
                <div class="kpi-note">Submitted / reviewing / need info</div>
            </div>
            <div class="ev-card kpi-card">
                <div class="kpi-label">Approved</div>
                <div class="kpi-value"><?= e($approvedApplications) ?></div>
                <div class="kpi-note">Applications approved</div>
            </div>
            <div class="ev-card kpi-card">
                <div class="kpi-label">Average Score</div>
                <div class="kpi-value"><?= e($averageScore) ?></div>
                <div class="kpi-note">Based on saved scores</div>
            </div>
        </div>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success rounded-4 border-0 shadow-sm">
                <i class="bi bi-check-circle me-2"></i>
                <?= e($_SESSION['flash_success']) ?>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <div class="main-layout">
            <div class="ev-card panel left-panel">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <div>
                        <h4 class="panel-title">Application Queue</h4>
                        <div class="panel-subtitle">Choose one application to score.</div>
                    </div>
                    <span class="badge text-bg-primary rounded-pill"><?= e($totalApplications) ?></span>
                </div>

                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input id="appSearch" type="text" placeholder="Search name, code, program...">
                </div>

                <div class="filter-row">
                    <button type="button" class="filter-pill active" data-filter="all">All</button>
                    <button type="button" class="filter-pill" data-filter="submitted">Submitted</button>
                    <button type="button" class="filter-pill" data-filter="reviewing">Reviewing</button>
                    <button type="button" class="filter-pill" data-filter="need_more_info">Need Info</button>
                    <button type="button" class="filter-pill" data-filter="approved">Approved</button>
                    <button type="button" class="filter-pill" data-filter="rejected">Rejected</button>
                </div>

                <div class="no-result" id="noResult">No applications found.</div>

                <div class="app-list">
                    <?php foreach ($applications as $app): ?>
                        <a
                            href="evaluation_scores.php?application_id=<?= (int)$app['id'] ?>"
                            class="app-card <?= (int)$app['id'] === $selectedId ? 'active' : '' ?>"
                            data-status="<?= e($app['status']) ?>"
                            data-search="<?= e(strtolower($app['full_name'] . ' ' . $app['student_code'] . ' ' . $app['program_name'] . ' ' . $app['status'])) ?>"
                        >
                            <div class="d-flex justify-content-between gap-2 align-items-start">
                                <div>
                                    <div class="student-name"><?= e($app['full_name']) ?></div>
                                    <div class="student-meta"><?= e($app['student_code']) ?></div>
                                </div>
                                <span class="badge text-bg-<?= statusBadge($app['status']) ?> status-badge">
                                    <?= e(displayText($app['status'])) ?>
                                </span>
                            </div>
                            <div class="student-meta mt-2"><?= e($app['program_name']) ?></div>
                            <div class="mt-3">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-muted">Total score</span>
                                    <strong><?= e($app['total_score']) ?>/100</strong>
                                </div>
                                <div class="score-progress"><span style="width: <?= min(100, (float)$app['total_score']) ?>%"></span></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="score-panel">
                <?php if ($selected): ?>
                    <form method="post" id="scoreForm">
                        <input type="hidden" name="application_id" value="<?= (int)$selectedId ?>">

                        <div class="ev-card panel mb-3">
                            <div class="profile-head">
                                <div>
                                    <h3><?= e($selected['full_name']) ?></h3>
                                    <div class="text-muted fw-semibold">
                                        <?= e($selected['student_code']) ?> · <?= e($selected['program_name']) ?>
                                    </div>
                                </div>
                                <span class="badge text-bg-<?= statusBadge($selected['status']) ?> status-badge fs-6">
                                    <?= e(displayText($selected['status'])) ?>
                                </span>
                            </div>

                            <div class="profile-stats">
                                <div class="profile-stat"><span>GPA</span><strong><?= e($selected['gpa'] ?? 0) ?></strong></div>
                                <div class="profile-stat"><span>Activities</span><strong><?= e($selected['activities_count'] ?? 0) ?></strong></div>
                                <div class="profile-stat"><span>Failed</span><strong><?= e($selected['failed_subjects'] ?? 0) ?></strong></div>
                                <div class="profile-stat"><span>Certificate</span><strong><?= !empty($selected['has_language_cert']) ? 'Yes' : 'No' ?></strong></div>
                            </div>
                        </div>

                        <div class="ev-card panel mb-3">
                            <h4 class="panel-title">Reviewer Scoring</h4>
                            <div class="panel-subtitle">Use auto scores for consistency, or switch to manual score when needed.</div>

                            <div class="quick-actions">
                                <button type="button" class="soft-btn" id="acceptAll"><i class="bi bi-check2-all"></i> Accept all auto</button>
                                <button type="button" class="soft-btn" id="manualMode"><i class="bi bi-pencil-square"></i> Manual mode</button>
                                <button type="button" class="soft-btn" id="fillNotes"><i class="bi bi-chat-left-text"></i> Fill notes</button>
                                <button type="button" class="soft-btn" id="clearNotes"><i class="bi bi-eraser"></i> Clear notes</button>
                            </div>

                            <?php if (!$criteria): ?>
                                <div class="alert alert-warning rounded-4">No scoring criteria found. Please add scoring criteria in admin first.</div>
                            <?php endif; ?>

                            <?php
                            $previewTotal = 0;
                            foreach ($criteria as $c):
                                $criteriaId = (int)$c['id'];
                                $rawAutoScore = round(calculateAutoScore($c['criterion_name'], $c['max_score'], $selected), 1);
                                $autoWeightedScore = calculateWeightedScore($rawAutoScore, $c['max_score'], $c['weight']);
                                $savedScore = $currentScores[$criteriaId]['score'] ?? null;
                                $savedNote = $currentScores[$criteriaId]['note'] ?? '';
                                $displayScore = $savedScore !== null ? $savedScore : $autoWeightedScore;
                                $isAutoChecked = ($savedScore === null || abs((float)$displayScore - (float)$autoWeightedScore) < 0.01);
                                $previewTotal += (float)$displayScore;
                            ?>
                                <div class="criteria-card">
                                    <div class="criteria-top">
                                        <div>
                                            <div class="criteria-name"><?= e($c['criterion_name']) ?></div>
                                            <div class="small-muted">
                                                Weight <?= e($c['weight']) ?> · Max raw <?= e($c['max_score']) ?> · Raw auto <?= e($rawAutoScore) ?>/<?= e($c['max_score']) ?>
                                            </div>
                                        </div>
                                        <span class="auto-chip"><i class="bi bi-calculator"></i> Auto <?= e($autoWeightedScore) ?>/<?= e($c['weight']) ?></span>
                                    </div>

                                    <div class="criteria-form-row">
                                        <div class="form-check form-switch">
                                            <input
                                                class="form-check-input accept-auto"
                                                type="checkbox"
                                                name="accept_auto[<?= $criteriaId ?>]"
                                                value="1"
                                                <?= $isAutoChecked ? 'checked' : '' ?>
                                                data-auto="<?= e($autoWeightedScore) ?>"
                                            >
                                            <label class="form-check-label">Accept auto</label>
                                        </div>

                                        <div>
                                            <label class="small-muted mb-1">Score</label>
                                            <input
                                                type="number"
                                                step="0.1"
                                                min="0"
                                                max="<?= e($c['weight']) ?>"
                                                name="reviewer_score[<?= $criteriaId ?>]"
                                                class="score-input reviewer-score"
                                                value="<?= e($displayScore) ?>"
                                                data-max="<?= e($c['weight']) ?>"
                                                data-auto="<?= e($autoWeightedScore) ?>"
                                            >
                                        </div>

                                        <div>
                                            <label class="small-muted mb-1">Reviewer note</label>
                                            <textarea
                                                name="reviewer_note[<?= $criteriaId ?>]"
                                                class="note-area reviewer-note"
                                                rows="2"
                                                data-default-note="<?= e(autoNote($c['criterion_name'], $selected)) ?>"
                                                placeholder="Write a short professional note..."
                                            ><?= e($savedNote) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="ev-card panel mb-3">
                            <h4 class="panel-title mb-3">Reviewer Insights</h4>
                            <div class="insight-grid">
                                <div class="insight-item">
                                    <strong>Academic profile</strong>
                                    <div class="small-muted mt-1">GPA <?= e($selected['gpa'] ?? 0) ?> is used for academic scoring.</div>
                                </div>
                                <div class="insight-item">
                                    <strong>Activities profile</strong>
                                    <div class="small-muted mt-1"><?= e($selected['activities_count'] ?? 0) ?> activities recorded.</div>
                                </div>
                                <div class="insight-item">
                                    <strong>Risk check</strong>
                                    <div class="small-muted mt-1">Failed: <?= e($selected['failed_subjects'] ?? 0) ?> · Certificate: <?= !empty($selected['has_language_cert']) ? 'Available' : 'Not available' ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="ev-card panel">
                            <h4 class="panel-title mb-3">Decision History</h4>
                            <?php foreach ($decisions as $d): ?>
                                <div class="history-item">
                                    <div class="d-flex justify-content-between flex-wrap gap-2">
                                        <div>
                                            <span class="badge text-bg-<?= statusBadge($d['decision']) ?> status-badge"><?= e(displayText($d['decision'])) ?></span>
                                            <strong class="ms-2"><?= e($d['reviewer_name'] ?? 'Reviewer') ?></strong>
                                        </div>
                                        <small class="text-muted"><?= e($d['created_at']) ?></small>
                                    </div>
                                    <p class="mt-3 mb-0"><?= nl2br(e($d['comment'])) ?></p>
                                </div>
                            <?php endforeach; ?>

                            <?php if (!$decisions): ?>
                                <div class="empty-state">No decision history yet.</div>
                            <?php endif; ?>
                        </div>

                        <div class="ev-card panel side-panel mobile-decision d-lg-none mt-3"></div>
                    </form>
                <?php else: ?>
                    <div class="ev-card panel text-center text-muted">No applications available for scoring.</div>
                <?php endif; ?>
            </div>

            <?php if ($selected): ?>
                <div class="ev-card panel side-panel" id="decisionBox">
                    <div class="total-box">
                        <div class="small-muted fw-bold text-uppercase mb-2">Current Total Score</div>
                        <div class="total-number"><span id="liveTotal"><?= e(round($previewTotal, 1)) ?></span></div>
                        <div class="text-muted fw-bold mb-3">/100 points</div>
                        <div class="score-progress"><span id="liveBar" style="width: <?= min(100, round($previewTotal, 1)) ?>%"></span></div>
                    </div>

                    <h4 class="panel-title">Final Decision</h4>
                    <div class="panel-subtitle">Choose only when scoring is ready.</div>

                    <div class="decision-grid">
                        <label class="decision-option">
                            <input type="radio" form="scoreForm" name="decision" value="approved">
                            <span><i class="bi bi-check-circle"></i> Approve</span>
                        </label>
                        <label class="decision-option">
                            <input type="radio" form="scoreForm" name="decision" value="need_more_info">
                            <span><i class="bi bi-question-circle"></i> Need more info</span>
                        </label>
                        <label class="decision-option">
                            <input type="radio" form="scoreForm" name="decision" value="rejected">
                            <span><i class="bi bi-x-circle"></i> Reject</span>
                        </label>
                    </div>

                    <textarea
                        form="scoreForm"
                        name="decision_comment"
                        id="decisionComment"
                        class="note-area mb-3"
                        rows="4"
                        placeholder="Write final decision comment..."
                    ></textarea>

                    <button type="submit" form="scoreForm" class="save-btn">
                        <i class="bi bi-save2"></i> Save Review
                    </button>

                    <a href="recommendations.php?application_id=<?= (int)$selectedId ?>" class="side-link">
                        <i class="bi bi-lightbulb"></i> View Recommendation
                    </a>
                    <a href="evidence_verification.php?application_id=<?= (int)$selectedId ?>" class="side-link">
                        <i class="bi bi-file-earmark-check"></i> Check Evidence
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const appSearch = document.getElementById('appSearch');
    const appCards = Array.from(document.querySelectorAll('.app-card'));
    const filterButtons = Array.from(document.querySelectorAll('.filter-pill'));
    const noResult = document.getElementById('noResult');
    let activeFilter = 'all';

    function filterApplications() {
        const keyword = (appSearch ? appSearch.value : '').toLowerCase().trim();
        let visibleCount = 0;

        appCards.forEach(card => {
            const text = card.dataset.search || '';
            const status = card.dataset.status || '';
            const matchText = text.includes(keyword);
            const matchStatus = activeFilter === 'all' || status === activeFilter;
            card.style.display = matchText && matchStatus ? '' : 'none';
            if (matchText && matchStatus) visibleCount++;
        });

        if (noResult) noResult.style.display = visibleCount === 0 ? 'block' : 'none';
    }

    if (appSearch) appSearch.addEventListener('input', filterApplications);

    filterButtons.forEach(button => {
        button.addEventListener('click', function () {
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            activeFilter = this.dataset.filter || 'all';
            filterApplications();
        });
    });

    const scoreInputs = Array.from(document.querySelectorAll('.reviewer-score'));
    const autoSwitches = Array.from(document.querySelectorAll('.accept-auto'));
    const noteAreas = Array.from(document.querySelectorAll('.reviewer-note'));
    const liveTotal = document.getElementById('liveTotal');
    const liveBar = document.getElementById('liveBar');

    function recalculateTotal() {
        let total = 0;
        scoreInputs.forEach(input => {
            const max = parseFloat(input.dataset.max || '0');
            let value = parseFloat(input.value || '0');
            if (Number.isNaN(value)) value = 0;
            if (value < 0) value = 0;
            if (value > max) value = max;
            value = Math.round(value * 10) / 10;
            input.value = value;
            total += value;
        });
        total = Math.round(total * 10) / 10;
        if (liveTotal) liveTotal.textContent = total.toFixed(1);
        if (liveBar) liveBar.style.width = Math.min(100, total) + '%';
    }

    scoreInputs.forEach(input => {
        input.addEventListener('input', function () {
            const card = this.closest('.criteria-card');
            const autoSwitch = card ? card.querySelector('.accept-auto') : null;
            if (autoSwitch) {
                const currentValue = parseFloat(this.value || '0');
                const autoValue = parseFloat(autoSwitch.dataset.auto || '0');
                if (Math.abs(currentValue - autoValue) > 0.01) autoSwitch.checked = false;
            }
            recalculateTotal();
        });
    });

    autoSwitches.forEach(sw => {
        sw.addEventListener('change', function () {
            const card = this.closest('.criteria-card');
            if (!card) return;
            const input = card.querySelector('.reviewer-score');
            const note = card.querySelector('.reviewer-note');
            if (this.checked && input) input.value = this.dataset.auto || '0';
            if (!this.checked && note) note.placeholder = 'Explain why you edited this score manually...';
            recalculateTotal();
        });
    });

    const acceptAll = document.getElementById('acceptAll');
    if (acceptAll) acceptAll.addEventListener('click', function () {
        autoSwitches.forEach(sw => {
            sw.checked = true;
            const input = sw.closest('.criteria-card')?.querySelector('.reviewer-score');
            if (input) input.value = sw.dataset.auto || '0';
        });
        recalculateTotal();
    });

    const manualMode = document.getElementById('manualMode');
    if (manualMode) manualMode.addEventListener('click', function () {
        autoSwitches.forEach(sw => sw.checked = false);
        if (scoreInputs.length > 0) scoreInputs[0].focus();
    });

    const clearNotes = document.getElementById('clearNotes');
    if (clearNotes) clearNotes.addEventListener('click', function () {
        noteAreas.forEach(note => note.value = '');
    });

    const fillNotes = document.getElementById('fillNotes');
    if (fillNotes) fillNotes.addEventListener('click', function () {
        noteAreas.forEach(note => {
            if (note.value.trim() === '') note.value = note.dataset.defaultNote || 'Reviewed based on submitted profile and scoring criteria.';
        });
    });

    const decisionRadios = Array.from(document.querySelectorAll('input[name="decision"]'));
    const decisionComment = document.getElementById('decisionComment');

    decisionRadios.forEach(radio => {
        radio.addEventListener('change', function () {
            if (!decisionComment || decisionComment.value.trim() !== '') return;
            if (this.value === 'approved') decisionComment.value = 'Application meets the scholarship requirements and is recommended for approval.';
            if (this.value === 'rejected') decisionComment.value = 'Application does not fully meet the required evaluation criteria at this stage.';
            if (this.value === 'need_more_info') decisionComment.value = 'Additional information or supporting documents are required before making the final decision.';
        });
    });

    const scrollDecisionBtn = document.getElementById('scrollDecisionBtn');
    const decisionBox = document.getElementById('decisionBox');
    if (scrollDecisionBtn && decisionBox) {
        scrollDecisionBtn.addEventListener('click', function () {
            decisionBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }

    const scoreForm = document.getElementById('scoreForm');
    if (scoreForm) {
        scoreForm.addEventListener('submit', function (event) {
            const selectedDecision = document.querySelector('input[name="decision"]:checked');
            const comment = decisionComment ? decisionComment.value.trim() : '';
            if (selectedDecision && comment === '') {
                event.preventDefault();
                alert('Please write a decision comment before saving a final decision.');
                decisionComment.focus();
            }
        });
    }

    filterApplications();
    recalculateTotal();
});
</script>

<?php require_once '../includes/footer.php'; ?>
