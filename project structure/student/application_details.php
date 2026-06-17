<?php
// ============================================================
// student/application_details.php
// View application progress, evaluation scores, and outcomes
// ============================================================

$pageTitle = 'Application Details';

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requireRole('student');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$pdo = getDB();
$studentId = currentUserId();
$appId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* ============================================================
   FETCH APPLICATION DETAILS
   ============================================================ */
$sql = "
    SELECT 
        a.id AS app_id,
        a.status AS app_status,
        a.eligible AS app_eligible,
        a.submitted_at,
        sp.id AS program_id,
        sp.name AS program_name,
        sp.budget AS program_budget,
        sp.slots AS program_slots,
        p.gpa AS student_gpa,
        p.faculty AS student_faculty,
        p.major AS student_major
    FROM applications a
    JOIN scholarship_programs sp ON a.program_id = sp.id
    LEFT JOIN student_profiles p ON a.student_id = p.student_id
    WHERE a.id = ? AND a.student_id = ?
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$appId, $studentId]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    echo '<div class="container py-5"><div class="alert alert-danger">Application not found or you do not have permission to view it.</div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

/* ============================================================
   FETCH EVALUATION SCORES
   ============================================================ */
$evalSql = "
    SELECT 
        es.score,
        es.note,
        es.scored_at,
        sc.criterion_name,
        sc.weight
    FROM evaluation_scores es
    JOIN scoring_criteria sc ON es.criteria_id = sc.id
    WHERE es.application_id = ?
    ORDER BY sc.id ASC
";
$evalStmt = $pdo->prepare($evalSql);
$evalStmt->execute([$appId]);
$scores = $evalStmt->fetchAll(PDO::FETCH_ASSOC);

/* ============================================================
   FETCH DISBURSEMENT & CERTIFICATE INFO
   ============================================================ */
$disbSql = "SELECT * FROM disbursements WHERE application_id = ? LIMIT 1";
$disbStmt = $pdo->prepare($disbSql);
$disbStmt->execute([$appId]);
$disbursement = $disbStmt->fetch(PDO::FETCH_ASSOC);

$certSql = "SELECT * FROM award_certificates WHERE application_id = ? LIMIT 1";
$certStmt = $pdo->prepare($certSql);
$certStmt->execute([$appId]);
$certificate = $certStmt->fetch(PDO::FETCH_ASSOC);

/* ============================================================
   FETCH EVIDENCE FILES
   ============================================================ */
$evidSql = "SELECT * FROM application_evidence WHERE application_id = ? ORDER BY uploaded_at ASC";
$evidStmt = $pdo->prepare($evidSql);
$evidStmt->execute([$appId]);
$evidenceFiles = $evidStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-4">
    <!-- PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">Application #<?= e($app['app_id']) ?></h1>
            <p class="page-subtitle"><?= e($app['program_name']) ?></p>
        </div>
        <a href="my_applications.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i> Back to List
        </a>
    </div>

    <div class="row g-4">
        <!-- LEFT COLUMN: STATUS & DETAILS -->
        <div class="col-lg-8">
            <!-- STATUS TIMELINE CARD -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4"><i class="bi bi-clock-history me-2 text-primary"></i> Application Status Timeline</h5>
                    <div class="position-relative py-3">
                        <?php
                        $statusList = ['submitted', 'reviewing', 'eligible', 'approved', 'disbursed'];
                        $currentStatusIndex = array_search($app['app_status'], $statusList);
                        if ($currentStatusIndex === false) {
                            if ($app['app_status'] === 'ineligible' || $app['app_status'] === 'rejected' || $app['app_status'] === 'failed') {
                                $currentStatusIndex = 2; // Treat as stopped at intermediate/outcome state
                            } else {
                                $currentStatusIndex = 0;
                            }
                        }
                        ?>
                        <div class="d-flex justify-content-between text-center">
                            <?php foreach ($statusList as $index => $step): ?>
                                <div class="flex-fill position-relative">
                                    <?php
                                    $stepColor = 'secondary';
                                    $iconClass = 'bi-circle';
                                    if ($index < $currentStatusIndex) {
                                        $stepColor = 'success';
                                        $iconClass = 'bi-check-circle-fill';
                                    } elseif ($index === $currentStatusIndex) {
                                        if ($app['app_status'] === 'ineligible' || $app['app_status'] === 'rejected') {
                                            $stepColor = 'danger';
                                            $iconClass = 'bi-x-circle-fill';
                                        } else {
                                            $stepColor = 'primary';
                                            $iconClass = 'bi-dot';
                                        }
                                    }
                                    ?>
                                    <div class="fs-4 text-<?= $stepColor ?>">
                                        <i class="bi <?= $iconClass ?>"></i>
                                    </div>
                                    <div class="small fw-semibold mt-2 text-capitalize">
                                        <?php
                                        if ($index === 2 && $app['app_status'] === 'ineligible') {
                                            echo 'Ineligible';
                                        } elseif ($index === 3 && $app['app_status'] === 'rejected') {
                                            echo 'Rejected';
                                        } else {
                                            echo $step;
                                        }
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PROGRAM & STUDENT DETAILS -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4"><i class="bi bi-info-circle me-2 text-primary"></i> Application Specifications</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small d-block">Submitted At</label>
                            <strong><?= e($app['submitted_at']) ?></strong>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small d-block">Eligibility Checks</label>
                            <?php if ($app['app_eligible'] === null): ?>
                                <span class="badge bg-warning">Pending Review</span>
                            <?php elseif ($app['app_eligible']): ?>
                                <span class="badge bg-success"><i class="bi bi-check2 me-1"></i> Qualified</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-x me-1"></i> Disqualified</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small d-block">Faculty & Major</label>
                            <strong><?= e($app['student_faculty']) ?> - <?= e($app['student_major']) ?></strong>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small d-block">GPA at Application</label>
                            <strong><?= e($app['student_gpa']) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EVALUATION BREAKDOWN -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4"><i class="bi bi-list-check me-2 text-primary"></i> Evaluation Scores</h5>
                    <?php if (empty($scores)): ?>
                        <div class="text-muted py-3">No scores have been assigned to this application yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Criterion</th>
                                        <th class="text-center">Weight</th>
                                        <th class="text-center">Score Granted</th>
                                        <th>Feedback / Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $totalScore = 0;
                                    $totalWeight = 0;
                                    foreach ($scores as $row): 
                                        $totalScore += $row['score'] * ($row['weight'] / 100);
                                        $totalWeight += $row['weight'];
                                    ?>
                                        <tr>
                                            <td><?= e($row['criterion_name']) ?></td>
                                            <td class="text-center text-muted"><?= e($row['weight']) ?>%</td>
                                            <td class="text-center"><span class="badge bg-primary fs-6"><?= e($row['score']) ?></span></td>
                                            <td><?= e($row['note'] ? $row['note'] : '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-light">
                                        <td><strong>Weighted Average Score</strong></td>
                                        <td class="text-center text-muted"><strong><?= $totalWeight ?>%</strong></td>
                                        <td class="text-center"><strong><span class="badge bg-success fs-5"><?= number_format($totalScore, 2) ?></span></strong></td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- EVIDENCE FILES CARD -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4"><i class="bi bi-paperclip me-2 text-primary"></i> Evidence Documents</h5>
                    <?php if (empty($evidenceFiles)): ?>
                        <div class="alert alert-secondary mb-0" style="font-size:13px;">
                            <i class="bi bi-info-circle me-2"></i>No evidence files were uploaded with this application.
                        </div>
                    <?php else: ?>
                        <div style="display:flex;flex-direction:column;gap:10px;">
                        <?php foreach ($evidenceFiles as $ev):
                            $isImage = strpos($ev['file_type'], 'image/') === 0;
                            $isPdf   = $ev['file_type'] === 'application/pdf';
                            $icon    = $isImage ? '🖼️' : ($isPdf ? '📄' : '📝');
                            $statusColors = ['pending'=>['#fef9c3','#854d0e','#ca8a04'], 'approved'=>['#f0fdf4','#14532d','#16a34a'], 'rejected'=>['#fef2f2','#7f1d1d','#dc2626']];
                            $sc = $statusColors[$ev['status']] ?? $statusColors['pending'];
                            // Encode each path segment to handle spaces (e.g. "project structure")
                            $rawPath = str_replace('\\', '/', $ev['file_path']);
                            $segments = explode('/', trim($rawPath, '/'));
                            $fileUrl = '/' . implode('/', array_map('rawurlencode', $segments));
                        ?>
                            <div style="border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;background:#fafafa;">
                                <div style="display:flex;align-items:flex-start;gap:12px;">
                                    <span style="font-size:28px;line-height:1;flex-shrink:0;"><?= $icon ?></span>
                                    <div style="flex:1;min-width:0;">
                                        <div style="font-size:13px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px;">
                                            <?= e($ev['original_name']) ?>
                                        </div>
                                        <div style="font-size:11.5px;color:#64748b;margin-bottom:8px;">
                                            <?= number_format($ev['file_size'] / 1024, 1) ?> KB &nbsp;&middot;&nbsp;
                                            <?= e($ev['file_type']) ?> &nbsp;&middot;&nbsp;
                                            Uploaded <?= date('M d, Y H:i', strtotime($ev['uploaded_at'])) ?>
                                        </div>
                                        <?php if ($ev['reviewer_comment']): ?>
                                        <div style="font-size:12px;color:#475569;background:#f1f5f9;border-left:3px solid #2563eb;padding:6px 10px;border-radius:4px;margin-bottom:8px;">
                                            <strong>Reviewer note:</strong> <?= e($ev['reviewer_comment']) ?>
                                        </div>
                                        <?php endif; ?>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <span style="font-size:11px;font-weight:700;background:<?= $sc[0] ?>;color:<?= $sc[1] ?>;border:1px solid <?= $sc[2] ?>;padding:2px 8px;border-radius:20px;text-transform:capitalize;">
                                                <?= e($ev['status']) ?>
                                            </span>
                                            <?php if ($isImage): ?>
                                                <a href="<?= e($fileUrl) ?>" target="_blank" class="btn btn-sm btn-outline-primary" style="font-size:11px;padding:2px 10px;">
                                                    <i class="bi bi-eye me-1"></i>View Image
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= e($fileUrl) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" style="font-size:11px;padding:2px 10px;">
                                                    <i class="bi bi-download me-1"></i>Download
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: FINANCIALS & CERTIFICATES -->

        <div class="col-lg-4">
            <!-- FINANCE DETAILS -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3"><i class="bi bi-cash-coin me-2 text-primary"></i> Payout Status</h5>
                    <?php if (!$disbursement): ?>
                        <div class="alert alert-secondary mb-0">No disbursements are scheduled yet.</div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label class="text-muted small d-block">Payout Amount</label>
                            <h3 class="fw-bold text-success mb-1"><?= number_format($disbursement['amount'], 0) ?> <span class="fs-5">VND</span></h3>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small d-block">Transaction Status</label>
                            <?php
                            $disbBadge = 'bg-secondary';
                            if ($disbursement['status'] === 'approved') $disbBadge = 'bg-info';
                            if ($disbursement['status'] === 'paid') $disbBadge = 'bg-success';
                            if ($disbursement['status'] === 'failed') $disbBadge = 'bg-danger';
                            ?>
                            <span class="badge <?= $disbBadge ?> text-capitalize"><?= e($disbursement['status']) ?></span>
                        </div>
                        <?php if ($disbursement['disbursed_at']): ?>
                            <div class="mb-3">
                                <label class="text-muted small d-block">Disbursed Date</label>
                                <strong><?= e($disbursement['disbursed_at']) ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if ($disbursement['note']): ?>
                            <div>
                                <label class="text-muted small d-block">Notes</label>
                                <span class="small text-muted"><?= e($disbursement['note']) ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- AWARD CERTIFICATE -->
            <div class="card">
                <div class="card-body text-center py-4">
                    <h5 class="card-title text-start mb-3"><i class="bi bi-patch-check me-2 text-primary"></i> Award Certificate</h5>
                    <?php if (!$certificate): ?>
                        <div class="text-muted">A certificate will be issued after the scholarship is successfully disbursed.</div>
                    <?php else: ?>
                        <div class="fs-1 text-warning mb-3">
                            <i class="bi bi-trophy"></i>
                        </div>
                        <h6 class="fw-bold mb-1">Scholarship Award Certificate</h6>
                        <p class="small text-muted mb-3">Certificate Code: <code class="text-dark"><?= e($certificate['certificate_code']) ?></code></p>
                        <div class="alert alert-success small mb-3">
                            <i class="bi bi-info-circle me-1"></i> Issued on <?= e($certificate['issued_at']) ?>
                        </div>
                        <button class="btn btn-primary w-100" onclick="alert('Downloading certificate: <?= e($certificate['certificate_code']) ?>.pdf')">
                            <i class="bi bi-download me-2"></i> Download PDF
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
