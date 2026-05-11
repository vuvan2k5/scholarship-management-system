<?php

include '../../config/db.php';
include '../../includes/header.php';

$pageTitle = 'Edit Application';
$pdo = getDB();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';

$stmt = $pdo->prepare('SELECT * FROM applications WHERE id = ?');
$stmt->execute([$id]);
$app = $stmt->fetch();

if (!$app) {
    echo '<div class="alert alert-danger">Application not found.</div>';
    include '../../includes/footer.php';
    exit;
}

$status = $app['status'];
$eligible = $app['eligible'];

if (isset($_POST['update'])) {
    $status = $_POST['status'];
    $eligible = $_POST['eligible'] !== '' ? intval($_POST['eligible']) : null;

    $update = $pdo->prepare(
        'UPDATE applications SET status = ?, eligible = ? WHERE id = ?'
    );
    $update->execute([$status, $eligible, $id]);

    header('Location: index.php');
    exit;
}
?>

<h2 class="mb-4">Edit Application</h2>

<form method="POST">
    <div class="mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-control" required>
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

    <div class="mb-3">
        <label class="form-label">Eligible</label>
        <select name="eligible" class="form-control">
            <option value="" <?= $eligible === null ? 'selected' : '' ?>>Unknown</option>
            <option value="1" <?= intval($eligible) === 1 ? 'selected' : '' ?>>Yes</option>
            <option value="0" <?= intval($eligible) === 0 && $eligible !== null ? 'selected' : '' ?>>No</option>
        </select>
    </div>

    <button type="submit" name="update" class="btn btn-success">Update</button>
</form>

<?php include '../../includes/footer.php'; ?>
