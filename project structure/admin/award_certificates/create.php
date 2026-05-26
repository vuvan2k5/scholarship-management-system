<?php
// ============================================================
// admin/award_certificates/create.php
// ============================================================

$pageTitle = 'Issue Certificate';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id   = trim($_POST['application_id']);
    $certificate_code = trim($_POST['certificate_code']);
    $issued_at        = trim($_POST['issued_at']);
    $file_path        = trim($_POST['file_path']);

    if (empty($application_id) || empty($certificate_code)) {
        $error = 'Please fill in all required fields.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO award_certificates (application_id, certificate_code, issued_at, file_path)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $application_id,
            $certificate_code,
            $issued_at !== '' ? $issued_at : null,
            $file_path !== '' ? $file_path : null,
        ]);
        header('Location: index.php');
        exit;
    }
}

// Fetch applications for dropdown
$apps = $pdo->query("
    SELECT a.id, u.full_name, sp.name AS program_name
    FROM applications a
    JOIN users u ON a.student_id = u.id
    JOIN scholarship_programs sp ON a.program_id = sp.id
    ORDER BY a.id DESC
")->fetchAll();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="container py-4">
    <!-- PAGE HEADER -->
    <div class="mb-4">
        <h1 class="page-title">Issue Award Certificate</h1>
        <p class="page-subtitle">Officially issue a scholarship award certificate to an applicant</p>
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
                        <label class="form-label">Certificate Code <span class="text-danger">*</span></label>
                        <input type="text" name="certificate_code" class="form-control" placeholder="e.g. CERT-2026-0001" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Issued At</label>
                            <input type="datetime-local" name="issued_at" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Certificate PDF Link / Path</label>
                            <input type="text" name="file_path" class="form-control" placeholder="e.g. /uploads/certs/cert_01.pdf">
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">Issue Certificate</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
