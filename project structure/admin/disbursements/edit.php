<?php
// ============================================================
// admin/disbursements/edit.php
// ============================================================

$pageTitle = 'Edit Disbursement';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM disbursements WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    require_once '../../includes/header.php';
    require_once '../../includes/navbar.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Disbursement record not found.</div></div>';
    require_once '../../includes/footer.php';
    exit;
}

$datetimeValue = '';
if (!empty($row['disbursed_at'])) {
    $datetimeValue = date('Y-m-d\TH:i', strtotime($row['disbursed_at']));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = trim($_POST['application_id']);
    $amount         = trim($_POST['amount']);
    $status         = trim($_POST['status']);
    $disbursed_at   = trim($_POST['disbursed_at']);
    $note           = trim($_POST['note']);

    if (empty($application_id) || empty($amount) || empty($status)) {
        $error = 'Please fill in all required fields.';
    } else {
        $update = $pdo->prepare("
            UPDATE disbursements
            SET application_id = ?,
                amount         = ?,
                status         = ?,
                disbursed_at   = ?,
                note           = ?
            WHERE id = ?
        ");
        $update->execute([
            $application_id,
            $amount,
            $status,
            $disbursed_at !== '' ? $disbursed_at : null,
            $note !== '' ? $note : null,
            $id,
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
        <h1 class="page-title">Edit Disbursement Record</h1>
        <p class="page-subtitle">Modify disbursement details and payout transaction status</p>
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
                            <label class="form-label">Payout Amount (VND) <span class="text-danger">*</span></label>
                            <input type="number" step="1" name="amount" class="form-control" value="<?= e($row['amount']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="pending"  <?= $row['status'] === 'pending'  ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $row['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="paid"     <?= $row['status'] === 'paid'     ? 'selected' : '' ?>>Paid (Completed)</option>
                                <option value="failed"   <?= $row['status'] === 'failed'   ? 'selected' : '' ?>>Failed</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Disbursed Date/Time</label>
                        <input type="datetime-local" name="disbursed_at" class="form-control" value="<?= e($datetimeValue) ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Notes / Remarks</label>
                        <textarea name="note" class="form-control" rows="3"><?= e($row['note']) ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Disbursement</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
