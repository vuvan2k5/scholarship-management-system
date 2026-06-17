<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pageTitle = 'Recommendations';

require_once '../config/db.php';
require_once '../includes/auth.php';

requireLogin();
requireRole('reviewer');

$pdo = getDB();
$reviewerId = $_SESSION['user_id'] ?? 0;

function safeText($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function priorityBadge($priority) {
    switch ($priority) {
        case 'high':
            return 'danger';
        case 'medium':
            return 'warning';
        case 'low':
            return 'secondary';
        default:
            return 'secondary';
    }
}

function recommendationStatusBadge($status) {
    switch ($status) {
        case 'reviewed':
            return 'success';
        case 'rejected':
            return 'danger';
        case 'in_review':
            return 'info';
        case 'sent':
            return 'primary';
        default:
            return 'secondary';
    }
}

function categoryLabel($category) {
    switch ($category) {
        case 'new_scholarship':
            return 'New Scholarship';
        case 'criteria_update':
            return 'Criteria Update';
        case 'policy_question':
            return 'Policy Question';
        case 'system_improvement':
            return 'System Improvement';
        default:
            return 'Other';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? 'other';
    $priority = $_POST['priority'] ?? 'medium';
    $message = trim($_POST['message'] ?? '');

    if ($title !== '' && $message !== '') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO reviewer_recommendations
                (reviewer_id, title, category, message, priority, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'sent', NOW())
            ");
            $stmt->execute([$reviewerId, $title, $category, $message, $priority]);

            $_SESSION['flash_success'] = 'Recommendation has been sent to admin successfully.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Send failed: ' . $e->getMessage();
        }

        header('Location: recommendations.php');
        exit;
    } else {
        $_SESSION['flash_error'] = 'Please enter both title and message.';
    }
}

require_once '../includes/header.php';
require_once '../includes/navbar.php';
require_once '_reviewer_common.php';

reviewerCss();

$stmt = $pdo->prepare("
    SELECT rr.*, u.full_name AS reviewer_name
    FROM reviewer_recommendations rr
    LEFT JOIN users u ON u.id = rr.reviewer_id
    WHERE rr.reviewer_id = ?
    ORDER BY rr.created_at DESC
");
$stmt->execute([$reviewerId]);
$recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalSent = count($recommendations);
$highPriority = count(array_filter($recommendations, function($r) { return $r['priority'] === 'high'; }));
$reviewed = count(array_filter($recommendations, function($r) { return $r['status'] === 'reviewed'; }));
$pending = count(array_filter($recommendations, function($r) { return $r['status'] === 'sent' || $r['status'] === 'in_review'; }));
?>

<style>
.recommend-page {
    background: #f4f7fb;
    min-height: 100vh;
}

.recommend-hero {
    position: relative;
    overflow: hidden;
    border-radius: 28px;
    padding: 34px;
    background: linear-gradient(135deg, #0f172a, #1d4ed8);
    color: #fff;
    box-shadow: 0 22px 50px rgba(15, 23, 42, .18);
}

.recommend-hero::after {
    content: "";
    position: absolute;
    width: 260px;
    height: 260px;
    right: -60px;
    top: -70px;
    border-radius: 50%;
    background: rgba(255,255,255,.15);
}

.recommend-hero h1 {
    font-size: 42px;
    letter-spacing: -.8px;
}

.metric-card {
    background: #fff;
    border-radius: 22px;
    padding: 24px;
    border: 1px solid #e5eaf1;
    box-shadow: 0 18px 40px rgba(15,23,42,.08);
    transition: .25s;
}

.metric-card:hover {
    transform: translateY(-4px);
}

.metric-icon {
    width: 46px;
    height: 46px;
    border-radius: 16px;
    display: grid;
    place-items: center;
    background: #eef4ff;
    color: #2563eb;
    font-size: 22px;
}

.mini-label {
    font-size: 12px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 1.4px;
    font-weight: 800;
}

.main-card {
    background: #fff;
    border-radius: 26px;
    border: 1px solid #e5eaf1;
    box-shadow: 0 18px 45px rgba(15,23,42,.08);
}

.form-control,
.form-select {
    min-height: 48px;
    border-radius: 14px;
    border: 1px solid #dbe3ef;
}

.form-control:focus,
.form-select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 .18rem rgba(37,99,235,.12);
}

textarea.form-control {
    min-height: 145px;
}

.action-btn {
    height: 50px;
    border-radius: 15px;
    font-weight: 800;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    border: none;
}

.template-card {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 18px;
    padding: 18px;
    height: 100%;
    cursor: pointer;
    transition: .22s;
}

.template-card:hover {
    border-color: #2563eb;
    background: #eef4ff;
    transform: translateY(-3px);
}

.history-item {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 20px;
    padding: 18px;
    transition: .2s;
}

.history-item:hover {
    background: #fff;
    box-shadow: 0 14px 28px rgba(15,23,42,.08);
}

.badge-soft {
    border-radius: 999px;
    padding: 7px 11px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .4px;
}

.toolbar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.toolbar input,
.toolbar select {
    max-width: 230px;
}

.empty-state {
    border: 1px dashed #cbd5e1;
    border-radius: 20px;
    padding: 42px;
    background: #f8fafc;
}

.quick-note {
    background: #ecfdf5;
    border: 1px solid #bbf7d0;
    color: #166534;
    border-radius: 18px;
    padding: 16px;
}

.admin-reply {
    background: #eff6ff;
    border-left: 4px solid #2563eb;
    border-radius: 14px;
    padding: 12px 14px;
    margin-top: 14px;
}
</style>

<div class="container-fluid py-4 recommend-page">

    <div class="recommend-hero mb-4">
        <div class="position-relative">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <h1 class="fw-bold mb-2">Recommendations</h1>
                    <p class="text-white-50 mb-0">
                        Send scholarship ideas, policy questions, system feedback, and improvement requests directly to admin.
                    </p>
                </div>
                <span class="badge bg-light text-primary rounded-pill px-3 py-2">
                    <i class="bi bi-send-check"></i> Reviewer to Admin
                </span>
            </div>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success rounded-4 border-0 shadow-sm">
            <i class="bi bi-check-circle me-1"></i>
            <?= safeText($_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger rounded-4 border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <?= safeText($_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="mini-label">Total Sent</div>
                        <h2 class="fw-bold mb-0"><?= $totalSent ?></h2>
                    </div>
                    <div class="metric-icon"><i class="bi bi-send"></i></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="mini-label">High Priority</div>
                        <h2 class="fw-bold text-danger mb-0"><?= $highPriority ?></h2>
                    </div>
                    <div class="metric-icon"><i class="bi bi-lightning-charge"></i></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="mini-label">Pending</div>
                        <h2 class="fw-bold text-primary mb-0"><?= $pending ?></h2>
                    </div>
                    <div class="metric-icon"><i class="bi bi-hourglass-split"></i></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="mini-label">Reviewed</div>
                        <h2 class="fw-bold text-success mb-0"><?= $reviewed ?></h2>
                    </div>
                    <div class="metric-icon"><i class="bi bi-check2-circle"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <div class="col-xl-5">
            <div class="main-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="fw-bold mb-1">Create Recommendation</h4>
                        <div class="text-muted small">Write a clear proposal for admin review.</div>
                    </div>
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">
                        New
                    </span>
                </div>

                <form method="post" id="recommendForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title</label>
                        <input type="text" name="title" id="title" class="form-control" required
                               placeholder="Example: Add interview round for final applicants">
                    </div>

                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-semibold">Category</label>
                            <select name="category" id="category" class="form-select">
                                <option value="new_scholarship">New Scholarship Suggestion</option>
                                <option value="criteria_update">Criteria Update</option>
                                <option value="policy_question">Policy Question</option>
                                <option value="system_improvement">System Improvement</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Priority</label>
                            <select name="priority" id="priority" class="form-select">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold">Message</label>
                        <textarea name="message" id="message" class="form-control" required
                                  placeholder="Explain the issue, why it matters, and what admin should consider..."></textarea>
                    </div>

                    <div class="quick-note mb-3 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Tip: A good recommendation should include the problem, reason, and expected improvement.
                    </div>

                    <button class="btn btn-primary action-btn w-100">
                        <i class="bi bi-send"></i> Send to Admin
                    </button>
                </form>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="main-card p-4 mb-4">
                <h4 class="fw-bold mb-3">Quick Templates</h4>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="template-card"
                             data-title="Create scholarship for students with strong extracurricular contribution"
                             data-category="new_scholarship"
                             data-priority="high"
                             data-message="Should we create a scholarship for students with strong extracurricular contribution? This can encourage students to join social activities, competitions, volunteer programs, and leadership projects. Admin may consider adding a separate scholarship type for this group.">
                            <strong>New Scholarship</strong>
                            <p class="text-muted small mb-0 mt-1">Suggest a new scholarship type for active students.</p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="template-card"
                             data-title="Update language certificate scoring criteria"
                             data-category="criteria_update"
                             data-priority="medium"
                             data-message="Should language certificates have a separate weight in the scoring criteria? This may help evaluate applicants more fairly, especially for international or academic scholarships.">
                            <strong>Criteria Update</strong>
                            <p class="text-muted small mb-0 mt-1">Ask admin to improve scoring rules.</p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="template-card"
                             data-title="Require evidence proof for all submitted activities"
                             data-category="policy_question"
                             data-priority="medium"
                             data-message="Should applicants be required to upload evidence for all activities? This can reduce false information and make the review process more transparent.">
                            <strong>Evidence Policy</strong>
                            <p class="text-muted small mb-0 mt-1">Improve document verification logic.</p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="template-card"
                             data-title="Improve reviewer dashboard notifications"
                             data-category="system_improvement"
                             data-priority="high"
                             data-message="The reviewer dashboard should show clearer notifications when admin responds, assigns new applications, or updates criteria. This will help reviewers react faster and avoid missing important tasks.">
                            <strong>System Improvement</strong>
                            <p class="text-muted small mb-0 mt-1">Suggest better notification features.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="main-card p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                    <div>
                        <h4 class="fw-bold mb-1">My Recommendation History</h4>
                        <div class="text-muted small">Track what you sent and whether admin reviewed it.</div>
                    </div>

                    <div class="toolbar">
                        <input type="text" id="searchBox" class="form-control" placeholder="Search history...">
                        <select id="statusFilter" class="form-select">
                            <option value="all">All Status</option>
                            <option value="sent">Sent</option>
                            <option value="in_review">In Review</option>
                            <option value="reviewed">Reviewed</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>

                <?php if ($recommendations): ?>
                    <div id="historyList">
                        <?php foreach ($recommendations as $r): ?>
                            <div class="history-item mb-3 recommendation-item"
                                 data-status="<?= safeText($r['status']) ?>"
                                 data-search="<?= safeText(strtolower($r['title'] . ' ' . $r['category'] . ' ' . $r['message'])) ?>">

                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <h6 class="fw-bold mb-1"><?= safeText($r['title']) ?></h6>
                                        <div class="small text-muted">
                                            <i class="bi bi-folder2-open"></i>
                                            <?= safeText(categoryLabel($r['category'])) ?>
                                            ·
                                            <i class="bi bi-calendar3"></i>
                                            <?= safeText($r['created_at']) ?>
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <span class="badge text-bg-<?= priorityBadge($r['priority']) ?> badge-soft">
                                            <?= safeText($r['priority']) ?>
                                        </span>
                                        <span class="badge text-bg-<?= recommendationStatusBadge($r['status']) ?> badge-soft">
                                            <?= safeText($r['status']) ?>
                                        </span>
                                    </div>
                                </div>

                                <p class="mt-3 mb-0"><?= nl2br(safeText($r['message'])) ?></p>

                                <?php if (!empty($r['admin_comment'])): ?>
                                    <div class="admin-reply">
                                        <strong><i class="bi bi-reply-fill"></i> Admin Reply:</strong>
                                        <div class="small mt-1"><?= nl2br(safeText($r['admin_comment'])) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="noResult" class="empty-state text-center text-muted d-none">
                        <i class="bi bi-search fs-2 d-block mb-2"></i>
                        No matching recommendations found.
                    </div>
                <?php else: ?>
                    <div class="empty-state text-center text-muted">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                        No recommendations sent yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
document.querySelectorAll('.template-card').forEach(card => {
    card.addEventListener('click', function () {
        document.getElementById('title').value = this.dataset.title;
        document.getElementById('category').value = this.dataset.category;
        document.getElementById('priority').value = this.dataset.priority;
        document.getElementById('message').value = this.dataset.message;

        document.getElementById('recommendForm').scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    });
});

const searchBox = document.getElementById('searchBox');
const statusFilter = document.getElementById('statusFilter');

function filterHistory() {
    const keyword = searchBox ? searchBox.value.toLowerCase().trim() : '';
    const status = statusFilter ? statusFilter.value : 'all';
    const items = document.querySelectorAll('.recommendation-item');
    let visible = 0;

    items.forEach(item => {
        const matchText = item.dataset.search.includes(keyword);
        const matchStatus = status === 'all' || item.dataset.status === status;

        if (matchText && matchStatus) {
            item.classList.remove('d-none');
            visible++;
        } else {
            item.classList.add('d-none');
        }
    });

    const noResult = document.getElementById('noResult');
    if (noResult) {
        noResult.classList.toggle('d-none', visible !== 0);
    }
}

if (searchBox) searchBox.addEventListener('input', filterHistory);
if (statusFilter) statusFilter.addEventListener('change', filterHistory);
</script>

<?php require_once '../includes/footer.php'; ?>