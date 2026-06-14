<?php
// ============================================================
// admin/applications/edit.php
// ============================================================

$pageTitle = 'Edit Application';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';

$stmt = $pdo->prepare('
    SELECT a.*, u.full_name, sp.name AS program_name 
    FROM applications a
    JOIN users u ON a.student_id = u.id
    JOIN scholarship_programs sp ON a.program_id = sp.id
    WHERE a.id = ?
');
$stmt->execute([$id]);
$app = $stmt->fetch();

if (!$app) {
    require_once '../../includes/header.php';
    require_once '../../includes/navbar.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Application not found.</div></div>';
    require_once '../../includes/footer.php';
    exit;
}

$status = $app['status'];
$eligible = $app['eligible'];

if (isset($_POST['update'])) {
    $oldStatus = $app['status']; // capture before overwrite
    $status    = $_POST['status'];
    $eligible  = $_POST['eligible'] !== '' ? intval($_POST['eligible']) : null;

    $update = $pdo->prepare(
        'UPDATE applications SET status = ?, eligible = ? WHERE id = ?'
    );
    $update->execute([$status, $eligible, $id]);

    // ── Auto-Notifications on status change ──────────────────
    require_once '../../includes/notifications.php';
    if ($oldStatus !== 'approved' && $status === 'approved') {
        notifyApplicationApproved($pdo, $id);
    }
    if ($oldStatus !== 'rejected' && $status === 'rejected') {
        notifyApplicationRejected($pdo, $id);
    }

    setFlash('success', 'Application #' . $id . ' updated successfully.');
    header('Location: index.php');
    exit;
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="container py-4">
    <!-- PAGE HEADER -->
    <div class="mb-4">
        <h1 class="page-title">Edit Application</h1>
        <p class="page-subtitle">Update status and eligibility details for Application #<?= e($id) ?></p>
    </div>

    <!-- DETAILS CARD -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card mb-4 bg-light">
                <div class="card-body">
                    <h6 class="card-title text-muted text-uppercase small">Applicant Details</h6>
                    <p class="mb-1"><strong>Student Name:</strong> <?= e($app['full_name']) ?></p>
                    <p class="mb-0"><strong>Program Name:</strong> <?= e($app['program_name']) ?></p>
                </div>
            </div>

            <div class="form-card">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label font-semibold">Application Status</label>
                        <select name="status" class="form-select" required>
                            <?php
                            $statusOptions = [
                                'draft' => 'Draft',
                                'submitted' => 'Submitted',
                                'reviewing' => 'Reviewing',
                                'eligible' => 'Eligible',
                                'ineligible' => 'Ineligible',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'disbursed' => 'Disbursed',
                            ];
                            foreach ($statusOptions as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $value === $status ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label font-semibold">Eligibility Verification</label>
                        <select name="eligible" class="form-select">
                            <option value="" <?= $eligible === null ? 'selected' : '' ?>>Unknown</option>
                            <option value="1" <?= intval($eligible) === 1 ? 'selected' : '' ?>>Yes (Eligible)</option>
                            <option value="0" <?= intval($eligible) === 0 && $eligible !== null ? 'selected' : '' ?>>No
                                (Ineligible)</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="update" class="btn btn-primary">Update Application</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>