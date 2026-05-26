<?php
// ============================================================
// admin/reports/create.php
// ============================================================

$pageTitle = 'Add Report';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title      = trim($_POST['title']);
    $content    = trim($_POST['content']);
    $created_at = trim($_POST['created_at']);

    if (empty($title) || empty($content)) {
        $error = 'Please fill in all required fields.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO reports (title, content, created_at)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $title,
            $content,
            $created_at !== '' ? $created_at : null,
        ]);
        header('Location: index.php');
        exit;
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="container py-4">
    <!-- PAGE HEADER -->
    <div class="mb-4">
        <h1 class="page-title">Add Report Summary</h1>
        <p class="page-subtitle">Publish a new system report or administrative review log</p>
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
                        <label class="form-label">Report Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Q1 2026 Disbursement Review" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Content / Summary <span class="text-danger">*</span></label>
                        <textarea name="content" class="form-control" rows="6" placeholder="Enter report contents, analysis details..." required></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Report Created At</label>
                        <input type="datetime-local" name="created_at" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save Report</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
