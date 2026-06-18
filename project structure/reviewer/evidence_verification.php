<?php
$pageTitle = 'Evidence Verification';

require_once '../config/db.php';
require_once '../includes/auth.php';

requireLogin();
requireRole('reviewer', 'admin');

$pdo = getDB();
$reviewerId = function_exists('currentUserId') ? currentUserId() : (int)($_SESSION['user_id'] ?? 0);


function evidenceFileUrl($path) {
    $path = trim((string)$path);

    if ($path === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    $path = str_replace('\\', '/', $path);
    $path = ltrim($path, '/');

    // Nếu DB lỡ lưu đường dẫn kiểu tuyệt đối hoặc có kèm tên project,
    // chỉ lấy phần từ uploads/evidence/... để reviewer mở đúng URL.
    $marker = 'uploads/evidence/';
    $pos = strpos($path, $marker);
    if ($pos !== false) {
        $path = substr($path, $pos);
    }

    $segments = array_filter(explode('/', $path), 'strlen');
    $encodedPath = implode('/', array_map('rawurlencode', $segments));

    if (defined('BASE_URL')) {
        return rtrim(BASE_URL, '/') . '/' . $encodedPath;
    }

    return '../' . $encodedPath;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $evidenceId = (int)($_POST['evidence_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $comment = trim($_POST['comment'] ?? '');

    if ($evidenceId > 0 && in_array($status, ['approved', 'rejected'], true)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE application_evidence
                SET status = ?, reviewer_comment = ?, reviewed_by = ?, reviewed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $comment, $reviewerId, $evidenceId]);

            $_SESSION['flash_success'] = 'Evidence has been reviewed successfully.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Save failed: ' . $e->getMessage();
        }

        header('Location: evidence_verification.php');
        exit;
    }

    $_SESSION['flash_error'] = 'Invalid evidence or status.';
    header('Location: evidence_verification.php');
    exit;
}

require_once '../includes/header.php';
require_once '../includes/navbar.php';
require_once '_reviewer_common.php';

reviewerCss();

$filter = $_GET['filter'] ?? 'all';
$where = "";
$params = [];

if (in_array($filter, ['pending', 'approved', 'rejected'], true)) {
    $where = "WHERE ev.status = ?";
    $params[] = $filter;
}

$stmt = $pdo->prepare("
    SELECT 
        ev.*,
        a.status AS application_status,
        u.full_name,
        u.student_code,
        sp.name AS program_name,
        profile.gpa,
        profile.activities_count,
        profile.failed_subjects,
        profile.has_language_cert,
        reviewer.full_name AS reviewer_name
    FROM application_evidence ev
    JOIN applications a ON a.id = ev.application_id
    JOIN users u ON u.id = a.student_id
    JOIN scholarship_programs sp ON sp.id = a.program_id
    LEFT JOIN student_profiles profile ON profile.student_id = u.id
    LEFT JOIN users reviewer ON reviewer.id = ev.reviewed_by
    $where
    ORDER BY 
        CASE 
            WHEN ev.status='pending' THEN 0
            WHEN ev.status='rejected' THEN 1
            ELSE 2
        END,
        ev.created_at DESC
");
$stmt->execute($params);
$evidences = $stmt->fetchAll();

$counts = $pdo->query("
    SELECT 
        COALESCE(SUM(status='pending'),0) AS pending_count,
        COALESCE(SUM(status='accepted'),0) AS accepted_count,
        COALESCE(SUM(status='rejected'),0) AS rejected_count
    FROM application_evidence
")->fetch();

$totalEvidence = (int)$counts['pending_count'] + (int)$counts['accepted_count'] + (int)$counts['rejected_count'];
$acceptedRate = $totalEvidence > 0 ? round(((int)$counts['accepted_count'] / $totalEvidence) * 100) : 0;
?>

<style>
:root {
    --rv-bg: #f4f7fb;
    --rv-card: #ffffff;
    --rv-text: #0f172a;
    --rv-muted: #64748b;
    --rv-line: #e2e8f0;
    --rv-primary: #2563eb;
    --rv-primary-dark: #1d4ed8;
    --rv-success: #16a34a;
    --rv-danger: #dc2626;
    --rv-warning: #d97706;
    --rv-shadow: 0 14px 35px rgba(15, 23, 42, .07);
}

html,
body {
    overflow-x: hidden;
    background: var(--rv-bg);
}

.evidence-page {
    min-height: 100vh;
    background: var(--rv-bg);
    color: var(--rv-text);
    padding: 24px 26px 90px;
}

.ev-inner {
    max-width: 100%;
    margin: 0 auto;
}

.ev-card {
    background: var(--rv-card);
    border: 1px solid var(--rv-line);
    border-radius: 22px;
    box-shadow: var(--rv-shadow);
}

.ev-header {
    padding: 22px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 18px;
}

.ev-title h2 {
    margin: 0;
    font-size: 26px;
    font-weight: 950;
    letter-spacing: -.4px;
}

.ev-title p {
    margin: 6px 0 0;
    color: var(--rv-muted);
    font-weight: 600;
    font-size: 14px;
}

.ev-header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

.ev-btn {
    min-height: 42px;
    border-radius: 14px;
    padding: 9px 14px;
    border: 1px solid var(--rv-line);
    background: #fff;
    color: var(--rv-text);
    text-decoration: none;
    font-weight: 850;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    white-space: nowrap;
    cursor: pointer;
}

.ev-btn:hover {
    background: #f8fafc;
    color: var(--rv-primary);
}

.ev-btn-primary {
    background: var(--rv-primary);
    border-color: var(--rv-primary);
    color: #fff;
}

.ev-btn-primary:hover {
    background: var(--rv-primary-dark);
    color: #fff;
}

.ev-btn-success {
    background: #eefcf3;
    color: #15803d;
    border-color: #bbf7d0;
}

.ev-btn-danger {
    background: #fff1f2;
    color: #be123c;
    border-color: #fecdd3;
}

.ev-metrics {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 18px;
}

.ev-metric {
    padding: 18px;
}

.ev-metric-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.ev-label {
    color: var(--rv-muted);
    font-size: 12px;
    font-weight: 950;
    text-transform: uppercase;
    letter-spacing: .9px;
}

.ev-value {
    font-size: 32px;
    font-weight: 950;
    margin-top: 5px;
}

.ev-icon {
    width: 46px;
    height: 46px;
    border-radius: 15px;
    background: #eef4ff;
    color: var(--rv-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 21px;
    flex-shrink: 0;
}

.ev-note,
.ev-muted {
    color: var(--rv-muted);
    font-size: 13px;
}

.ev-workspace {
    padding: 22px;
}

.ev-workspace-head {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    align-items: flex-start;
    margin-bottom: 16px;
}

.ev-workspace h4 {
    font-size: 20px;
    font-weight: 950;
    margin: 0;
}

.ev-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.ev-tab {
    min-height: 38px;
    padding: 8px 12px;
    border-radius: 13px;
    border: 1px solid var(--rv-line);
    color: var(--rv-muted);
    background: #fff;
    font-weight: 850;
    text-decoration: none;
}

.ev-tab.active,
.ev-tab:hover {
    background: var(--rv-primary);
    border-color: var(--rv-primary);
    color: #fff;
}

.ev-toolbar {
    display: grid;
    grid-template-columns: minmax(260px, 1fr) 170px 210px auto;
    gap: 10px;
    margin-bottom: 16px;
}

.ev-control {
    min-height: 44px;
    width: 100%;
    border-radius: 14px;
    border: 1px solid var(--rv-line);
    padding: 9px 13px;
    outline: none;
    background: #fff;
}

.ev-control:focus {
    border-color: var(--rv-primary);
    box-shadow: 0 0 0 4px rgba(37, 99, 235, .1);
}

.ev-list {
    display: grid;
    gap: 12px;
}

.ev-item {
    border: 1px solid var(--rv-line);
    border-radius: 20px;
    background: #fff;
    overflow: hidden;
}

.ev-item-main {
    display: grid;
    grid-template-columns: 260px minmax(240px, 1fr) 280px;
    gap: 18px;
    padding: 18px;
    align-items: start;
}

.ev-student {
    display: flex;
    gap: 12px;
    min-width: 0;
}

.ev-avatar {
    width: 46px;
    height: 46px;
    border-radius: 15px;
    background: #e0ecff;
    color: var(--rv-primary);
    font-weight: 950;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.ev-name {
    font-weight: 900;
    line-height: 1.25;
}

.ev-code {
    color: var(--rv-muted);
    font-size: 13px;
    margin-top: 3px;
}

.ev-chip-row {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.ev-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 9px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #475569;
    font-size: 12px;
    font-weight: 850;
}

.ev-section-label {
    color: var(--rv-muted);
    font-size: 11px;
    font-weight: 950;
    letter-spacing: .75px;
    text-transform: uppercase;
    margin-bottom: 6px;
}

.ev-program,
.ev-evidence-title {
    font-weight: 900;
    line-height: 1.35;
}

.ev-evidence-grid {
    display: grid;
    grid-template-columns: minmax(170px, 1fr) minmax(170px, 1fr);
    gap: 14px;
}

.ev-file-box {
    margin-top: 10px;
    padding: 10px;
    border-radius: 14px;
    border: 1px dashed #cbd5e1;
    background: #f8fafc;
}

.ev-file-box.has-file {
    border-style: solid;
    background: #f8fafc;
}

.ev-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 900;
    text-transform: capitalize;
}

.ev-status.pending {
    color: #92400e;
    background: #fffbeb;
}

.ev-status.accepted {
    color: #166534;
    background: #f0fdf4;
}

.ev-status.rejected {
    color: #991b1b;
    background: #fef2f2;
}

.ev-action-box {
    border-left: 1px solid #edf2f7;
    padding-left: 18px;
}

.ev-comment {
    min-height: 88px;
    border-radius: 14px;
    resize: vertical;
}

.ev-quick-comments {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin: 8px 0;
}

.ev-mini-btn {
    border: 1px solid var(--rv-line);
    background: #fff;
    border-radius: 999px;
    padding: 5px 9px;
    font-size: 12px;
    color: var(--rv-muted);
    font-weight: 800;
}

.ev-mini-btn:hover {
    color: var(--rv-primary);
    background: #f8fafc;
}

.ev-actions-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.ev-empty {
    border: 1px dashed #cbd5e1;
    border-radius: 18px;
    padding: 38px;
    text-align: center;
    color: var(--rv-muted);
    background: #f8fafc;
}

.ev-footer-tools {
    display: flex;
    justify-content: center;
    margin-top: 16px;
}

@media (max-width: 1200px) {
    .ev-item-main {
        grid-template-columns: 1fr;
    }

    .ev-action-box {
        border-left: 0;
        border-top: 1px solid #edf2f7;
        padding-left: 0;
        padding-top: 16px;
    }
}

@media (max-width: 992px) {
    .evidence-page {
        padding: 18px 14px 80px;
    }

    .ev-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .ev-header-actions,
    .ev-btn {
        width: 100%;
    }

    .ev-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .ev-toolbar {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .ev-metrics,
    .ev-evidence-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container-fluid evidence-page">
    <div class="ev-inner">

        <div class="ev-card ev-header">
            <div class="ev-title">
                <h2>Evidence Verification</h2>
                <p>Validate uploaded proof files, leave comments, and keep evidence decisions easy to audit.</p>
            </div>

            <div class="ev-header-actions">
                <a href="dashboard.php" class="ev-btn">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
                <a href="evaluation_scores.php" class="ev-btn">
                    <i class="bi bi-star"></i>
                    Scores
                </a>
                <button type="button" class="ev-btn ev-btn-primary" id="focusPendingBtn">
                    <i class="bi bi-hourglass-split"></i>
                    Focus Pending
                </button>
            </div>
        </div>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success rounded-4 border-0 shadow-sm">
                <i class="bi bi-check2-circle me-2"></i>
                <?= e($_SESSION['flash_success']) ?>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <div class="ev-metrics">
            <div class="ev-card ev-metric">
                <div class="ev-metric-top">
                    <div>
                        <div class="ev-label">Pending</div>
                        <div class="ev-value"><?= (int)$counts['pending_count'] ?></div>
                    </div>
                    <div class="ev-icon"><i class="bi bi-hourglass-split"></i></div>
                </div>
                <div class="ev-note mt-2">Waiting for decision</div>
            </div>

            <div class="ev-card ev-metric">
                <div class="ev-metric-top">
                    <div>
                        <div class="ev-label">Accepted</div>
                        <div class="ev-value"><?= (int)$counts['accepted_count'] ?></div>
                    </div>
                    <div class="ev-icon"><i class="bi bi-check2-circle"></i></div>
                </div>
                <div class="ev-note mt-2">Validated files</div>
            </div>

            <div class="ev-card ev-metric">
                <div class="ev-metric-top">
                    <div>
                        <div class="ev-label">Rejected</div>
                        <div class="ev-value"><?= (int)$counts['rejected_count'] ?></div>
                    </div>
                    <div class="ev-icon"><i class="bi bi-x-circle"></i></div>
                </div>
                <div class="ev-note mt-2">Invalid submissions</div>
            </div>

            <div class="ev-card ev-metric">
                <div class="ev-metric-top">
                    <div>
                        <div class="ev-label">Acceptance Rate</div>
                        <div class="ev-value"><?= $acceptedRate ?>%</div>
                    </div>
                    <div class="ev-icon"><i class="bi bi-graph-up-arrow"></i></div>
                </div>
                <div class="ev-note mt-2">Accepted / total</div>
            </div>
        </div>

        <div class="ev-card ev-workspace">
            <div class="ev-workspace-head">
                <div>
                    <h4>Evidence Review Queue</h4>
                    <div class="ev-muted mt-1">Cleaner reviewer workflow with search, quick comments, file opening, accept and reject actions.</div>
                </div>

                <div class="ev-tabs">
                    <a class="ev-tab <?= $filter==='all'?'active':'' ?>" href="?filter=all">All</a>
                    <a class="ev-tab <?= $filter==='pending'?'active':'' ?>" href="?filter=pending">Pending</a>
                    <a class="ev-tab <?= $filter==='accepted'?'active':'' ?>" href="?filter=accepted">Accepted</a>
                    <a class="ev-tab <?= $filter==='rejected'?'active':'' ?>" href="?filter=rejected">Rejected</a>
                </div>
            </div>

            <div class="ev-toolbar">
                <input 
                    type="text" 
                    id="searchInput" 
                    class="ev-control" 
                    placeholder="Search student, code, program, evidence..."
                >

                <select id="statusFilter" class="ev-control">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Accepted</option>
                    <option value="rejected">Rejected</option>
                </select>

                <select id="typeFilter" class="ev-control">
                    <option value="">All Evidence Types</option>
                    <option value="language_certificate">Language Certificate</option>
                    <option value="activities">Activities Proof</option>
                    <option value="failed_subjects">Failed Subjects</option>
                </select>

                <button type="button" class="ev-btn" id="resetFilters">
                    <i class="bi bi-arrow-clockwise"></i>
                    Reset
                </button>
            </div>

            <div class="ev-list" id="evidenceList">
                <?php foreach ($evidences as $ev): ?>
                    <?php
                        $status = strtolower($ev['status']);
                        $searchText = strtolower(
                            ($ev['full_name'] ?? '') . ' ' .
                            ($ev['student_code'] ?? '') . ' ' .
                            ($ev['program_name'] ?? '') . ' ' .
                            ($ev['title'] ?? '') . ' ' .
                            ($ev['evidence_type'] ?? '')
                        );
                    ?>

                    <div 
                        class="ev-item"
                        data-search="<?= e($searchText) ?>"
                        data-status="<?= e($status) ?>"
                        data-type="<?= e(strtolower($ev['evidence_type'])) ?>"
                    >
                        <div class="ev-item-main">
                            <div class="ev-student">
                                <div class="ev-avatar">
                                    <?= e(strtoupper(substr($ev['full_name'] ?? 'S', 0, 1))) ?>
                                </div>

                                <div>
                                    <div class="ev-name"><?= e($ev['full_name']) ?></div>
                                    <div class="ev-code"><?= e($ev['student_code']) ?></div>

                                    <div class="ev-chip-row">
                                        <span class="ev-chip">
                                            <i class="bi bi-mortarboard"></i>
                                            GPA <?= e($ev['gpa'] ?? 'N/A') ?>
                                        </span>
                                        <span class="ev-chip">
                                            <i class="bi bi-activity"></i>
                                            Activities <?= e($ev['activities_count'] ?? 0) ?>
                                        </span>
                                        <span class="ev-chip">
                                            <i class="bi bi-exclamation-circle"></i>
                                            Failed <?= e($ev['failed_subjects'] ?? 0) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="ev-evidence-grid">
                                <div>
                                    <div class="ev-section-label">Application</div>
                                    <div class="ev-program"><?= e($ev['program_name']) ?></div>
                                    <div class="ev-muted mt-1">Application status: <?= e(ucfirst($ev['application_status'])) ?></div>
                                </div>

                                <div>
                                    <div class="ev-section-label">Evidence</div>
                                    <div class="ev-evidence-title"><?= e($ev['title']) ?></div>
                                    <div class="ev-muted mt-1"><?= e($ev['evidence_type']) ?></div>

                                    <?php
                                        $fileUrl = evidenceFileUrl($ev['file_path'] ?? '');
                                    ?>

                                    <?php if ($fileUrl !== ''): ?>
                                        <div class="ev-file-box has-file">
                                            <a
                                                class="ev-btn ev-btn-primary w-100"
                                                target="_blank"
                                                href="<?= e($fileUrl) ?>"
                                            >
                                                <i class="bi bi-box-arrow-up-right"></i>
                                                Open uploaded file
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="ev-file-box">
                                            <button type="button" class="ev-btn w-100" disabled>
                                                <i class="bi bi-file-earmark-x"></i>
                                                No file uploaded yet
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="ev-action-box">
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                    <span class="ev-status <?= e($status) ?>">
                                        <?php if ($status === 'pending'): ?>
                                            <i class="bi bi-hourglass-split"></i>
                                        <?php elseif ($status === 'accepted'): ?>
                                            <i class="bi bi-check2-circle"></i>
                                        <?php else: ?>
                                            <i class="bi bi-x-circle"></i>
                                        <?php endif; ?>
                                        <?= e($ev['status']) ?>
                                    </span>

                                    <?php if (!empty($ev['reviewer_name'])): ?>
                                        <small class="ev-muted">by <?= e($ev['reviewer_name']) ?></small>
                                    <?php endif; ?>
                                </div>

                                <form method="post" class="review-form">
                                    <input type="hidden" name="evidence_id" value="<?= (int)$ev['id'] ?>">

                                    <textarea 
                                        name="comment" 
                                        class="form-control ev-comment" 
                                        rows="3" 
                                        placeholder="Reviewer comment..."
                                    ><?= e($ev['reviewer_comment'] ?? '') ?></textarea>

                                    <div class="ev-quick-comments">
                                        <button type="button" class="ev-mini-btn quick-comment" data-comment="Evidence is clear and matches the application information.">Clear evidence</button>
                                        <button type="button" class="ev-mini-btn quick-comment" data-comment="File is missing or cannot be verified. Please upload a valid document.">Missing/invalid</button>
                                        <button type="button" class="ev-mini-btn quick-comment" data-comment="Information is unclear. Additional proof is required.">Need more proof</button>
                                    </div>

                                    <div class="ev-actions-row">
                                        <button 
                                            name="status" 
                                            value="approved" 
                                            class="ev-btn ev-btn-success"
                                        >
                                            <i class="bi bi-check2-circle"></i>
                                            Accept
                                        </button>

                                        <button 
                                            name="status" 
                                            value="rejected" 
                                            class="ev-btn ev-btn-danger"
                                        >
                                            <i class="bi bi-x-circle"></i>
                                            Reject
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (!$evidences): ?>
                    <div class="ev-empty">
                        <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                        No evidence found.
                    </div>
                <?php endif; ?>
            </div>

            <div id="emptySearchState" class="ev-empty d-none">
                <i class="bi bi-search fs-1 d-block mb-3"></i>
                No matching evidence found.
            </div>

            <?php if (count($evidences) > 6): ?>
                <div class="ev-footer-tools">
                    <button type="button" class="ev-btn" id="toggleListBtn">
                        <i class="bi bi-list-ul"></i>
                        Show more evidence
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const typeFilter = document.getElementById('typeFilter');
const evidenceCards = Array.from(document.querySelectorAll('.ev-item'));
const emptySearchState = document.getElementById('emptySearchState');
const resetFilters = document.getElementById('resetFilters');
const focusPendingBtn = document.getElementById('focusPendingBtn');
const toggleListBtn = document.getElementById('toggleListBtn');

let expanded = false;
const defaultLimit = 6;

function filterEvidence() {
    const searchValue = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const statusValue = statusFilter ? statusFilter.value.toLowerCase() : '';
    const typeValue = typeFilter ? typeFilter.value.toLowerCase() : '';
    const hasFilter = searchValue !== '' || statusValue !== '' || typeValue !== '';

    let visibleCount = 0;

    evidenceCards.forEach((card, index) => {
        const cardSearch = card.dataset.search || '';
        const cardStatus = card.dataset.status || '';
        const cardType = card.dataset.type || '';

        const matchesSearch = cardSearch.includes(searchValue);
        const matchesStatus = !statusValue || cardStatus === statusValue;
        const matchesType = !typeValue || cardType === typeValue;
        const allowedByLimit = expanded || hasFilter || index < defaultLimit;

        const visible = matchesSearch && matchesStatus && matchesType && allowedByLimit;
        card.style.display = visible ? '' : 'none';

        if (visible) visibleCount++;
    });

    if (emptySearchState) {
        emptySearchState.classList.toggle('d-none', visibleCount > 0);
    }

    if (toggleListBtn) {
        toggleListBtn.innerHTML = expanded
            ? '<i class="bi bi-chevron-up"></i> Show less'
            : '<i class="bi bi-list-ul"></i> Show more evidence';
    }
}

if (searchInput) searchInput.addEventListener('input', filterEvidence);
if (statusFilter) statusFilter.addEventListener('change', filterEvidence);
if (typeFilter) typeFilter.addEventListener('change', filterEvidence);

if (resetFilters) {
    resetFilters.addEventListener('click', function() {
        if (searchInput) searchInput.value = '';
        if (statusFilter) statusFilter.value = '';
        if (typeFilter) typeFilter.value = '';
        expanded = false;
        filterEvidence();
    });
}

if (focusPendingBtn && statusFilter) {
    focusPendingBtn.addEventListener('click', function() {
        statusFilter.value = 'pending';
        expanded = true;
        filterEvidence();
        document.querySelector('.ev-workspace')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}

if (toggleListBtn) {
    toggleListBtn.addEventListener('click', function() {
        expanded = !expanded;
        filterEvidence();
    });
}

document.querySelectorAll('.quick-comment').forEach(button => {
    button.addEventListener('click', function() {
        const form = button.closest('form');
        const textarea = form ? form.querySelector('textarea[name="comment"]') : null;
        if (textarea) {
            textarea.value = button.dataset.comment || '';
            textarea.focus();
        }
    });
});

document.querySelectorAll('.review-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const clickedButton = document.activeElement;
        const comment = form.querySelector('textarea[name="comment"]').value.trim();

        if (clickedButton && clickedButton.value === 'rejected' && comment.length < 5) {
            e.preventDefault();
            alert('Please write a clear comment before rejecting evidence.');
        }
    });
});

filterEvidence();
</script>

<?php require_once '../includes/footer.php'; ?>
