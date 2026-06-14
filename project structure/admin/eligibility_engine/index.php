<?php
// ============================================================
// admin/eligibility_engine/index.php
// ============================================================

$pageTitle = 'Eligibility Engine';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// Handle Run All
if (isset($_GET['run_all'])) {
    require_once '../../includes/eligibility.php';
    $stmtPending = $pdo->query("SELECT id FROM applications WHERE eligible IS NULL");
    $count = 0;
    while ($row = $stmtPending->fetch()) {
        checkEligibility($pdo, (int)$row['id']);
        $count++;
    }
    setFlash('success', "Engine processed $count pending application(s).");
    header("Location: index.php");
    exit;
}

// Handle Check Single
if (isset($_GET['check_id'])) {
    require_once '../../includes/eligibility.php';
    $checkId = (int)$_GET['check_id'];
    checkEligibility($pdo, $checkId);
    setFlash('success', "Eligibility check completed for Application #$checkId.");
    header("Location: index.php");
    exit;
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

// Fetch applications that need checking or all applications
$filter = $_GET['filter'] ?? 'pending';

if ($filter === 'all') {
    $sql = "SELECT a.*, u.full_name, u.student_code, sp.name AS program_name 
            FROM applications a 
            JOIN users u ON a.student_id = u.id 
            JOIN scholarship_programs sp ON a.program_id = sp.id 
            ORDER BY a.id DESC";
} else {
    $sql = "SELECT a.*, u.full_name, u.student_code, sp.name AS program_name 
            FROM applications a 
            JOIN users u ON a.student_id = u.id 
            JOIN scholarship_programs sp ON a.program_id = sp.id 
            WHERE a.eligible IS NULL 
            ORDER BY a.id ASC";
}
$applications = $pdo->query($sql)->fetchAll();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-cpu me-2 text-primary"></i> Eligibility Engine</h1>
            <p class="page-subtitle">Automatic validation of GPA, Extracurricular Activities, and Failed Subjects against rules.</p>
        </div>
        <div>
            <a href="index.php?run_all=1" class="btn btn-primary" onclick="return confirm('Run Eligibility Engine on all pending applications?');">
                <i class="bi bi-play-circle-fill me-2"></i> Run Engine (Check All Pending)
            </a>
        </div>
    </div>

    <!-- Stats / Instructions -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm border-0 border-start border-primary border-4 bg-light">
                <div class="card-body">
                    <h5 class="text-primary"><i class="bi bi-info-circle me-1"></i> How it works</h5>
                    <p class="mb-0">
                        When you click <strong>Check Eligibility</strong>, the system reads from <code>eligibility_rules</code> and the candidate's <code>student_profiles</code>. 
                        It automatically checks <strong>GPA</strong>, <strong>Activities</strong>, and <strong>Failed Subjects</strong>. 
                        The result (Pass/Fail) and reason are then saved to <code>eligibility_results</code>.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-3">
        <a href="index.php?filter=pending" class="btn btn-sm <?= $filter === 'pending' ? 'btn-dark' : 'btn-outline-dark' ?>">Show Pending Only</a>
        <a href="index.php?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-dark' : 'btn-outline-dark' ?>">Show All</a>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>App ID</th>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Submitted At</th>
                        <th>Current Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <td>#<?= e($app['id']) ?></td>
                            <td>
                                <strong><?= e($app['full_name']) ?></strong><br>
                                <small class="text-muted"><?= e($app['student_code']) ?></small>
                            </td>
                            <td><?= e($app['program_name']) ?></td>
                            <td><?= e($app['submitted_at']) ?></td>
                            <td>
                                <?php if ($app['eligible'] === null): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> Pending Check</span>
                                <?php elseif ($app['eligible'] == 1): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> Pass</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Fail</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="index.php?check_id=<?= $app['id'] ?>" class="btn btn-sm btn-info text-white">
                                    <i class="bi bi-shield-check me-1"></i> Check Eligibility
                                </a>
                                <?php if ($app['eligible'] !== null): ?>
                                    <a href="../eligibility_results/index.php" class="btn btn-sm btn-outline-secondary">
                                        View Result
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($applications)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No applications found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
