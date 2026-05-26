<?php
// ============================================================
// admin/reports/edit.php
// ============================================================

$pageTitle = 'Edit Report';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    require_once '../../includes/header.php';
    require_once '../../includes/navbar.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Report record not found.</div></div>';
    require_once '../../includes/footer.php';
    exit;
}

$datetimeValue = !empty($row['created_at']) ? date('Y-m-d\TH:i', strtotime($row['created_at'])) : '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title      = trim($_POST['title']);
    $content    = trim($_POST['content']);
    $created_at = trim($_POST['created_at']);

    if (empty($title) || empty($content)) {
        $error = 'Please fill in all required fields.';
    } else {
        $update = $pdo->prepare("
            UPDATE reports
            SET title      = ?,
                content    = ?,
                created_at = ?
            WHERE id = ?
        ");
        $update->execute([
            $title,
            $content,
            $created_at !== '' ? $created_at : null,
            $id,
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
        <h1 class="page-title">Edit Report Summary</h1>
        <p class="page-subtitle">Modify existing administrative summary contents</p>
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
                        <input type="text" name="title" class="form-control" value="<?= e($row['title']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Content / Summary <span class="text-danger">*</span></label>
                        <textarea name="content" class="form-control" rows="6" required><?= e($row['content']) ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Report Created At</label>
                        <input type="datetime-local" name="created_at" class="form-control" value="<?= e($datetimeValue) ?>">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Report</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
