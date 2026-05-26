<?php
// ============================================================
// admin/eligibility_results/create.php
// ============================================================

$pageTitle = 'Create Eligibility Result';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$error = "";

// Fetch all applications
$apps = $pdo->query("
    SELECT a.id, u.full_name, sp.name AS program_name 
    FROM applications a 
    JOIN users u ON a.student_id = u.id 
    JOIN scholarship_programs sp ON a.program_id = sp.id 
    ORDER BY a.id DESC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = $_POST['application_id'];
    $is_passed = $_POST['is_passed'];
    $reason = trim($_POST['reason']);

    if (empty($application_id)) {
        $error = "Please select an application.";
    } else {
        $sql = "INSERT INTO eligibility_results (application_id, is_passed, reason) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$application_id, $is_passed, $reason]);

        header("Location: index.php");
        exit;
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="container py-4">
    <!-- PAGE HEADER -->
    <div class="mb-4">
        <h1 class="page-title">Create Eligibility Result</h1>
        <p class="page-subtitle">Manually record candidate eligibility check outcomes</p>
    </div>

    <!-- ALERTS -->
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- FORM -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="form-card">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Select Application <span class="text-danger">*</span></label>
                        <select name="application_id" class="form-select" required>
                            <option value="">-- Select Application --</option>
                            <?php foreach ($apps as $app): ?>
                                <option value="<?= e($app['id']) ?>">
                                    #<?= e($app['id']) ?> - <?= e($app['full_name']) ?> (<?= e($app['program_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Eligibility Status</label>
                        <select name="is_passed" class="form-select">
                            <option value="1">PASS (Qualified)</option>
                            <option value="0">FAIL (Disqualified)</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Reason / Explanation</label>
                        <textarea name="reason" class="form-control" rows="5" placeholder="Enter rule check details, criteria failed or comments..."></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            Create Result
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>