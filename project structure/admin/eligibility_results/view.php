<?php
// ============================================================
// admin/eligibility_results/view.php
// ============================================================

$pageTitle = 'View Eligibility Result';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

$pdo = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$sql = "
    SELECT er.*, a.id AS application_number, u.full_name AS student_name, sp.name AS program_name
    FROM eligibility_results er
    INNER JOIN applications a ON er.application_id = a.id
    INNER JOIN users u ON a.student_id = u.id
    INNER JOIN scholarship_programs sp ON a.program_id = sp.id
    WHERE er.id = :id
    LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$result = $stmt->fetch();

if (!$result) {
    echo '<div class="container py-5"><div class="alert alert-danger">Eligibility result not found.</div></div>';
    require_once '../../includes/footer.php';
    exit;
}
?>

<div class="container py-4">
    <!-- PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">Eligibility Result Detail</h1>
            <p class="page-subtitle">Detailed check outcome for Application #<?= e($result['application_number']) ?></p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i> Back to List
        </a>
    </div>

    <!-- DETAIL CARD -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4"><i class="bi bi-info-circle me-2 text-primary"></i> Verification Details</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small d-block">Result ID</label>
                            <strong>#<?= e($result['id']) ?></strong>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small d-block">Application ID</label>
                            <strong>#<?= e($result['application_number']) ?></strong>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small d-block">Student Name</label>
                            <strong><?= e($result['student_name']) ?></strong>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small d-block">Scholarship Program</label>
                            <strong><?= e($result['program_name']) ?></strong>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small d-block">Status</label>
                            <?php if ($result['is_passed'] == 1): ?>
                                <span class="badge bg-success"><i class="bi bi-check2 me-1"></i> PASS</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-x me-1"></i> FAIL</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small d-block">Checked At</label>
                            <strong><?= e($result['checked_at']) ?></strong>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="text-muted small d-block">Reason / Explanation</label>
                            <p class="mb-0 bg-light p-3 rounded text-dark border-start border-4 border-info">
                                <?= e($result['reason'] ? $result['reason'] : 'No comments provided. Automatically passed eligibility checks.') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>