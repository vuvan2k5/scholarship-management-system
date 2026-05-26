<?php
// ============================================================
// admin/ranking_results/edit.php
// ============================================================

$pageTitle = 'Edit Ranking Result';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM ranking_results WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    require_once '../../includes/header.php';
    require_once '../../includes/navbar.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Ranking record not found.</div></div>';
    require_once '../../includes/footer.php';
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = trim($_POST['application_id']);
    $total_score    = trim($_POST['total_score']);
    $rank           = trim($_POST['rank']);
    $recommended    = isset($_POST['recommended']) ? (int)$_POST['recommended'] : 0;

    if (empty($application_id) || $total_score === '' || $rank === '') {
        $error = 'Please fill in all required fields.';
    } else {
        $update = $pdo->prepare("
            UPDATE ranking_results
            SET application_id = ?,
                total_score    = ?,
                `rank`         = ?,
                recommended    = ?
            WHERE id = ?
        ");
        $update->execute([$application_id, $total_score, $rank, $recommended, $id]);
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
        <h1 class="page-title">Edit Ranking Record</h1>
        <p class="page-subtitle">Modify candidate ranking metrics and recommendation status</p>
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
                            <?php foreach ($apps as $app): ?>
                                <option value="<?= e($app['id']) ?>" <?= $row['application_id'] == $app['id'] ? 'selected' : '' ?>>
                                    #<?= e($app['id']) ?> - <?= e($app['full_name']) ?> (<?= e($app['program_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Total Score <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="total_score" class="form-control" value="<?= e($row['total_score']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rank Position <span class="text-danger">*</span></label>
                            <input type="number" name="rank" class="form-control" value="<?= e($row['rank']) ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Recommended Status</label>
                        <select name="recommended" class="form-select">
                            <option value="0" <?= $row['recommended'] == 0 ? 'selected' : '' ?>>No (Under Review)</option>
                            <option value="1" <?= $row['recommended'] == 1 ? 'selected' : '' ?>>Yes (Recommend Approved)</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Ranking</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
