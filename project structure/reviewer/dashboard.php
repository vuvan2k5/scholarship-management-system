<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pageTitle = 'Reviewer Dashboard';

require_once '../config/db.php';
require_once '../includes/auth.php';

requireLogin();
requireRole('reviewer');

$pdo = getDB();
$reviewerId = function_exists('currentUserId') ? currentUserId() : (int)($_SESSION['user_id'] ?? 0);

function tableExists($pdo, $table) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

if (isset($_GET['mark_notifications_read'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = ?
        ");
        $stmt->execute([$reviewerId]);
    } catch (Exception $e) {}

    header('Location: dashboard.php');
    exit;
}

require_once '../includes/header.php';
require_once '../includes/navbar.php';
require_once '_reviewer_common.php';

reviewerCss();

function safeCount($pdo, $sql) {
    try {
        return (int)$pdo->query($sql)->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

$totalApplications = safeCount($pdo, "SELECT COUNT(*) FROM applications");

$pendingEvidence = safeCount($pdo, "
    SELECT COUNT(*) FROM application_evidence 
    WHERE status IN ('pending','uploaded')
");

$verifiedEvidence = safeCount($pdo, "
    SELECT COUNT(*) FROM application_evidence 
    WHERE status IN ('verified','accepted')
");

$rejectedEvidence = safeCount($pdo, "
    SELECT COUNT(*) FROM application_evidence 
    WHERE status = 'rejected'
");

$scoredApplications = safeCount($pdo, "
    SELECT COUNT(DISTINCT application_id) FROM evaluation_scores
");

$notScoredApplications = max(0, $totalApplications - $scoredApplications);

$completedReviews = safeCount($pdo, "
    SELECT COUNT(*) FROM applications 
    WHERE status IN ('approved','rejected')
");

$activeReviews = safeCount($pdo, "
    SELECT COUNT(*) FROM applications 
    WHERE status IN ('submitted','reviewing','pending','need_more_info','eligible','ineligible')
");

try {
    $avgScore = $pdo->query("SELECT ROUND(AVG(score), 1) FROM evaluation_scores")->fetchColumn();
    $avgScore = $avgScore ?: 0;
} catch (Exception $e) {
    $avgScore = 0;
}

$completionRate = $totalApplications > 0 ? round(($completedReviews / $totalApplications) * 100) : 0;
$scoringRate = $totalApplications > 0 ? round(($scoredApplications / $totalApplications) * 100) : 0;
$evidenceTotalAll = $pendingEvidence + $verifiedEvidence + $rejectedEvidence;
$evidenceRate = $evidenceTotalAll > 0 ? round(($verifiedEvidence / $evidenceTotalAll) * 100) : 0;

try {
    $applications = $pdo->query("
        SELECT 
            a.id,
            a.status,
            a.submitted_at,
            u.full_name,
            u.student_code,
            sp.name AS program_name,
            profile.gpa,
            COALESCE(ROUND(AVG(es.score),1), 0) AS avg_score,
            COUNT(DISTINCT ev.id) AS evidence_total,
            SUM(CASE WHEN ev.status IN ('pending','uploaded') THEN 1 ELSE 0 END) AS evidence_pending
        FROM applications a
        JOIN users u ON u.id = a.student_id
        JOIN scholarship_programs sp ON sp.id = a.program_id
        LEFT JOIN student_profiles profile ON profile.student_id = u.id
        LEFT JOIN evaluation_scores es ON es.application_id = a.id
        LEFT JOIN application_evidence ev ON ev.application_id = a.id
        WHERE a.status IN ('submitted','reviewing','pending','need_more_info','eligible','ineligible','approved','rejected','disbursed')
        GROUP BY a.id
        ORDER BY 
            CASE 
                WHEN SUM(CASE WHEN ev.status IN ('pending','uploaded') THEN 1 ELSE 0 END) > 0 THEN 1
                WHEN COALESCE(AVG(es.score), 0) = 0 THEN 2
                WHEN a.status IN ('submitted','reviewing','pending','need_more_info','eligible','ineligible') THEN 3
                ELSE 4
            END,
            a.submitted_at DESC
    ")->fetchAll();
} catch (Exception $e) {
    $applications = [];
}

$notifications = [];

if (tableExists($pdo, 'notifications')) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, title, message, type, created_at, is_read
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 8
        ");
        $stmt->execute([$reviewerId]);
        $notifications = $stmt->fetchAll();
    } catch (Exception $e) {
        $notifications = [];
    }
}

$unreadNotifications = 0;
foreach ($notifications as $n) {
    if ((int)($n['is_read'] ?? 0) === 0) {
        $unreadNotifications++;
    }
}

function notificationLink($notification) {
    $type = strtolower((string)($notification['type'] ?? ''));
    $title = strtolower((string)($notification['title'] ?? ''));
    $message = strtolower((string)($notification['message'] ?? ''));
    $text = $type . ' ' . $title . ' ' . $message;

    if (strpos($text, 'evidence') !== false || strpos($text, 'document') !== false || strpos($text, 'verification') !== false) {
        return 'evidence_verification.php?filter=pending';
    }

    if (strpos($text, 'score') !== false || strpos($text, 'scoring') !== false || strpos($text, 'evaluation') !== false) {
        return 'evaluation_scores.php';
    }

    if (strpos($text, 'recommend') !== false || strpos($text, 'admin') !== false) {
        return 'recommendations.php';
    }

    if (strpos($text, 'review') !== false || strpos($text, 'application') !== false) {
        return '#reviewQueueSection';
    }

    return '#reviewQueueSection';
}

$currentName = $_SESSION['full_name'] ?? $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Reviewer';
$logoutLink = '../logout.php';
?>

<style>
:root {
    --rv-bg: #f5f7fb;
    --rv-card: #ffffff;
    --rv-text: #0f172a;
    --rv-muted: #64748b;
    --rv-line: #e2e8f0;
    --rv-primary: #2563eb;
    --rv-primary-dark: #1d4ed8;
    --rv-danger: #dc2626;
    --rv-success: #16a34a;
    --rv-warning: #d97706;
    --rv-shadow: 0 12px 28px rgba(15, 23, 42, .07);
}

html,
body {
    width: 100%;
    max-width: 100%;
    min-height: 100%;
    overflow-x: hidden !important;
    background: var(--rv-bg);
}

* {
    box-sizing: border-box;
}

body,
.main-content,
.content-wrapper,
.page-wrapper,
.app-content,
.container,
.container-fluid {
    max-width: 100%;
    overflow-x: hidden !important;
}

.reviewer-page {
    width: 100%;
    max-width: 100%;
    min-height: 100vh;
    background: var(--rv-bg);
    color: var(--rv-text);
    padding: 18px 18px 60px;
}

.reviewer-inner {
    width: 100%;
    max-width: 100%;
    margin: 0 auto;
}

.rv-card {
    background: var(--rv-card);
    border: 1px solid var(--rv-line);
    border-radius: 20px;
    box-shadow: var(--rv-shadow);
}

.rv-topbar {
    padding: 16px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    flex-wrap: nowrap;
    margin-bottom: 18px;
}

.rv-title {
    min-width: 0;
    flex: 1;
}

.rv-title h2 {
    margin: 0;
    font-size: clamp(22px, 2vw, 30px);
    font-weight: 950;
    line-height: 1.1;
}

.rv-title p {
    margin: 6px 0 0;
    color: var(--rv-muted);
    font-size: 14px;
    font-weight: 600;
}

.rv-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 9px;
    flex: 0 1 auto;
    min-width: 0;
}

.rv-search {
    position: relative;
    width: 280px;
    max-width: 28vw;
    flex-shrink: 1;
}

.rv-search i {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--rv-muted);
}

.rv-search input {
    width: 100%;
    min-height: 42px;
    border: 1px solid var(--rv-line);
    border-radius: 14px;
    padding: 9px 12px 9px 38px;
    outline: none;
}

.rv-search input:focus,
.rv-toolbar input:focus,
.rv-toolbar select:focus {
    border-color: var(--rv-primary);
    box-shadow: 0 0 0 4px rgba(37, 99, 235, .1);
}

.rv-btn {
    min-height: 42px;
    border-radius: 14px;
    padding: 9px 13px;
    border: 1px solid var(--rv-line);
    background: #fff;
    color: var(--rv-text);
    font-weight: 850;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    white-space: nowrap;
    cursor: pointer;
}

.rv-btn:hover {
    background: #f8fafc;
    color: var(--rv-primary);
}

.rv-btn-primary {
    background: var(--rv-primary);
    color: #fff;
    border-color: var(--rv-primary);
}

.rv-btn-primary:hover {
    background: var(--rv-primary-dark);
    color: #fff;
}

.rv-bell-wrap {
    position: relative;
    flex-shrink: 0;
}

.rv-bell {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    border: 1px solid var(--rv-line);
    background: #fff;
    position: relative;
}

.rv-badge {
    position: absolute;
    top: -7px;
    right: -7px;
    min-width: 21px;
    height: 21px;
    border-radius: 999px;
    background: var(--rv-danger);
    color: #fff;
    font-size: 11px;
    font-weight: 900;
    display: flex;
    align-items: center;
    justify-content: center;
}

.rv-notify-panel {
    display: none;
    position: fixed;
    top: 90px;
    right: 20px;
    width: 380px;
    max-height: 460px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid var(--rv-line);
    border-radius: 18px;
    box-shadow: 0 24px 70px rgba(15,23,42,.2);
    z-index: 999999;
}

.rv-notify-panel.show {
    display: block;
}

.rv-notify-head,
.rv-notify-item {
    padding: 14px 15px;
    border-bottom: 1px solid #f1f5f9;
}

.rv-notify-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.rv-notify-item.unread {
    background: #f8fbff;
}

.rv-notify-link {
    display: block;
    color: inherit;
    text-decoration: none;
    cursor: pointer;
}

.rv-notify-link:hover {
    background: #eef4ff;
    color: inherit;
}

.rv-type {
    font-size: 11px;
    font-weight: 900;
    color: var(--rv-primary);
    text-transform: uppercase;
    letter-spacing: .6px;
}

.rv-metric {
    padding: 18px;
    height: 100%;
    min-width: 0;
}

.rv-metric-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.rv-label {
    color: var(--rv-muted);
    font-size: 12px;
    font-weight: 900;
}

.rv-value {
    font-size: clamp(28px, 3vw, 38px);
    font-weight: 950;
    margin-top: 4px;
    line-height: 1;
}

.rv-icon {
    width: 46px;
    height: 46px;
    border-radius: 15px;
    background: #eef4ff;
    color: var(--rv-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.rv-note,
.rv-muted {
    color: var(--rv-muted);
    font-size: 13px;
}

.row {
    --bs-gutter-x: 1rem;
}

.rv-section {
    padding: 20px;
    min-width: 0;
}

.rv-section-title {
    font-size: 20px;
    font-weight: 950;
    margin: 0;
}

.rv-toolbar {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) minmax(130px, 160px) minmax(130px, 160px) auto;
    gap: 10px;
    margin: 16px 0;
    align-items: center;
}

.rv-toolbar input,
.rv-toolbar select {
    width: 100%;
    min-width: 0;
    min-height: 42px;
    border-radius: 14px;
    border: 1px solid var(--rv-line);
    padding: 9px 12px;
    outline: none;
}

.rv-table-box {
    width: 100%;
    max-width: 100%;
    border: 1px solid var(--rv-line);
    border-radius: 20px;
    overflow: hidden;
    background: #fff;
}

.rv-queue-head,
.rv-queue-row {
    display: grid;
    grid-template-columns: minmax(170px, 1.25fr) minmax(155px, 1fr) 96px 105px 112px 94px;
    align-items: center;
    gap: 12px;
}

.rv-queue-head {
    padding: 13px 14px;
    background: #f8fafc;
    border-bottom: 1px solid var(--rv-line);
    color: var(--rv-muted);
    font-size: 10px;
    font-weight: 950;
    text-transform: uppercase;
    letter-spacing: .6px;
}

.rv-queue-row {
    padding: 15px 14px;
    border-bottom: 1px solid #eef2f7;
}

.rv-queue-row:last-child {
    border-bottom: 0;
}

.rv-queue-row:hover {
    background: #fbfdff;
}

.rv-cell {
    min-width: 0;
}

.rv-cell-label {
    display: none;
}

.rv-student-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 10px;
    margin-top: 5px;
    color: var(--rv-muted);
    font-size: 12px;
    font-weight: 700;
}

.rv-program-text {
    color: #334155;
    font-weight: 700;
}

.rv-score-box {
    width: 100%;
    min-width: 0;
}

.rv-score-box .fw-bold {
    font-size: 13px;
}

.rv-status-stack {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
}

.rv-action-btn {
    min-height: 36px;
    width: 100%;
    border-radius: 12px;
    padding: 7px 8px;
    justify-content: center;
    white-space: nowrap;
    font-size: 12px;
}

.rv-student {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.rv-avatar {
    width: 38px;
    height: 38px;
    border-radius: 13px;
    background: #e0ecff;
    color: var(--rv-primary);
    font-weight: 900;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.rv-student > div:last-child {
    min-width: 0;
}

.rv-name,
.rv-program-text {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    white-space: normal;
    overflow: hidden;
    overflow-wrap: anywhere;
    word-break: break-word;
    line-height: 1.3;
}

.rv-pill {
    border-radius: 999px;
    padding: 5px 8px;
    font-size: 11px;
    font-weight: 900;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

.rv-high {
    background: #fef2f2;
    color: #b91c1c;
}

.rv-medium {
    background: #fffbeb;
    color: #92400e;
}

.rv-normal {
    background: #f0fdf4;
    color: #166534;
}

.badge {
    white-space: normal;
    line-height: 1.25;
}

.progress {
    height: 7px;
    border-radius: 999px;
    background: #e5e7eb;
}

.progress-bar {
    border-radius: 999px;
    background: var(--rv-primary);
}

.rv-toggle-wrap {
    display: flex;
    justify-content: center;
    margin-top: 16px;
}

.rv-side-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}

.rv-side-row:last-child {
    border-bottom: 0;
}

.rv-quick {
    display: grid;
    gap: 10px;
}

.rv-quick a {
    text-decoration: none;
    color: var(--rv-text);
    border: 1px solid var(--rv-line);
    border-radius: 15px;
    padding: 12px 13px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 900;
}

.rv-quick a:hover {
    color: var(--rv-primary);
    background: #f8fafc;
}

.rv-activity {
    display: flex;
    gap: 11px;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}

.rv-activity:last-child {
    border-bottom: 0;
}

.rv-activity-icon {
    width: 34px;
    height: 34px;
    border-radius: 12px;
    background: #f1f5f9;
    color: var(--rv-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.rv-focus-flash {
    outline: 4px solid rgba(37, 99, 235, .18);
    transition: outline .25s ease;
}

.rv-footer {
    text-align: center;
    color: var(--rv-muted);
    font-size: 13px;
    padding: 22px 0 0;
}

@media (max-width: 1280px) {
    .reviewer-page {
        padding: 14px 14px 50px;
    }

    .rv-section {
        padding: 18px;
    }

    .rv-search {
        width: 230px;
        max-width: 22vw;
    }

    .rv-queue-head,
    .rv-queue-row {
        grid-template-columns: minmax(180px, 1.25fr) minmax(150px, 1fr) minmax(170px, .9fr) 96px;
        gap: 12px;
    }

    .rv-hide-md {
        display: none;
    }

    .rv-status-stack {
        flex-direction: row;
        flex-wrap: wrap;
        align-items: center;
    }
}

@media (max-width: 1100px) {
    .rv-queue-head {
        display: none;
    }

    .rv-table-box {
        border: 0;
        background: transparent;
        display: grid;
        gap: 12px;
    }

    .rv-queue-row {
        grid-template-columns: 1fr;
        align-items: start;
        gap: 12px;
        padding: 16px;
        border: 1px solid var(--rv-line);
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
    }

    .rv-cell-label {
        display: block;
        color: var(--rv-muted);
        font-size: 10px;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: .5px;
        margin-bottom: 5px;
    }

    .rv-hide-md {
        display: block;
    }

    .rv-status-stack {
        flex-direction: row;
        flex-wrap: wrap;
    }

    .rv-action-btn {
        width: auto;
        min-width: 150px;
    }
}

@media (max-width: 992px) {
    .reviewer-page {
        padding: 14px 12px 70px;
    }

    .rv-topbar {
        flex-wrap: wrap;
        align-items: flex-start;
    }

    .rv-actions,
    .rv-search {
        width: 100%;
        max-width: 100%;
    }

    .rv-actions {
        justify-content: flex-start;
        flex-wrap: wrap;
    }

    .rv-toolbar {
        grid-template-columns: 1fr;
    }

    .rv-notify-panel {
        left: 12px;
        right: 12px;
        width: auto;
    }
}
</style>

<div class="container-fluid reviewer-page">
    <div class="reviewer-inner">

        <div class="rv-card rv-topbar">
            <div class="rv-title">
                <h2>Reviewer Dashboard</h2>
                <p>
                    Welcome back, <?= e($currentName) ?>. Review applications, verify evidence, and manage scoring tasks.
                </p>
            </div>

            <div class="rv-actions">
                <div class="rv-search">
                    <i class="bi bi-search"></i>
                    <input 
                        type="text" 
                        id="globalSearch" 
                        placeholder="Search student, code, or program..."
                    >
                </div>

                <div class="rv-bell-wrap">
                    <button class="rv-bell" id="bellButton" type="button" title="Notifications">
                        <i class="bi bi-bell"></i>
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="rv-badge"><?= $unreadNotifications ?></span>
                        <?php endif; ?>
                    </button>

                    <div class="rv-notify-panel" id="notificationPanel">
                        <div class="rv-notify-head">
                            <strong>Notifications</strong>
                            <span class="rv-muted"><?= $unreadNotifications ?> unread</span>
                            <?php if ($unreadNotifications > 0): ?>
                                <a href="dashboard.php?mark_notifications_read=1" class="rv-muted small text-decoration-none">
                                    Mark read
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php foreach ($notifications as $n): ?>
                            <?php $notifyHref = notificationLink($n); ?>
                            <a 
                                href="<?= e($notifyHref) ?>" 
                                class="rv-notify-item rv-notify-link <?= ((int)($n['is_read'] ?? 0) === 0) ? 'unread' : '' ?>"
                                data-notify-link="<?= e($notifyHref) ?>"
                            >
                                <div class="rv-type"><?= e($n['type'] ?? 'System') ?></div>
                                <div class="fw-bold mt-1"><?= e($n['title'] ?? 'Notification') ?></div>
                                <div class="rv-muted mt-1"><?= e($n['message'] ?? '') ?></div>
                                <div class="text-muted small mt-2">
                                    <?= e(date('d M Y, H:i', strtotime($n['created_at'] ?? date('Y-m-d H:i:s')))) ?>
                                </div>
                            </a>
                        <?php endforeach; ?>

                        <?php if (!$notifications): ?>
                            <div class="p-4 text-center text-muted">No notifications.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <a href="evidence_verification.php" class="rv-btn rv-btn-primary">
                    <i class="bi bi-file-earmark-check"></i>
                    Verify
                </a>

                <a href="<?= e($logoutLink) ?>" class="rv-btn">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="rv-card rv-metric">
                    <div class="rv-metric-head">
                        <div>
                            <div class="rv-label">Need Review</div>
                            <div class="rv-value"><?= $activeReviews ?></div>
                        </div>
                        <div class="rv-icon"><i class="bi bi-folder2-open"></i></div>
                    </div>
                    <div class="rv-note mt-2">Submitted or reviewing applications</div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="rv-card rv-metric">
                    <div class="rv-metric-head">
                        <div>
                            <div class="rv-label">Pending Evidence</div>
                            <div class="rv-value"><?= $pendingEvidence ?></div>
                        </div>
                        <div class="rv-icon"><i class="bi bi-file-earmark-text"></i></div>
                    </div>
                    <div class="rv-note mt-2">Documents waiting for verification</div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="rv-card rv-metric">
                    <div class="rv-metric-head">
                        <div>
                            <div class="rv-label">Not Scored</div>
                            <div class="rv-value"><?= $notScoredApplications ?></div>
                        </div>
                        <div class="rv-icon"><i class="bi bi-pencil-square"></i></div>
                    </div>
                    <div class="rv-note mt-2">Applications need evaluation score</div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="rv-card rv-metric">
                    <div class="rv-metric-head">
                        <div>
                            <div class="rv-label">Completed</div>
                            <div class="rv-value"><?= $completedReviews ?></div>
                        </div>
                        <div class="rv-icon"><i class="bi bi-check2-circle"></i></div>
                    </div>
                    <div class="rv-note mt-2"><?= $completionRate ?>% of total applications</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="rv-card rv-section" id="reviewQueueSection">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <h4 class="rv-section-title">Review Queue</h4>
                            <div class="rv-muted mt-1">Search, filter, and continue the next required action.</div>
                        </div>

                        <a href="evidence_verification.php" class="rv-btn rv-btn-primary">
                            <i class="bi bi-arrow-right-circle"></i>
                            Open Full Queue
                        </a>
                    </div>

                    <div class="rv-toolbar">
                        <input 
                            type="text" 
                            id="tableSearch" 
                            placeholder="Search in table..."
                        >

                        <select id="statusFilter">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="submitted">Submitted</option>
                            <option value="reviewing">Reviewing</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>

                        <select id="priorityFilter">
                            <option value="">All Priority</option>
                            <option value="high">High Priority</option>
                            <option value="medium">Medium Priority</option>
                            <option value="normal">Normal</option>
                        </select>

                        <button type="button" class="rv-btn" id="resetFilter">
                            <i class="bi bi-arrow-clockwise"></i>
                            Reset
                        </button>
                    </div>

                    <div class="rv-table-box" id="reviewQueueList">
                        <div class="rv-queue-head">
                            <div>Student</div>
                            <div>Program</div>
                            <div class="rv-hide-md">Evidence</div>
                            <div>Score</div>
                            <div class="rv-hide-md">Status</div>
                            <div>Action</div>
                        </div>

                        <?php foreach ($applications as $index => $app): ?>
                            <?php
                                $evidenceTotal = (int)$app['evidence_total'];
                                $evidencePending = (int)$app['evidence_pending'];
                                $evidenceChecked = max(0, $evidenceTotal - $evidencePending);
                                $score = (float)$app['avg_score'];
                                $gpa = $app['gpa'] !== null ? (float)$app['gpa'] : 0;

                                if ($evidencePending > 0 || $score <= 0) {
                                    $priority = 'high';
                                    $priorityText = 'High';
                                    $priorityClass = 'rv-high';
                                } elseif ($gpa >= 3.5 || $score >= 80) {
                                    $priority = 'medium';
                                    $priorityText = 'Medium';
                                    $priorityClass = 'rv-medium';
                                } else {
                                    $priority = 'normal';
                                    $priorityText = 'Normal';
                                    $priorityClass = 'rv-normal';
                                }

                                if ($app['status'] === 'approved' || $app['status'] === 'rejected') {
                                    $actionText = 'View';
                                    $actionClass = 'btn-outline-secondary';
                                    $actionIcon = 'bi-eye';
                                    $actionLink = 'evidence_verification.php?application_id=' . (int)$app['id'];
                                } elseif ($evidencePending > 0) {
                                    $actionText = 'Verify';
                                    $actionClass = 'btn-warning';
                                    $actionIcon = 'bi-file-check';
                                    $actionLink = 'evidence_verification.php?application_id=' . (int)$app['id'];
                                } elseif ($score <= 0) {
                                    $actionText = 'Score';
                                    $actionClass = 'btn-primary';
                                    $actionIcon = 'bi-star';
                                    $actionLink = 'evaluation_scores.php?application_id=' . (int)$app['id'];
                                } else {
                                    $actionText = 'Recommend';
                                    $actionClass = 'btn-success';
                                    $actionIcon = 'bi-award';
                                    $actionLink = 'recommendations.php?application_id=' . (int)$app['id'];
                                }

                                $searchData = strtolower(
                                    ($app['full_name'] ?? '') . ' ' .
                                    ($app['student_code'] ?? '') . ' ' .
                                    ($app['program_name'] ?? '')
                                );
                            ?>

                            <div 
                                class="rv-queue-row review-row"
                                data-index="<?= $index ?>"
                                data-search="<?= e($searchData) ?>"
                                data-status="<?= e(strtolower($app['status'])) ?>"
                                data-priority="<?= e($priority) ?>"
                            >
                                <div class="rv-cell rv-cell-student">
                                    <div class="rv-student">
                                        <div class="rv-avatar">
                                            <?= e(strtoupper(substr($app['full_name'] ?? 'R', 0, 1))) ?>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="fw-bold rv-name"><?= e($app['full_name'] ?? 'Unknown Student') ?></div>
                                            <div class="rv-student-meta">
                                                <span><?= e($app['student_code'] ?? 'N/A') ?></span>
                                                <span>GPA: <strong><?= e($app['gpa'] ?? 'N/A') ?></strong></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="rv-cell">
                                    <span class="rv-cell-label">Program</span>
                                    <div class="rv-program-text"><?= e($app['program_name'] ?? 'N/A') ?></div>
                                </div>

                                <div class="rv-cell rv-hide-md">
                                    <span class="rv-cell-label">Evidence</span>
                                    <span class="badge text-bg-<?= $evidencePending > 0 ? 'warning' : 'success' ?> rv-pill">
                                        <?= $evidenceChecked ?>/<?= $evidenceTotal ?> checked
                                    </span>
                                </div>

                                <div class="rv-cell">
                                    <span class="rv-cell-label">Score</span>
                                    <div class="rv-score-box">
                                        <div class="fw-bold"><?= e($score) ?>/100</div>
                                        <div class="progress mt-1">
                                            <div class="progress-bar" style="width: <?= min(100, max(0, $score)) ?>%"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="rv-cell rv-hide-md">
                                    <span class="rv-cell-label">Status</span>
                                    <div class="rv-status-stack">
                                        <span class="rv-pill <?= $priorityClass ?>">
                                            <i class="bi bi-flag-fill"></i><?= $priorityText ?>
                                        </span>
                                        <span class="badge text-bg-<?= statusBadge($app['status']) ?> rv-pill">
                                            <?= e(ucfirst($app['status'])) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="rv-cell rv-cell-action">
                                    <span class="rv-cell-label">Action</span>
                                    <a href="<?= e($actionLink) ?>" class="btn btn-sm <?= $actionClass ?> fw-bold rv-action-btn">
                                        <i class="bi <?= $actionIcon ?> me-1"></i><?= $actionText ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (!$applications): ?>
                            <div class="text-center text-muted py-5">No applications found.</div>
                        <?php endif; ?>
                    </div>

                    <?php if (count($applications) > 5): ?>
                        <div class="rv-toggle-wrap">
                            <button type="button" class="rv-btn" id="toggleQueueBtn">
                                <i class="bi bi-list-ul"></i>
                                Show all students
                            </button>
                        </div>
                    <?php endif; ?>

                    <div id="emptyState" class="text-center text-muted py-4 d-none">
                        No matching applications found.
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="rv-card rv-section mb-4">
                    <h4 class="rv-section-title">Workflow Progress</h4>
                    <div class="rv-muted mt-1 mb-3">Current processing overview</div>

                    <div class="rv-side-row">
                        <span>Evidence Verified</span>
                        <strong><?= $evidenceRate ?>%</strong>
                    </div>
                    <div class="progress mb-3">
                        <div class="progress-bar" style="width: <?= $evidenceRate ?>%"></div>
                    </div>

                    <div class="rv-side-row">
                        <span>Scoring Progress</span>
                        <strong><?= $scoringRate ?>%</strong>
                    </div>
                    <div class="progress mb-3">
                        <div class="progress-bar" style="width: <?= $scoringRate ?>%"></div>
                    </div>

                    <div class="rv-side-row">
                        <span>Review Completion</span>
                        <strong><?= $completionRate ?>%</strong>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width: <?= $completionRate ?>%"></div>
                    </div>
                </div>

                <div class="rv-card rv-section mb-4">
                    <h4 class="rv-section-title">Quick Actions</h4>
                    <div class="rv-muted mt-1 mb-3">Common reviewer tasks</div>

                    <div class="rv-quick">
                        <a href="evidence_verification.php">
                            <span><i class="bi bi-file-earmark-check me-2"></i>Verify Evidence</span>
                            <i class="bi bi-chevron-right"></i>
                        </a>

                        <a href="evaluation_scores.php">
                            <span><i class="bi bi-star me-2"></i>Evaluation Scores</span>
                            <i class="bi bi-chevron-right"></i>
                        </a>

                        <a href="recommendations.php">
                            <span><i class="bi bi-award me-2"></i>Recommendations</span>
                            <i class="bi bi-chevron-right"></i>
                        </a>

                        <a href="review_analytics.php">
                            <span><i class="bi bi-bar-chart me-2"></i>Review Analytics</span>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>

                <div class="rv-card rv-section mb-4">
                    <h4 class="rv-section-title">Evidence Status</h4>

                    <div class="rv-side-row">
                        <span>Pending</span>
                        <strong><?= $pendingEvidence ?></strong>
                    </div>

                    <div class="rv-side-row">
                        <span>Verified</span>
                        <strong><?= $verifiedEvidence ?></strong>
                    </div>

                    <div class="rv-side-row">
                        <span>Rejected</span>
                        <strong><?= $rejectedEvidence ?></strong>
                    </div>
                </div>

                <div class="rv-card rv-section">
                    <h4 class="rv-section-title">Recent Activity</h4>

                    <?php if ($notifications): ?>
                        <?php foreach (array_slice($notifications, 0, 4) as $activity): ?>
                            <div class="rv-activity">
                                <div class="rv-activity-icon"><i class="bi bi-bell"></i></div>
                                <div>
                                    <div class="fw-bold"><?= e($activity['title'] ?? 'Notification') ?></div>
                                    <div class="rv-muted">
                                        <?= e(date('d M Y, H:i', strtotime($activity['created_at'] ?? date('Y-m-d H:i:s')))) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="rv-activity">
                            <div class="rv-activity-icon"><i class="bi bi-inbox"></i></div>
                            <div>
                                <div class="fw-bold">No recent notifications</div>
                                <div class="rv-muted">Waiting for new updates</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="rv-footer">
            © <?= date('Y') ?> Scholarship Management System. Reviewer workspace.
        </div>
    </div>
</div>

<script>
const bellButton = document.getElementById('bellButton');
const notificationPanel = document.getElementById('notificationPanel');

if (bellButton && notificationPanel) {
    bellButton.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationPanel.classList.toggle('show');
    });

    notificationPanel.addEventListener('click', function(e) {
        e.stopPropagation();

        const link = e.target.closest('.rv-notify-link');
        if (!link) return;

        const href = link.getAttribute('href') || '';
        if (href.charAt(0) === '#') {
            e.preventDefault();
            notificationPanel.classList.remove('show');

            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                target.classList.add('rv-focus-flash');
                setTimeout(() => target.classList.remove('rv-focus-flash'), 1400);
            }
        }
    });

    document.addEventListener('click', function() {
        notificationPanel.classList.remove('show');
    });
}

const globalSearch = document.getElementById('globalSearch');
const tableSearch = document.getElementById('tableSearch');
const statusFilter = document.getElementById('statusFilter');
const priorityFilter = document.getElementById('priorityFilter');
const resetFilter = document.getElementById('resetFilter');
const tableRows = Array.from(document.querySelectorAll('#reviewQueueList .review-row'));
const emptyState = document.getElementById('emptyState');
const toggleQueueBtn = document.getElementById('toggleQueueBtn');

let queueExpanded = false;
const defaultLimit = 5;

function getSearchValue() {
    const tableValue = tableSearch ? tableSearch.value.trim() : '';
    const globalValue = globalSearch ? globalSearch.value.trim() : '';
    return (tableValue || globalValue).toLowerCase();
}

function syncSearch(source) {
    if (source === 'global' && tableSearch && globalSearch) {
        tableSearch.value = globalSearch.value;
    }

    if (source === 'table' && tableSearch && globalSearch) {
        globalSearch.value = tableSearch.value;
    }
}

function filterTable() {
    const searchValue = getSearchValue();
    const statusValue = statusFilter ? statusFilter.value.toLowerCase() : '';
    const priorityValue = priorityFilter ? priorityFilter.value.toLowerCase() : '';
    const hasFilter = searchValue !== '' || statusValue !== '' || priorityValue !== '';

    let visibleCount = 0;

    tableRows.forEach((row, index) => {
        const rowSearch = row.dataset.search || '';
        const rowStatus = row.dataset.status || '';
        const rowPriority = row.dataset.priority || '';

        const matchesSearch = rowSearch.includes(searchValue);
        const matchesStatus = !statusValue || rowStatus === statusValue;
        const matchesPriority = !priorityValue || rowPriority === priorityValue;
        const allowedByLimit = queueExpanded || hasFilter || index < defaultLimit;

        const shouldShow = matchesSearch && matchesStatus && matchesPriority && allowedByLimit;

        row.style.display = shouldShow ? 'grid' : 'none';

        if (shouldShow) {
            visibleCount++;
        }
    });

    if (emptyState) {
        emptyState.classList.toggle('d-none', visibleCount > 0);
    }

    if (toggleQueueBtn) {
        const icon = queueExpanded ? 'bi-chevron-up' : 'bi-list-ul';
        const text = queueExpanded ? 'Hide list' : 'Show all students';
        toggleQueueBtn.innerHTML = `<i class="bi ${icon}"></i> ${text}`;
    }
}

if (globalSearch) {
    globalSearch.addEventListener('input', function() {
        syncSearch('global');
        filterTable();
    });
}

if (tableSearch) {
    tableSearch.addEventListener('input', function() {
        syncSearch('table');
        filterTable();
    });
}

if (statusFilter) {
    statusFilter.addEventListener('change', filterTable);
}

if (priorityFilter) {
    priorityFilter.addEventListener('change', filterTable);
}

if (resetFilter) {
    resetFilter.addEventListener('click', function() {
        if (globalSearch) globalSearch.value = '';
        if (tableSearch) tableSearch.value = '';
        if (statusFilter) statusFilter.value = '';
        if (priorityFilter) priorityFilter.value = '';

        queueExpanded = false;
        filterTable();
    });
}

if (toggleQueueBtn) {
    toggleQueueBtn.addEventListener('click', function() {
        queueExpanded = !queueExpanded;
        filterTable();

        if (queueExpanded) {
            const tableBox = document.querySelector('.rv-table-box');
            if (tableBox) {
                tableBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    });
}

filterTable();
</script>

<?php require_once '../includes/footer.php'; ?>