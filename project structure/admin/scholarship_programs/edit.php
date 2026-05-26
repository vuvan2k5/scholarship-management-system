<?php
// ============================================================
// admin/scholarship_programs/edit.php
// ============================================================

$pageTitle = 'Edit Scholarship Program';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch current program
$sql = "SELECT * FROM scholarship_programs WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$program = $stmt->fetch();

if (!$program) {
    require_once '../../includes/header.php';
    require_once '../../includes/navbar.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Scholarship program not found.</div></div>';
    require_once '../../includes/footer.php';
    exit;
}

$error = "";

if (isset($_POST['submit'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $budget = trim($_POST['budget']);
    $slots = trim($_POST['slots']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $status = $_POST['status'];

    if (empty($name) || empty($budget) || empty($slots)) {
        $error = "Please fill in all required fields.";
    } else {
        $update = "UPDATE scholarship_programs SET
                    name = :name,
                    description = :description,
                    budget = :budget,
                    slots = :slots,
                    start_date = :start_date,
                    end_date = :end_date,
                    status = :status
                    WHERE id = :id";
        
        $stmt = $pdo->prepare($update);
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':budget' => $budget,
            ':slots' => $slots,
            ':start_date' => !empty($start_date) ? $start_date : null,
            ':end_date' => !empty($end_date) ? $end_date : null,
            ':status' => $status,
            ':id' => $id
        ]);

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
        <h1 class="page-title">Edit Scholarship Program</h1>
        <p class="page-subtitle">Modify scholarship program specifications</p>
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
                        <label class="form-label">Program Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= e($program['name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4"><?= e($program['description']) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Budget (VND) <span class="text-danger">*</span></label>
                            <input type="number" name="budget" class="form-control" value="<?= e($program['budget']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Slots <span class="text-danger">*</span></label>
                            <input type="number" name="slots" class="form-control" value="<?= e($program['slots']) ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= e($program['start_date']) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?= e($program['end_date']) ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= $program['status'] === 'active' ? 'selected' : '' ?>>Active (Open)</option>
                            <option value="inactive" <?= $program['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Closed)</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="submit" class="btn btn-primary">
                            Update Program
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