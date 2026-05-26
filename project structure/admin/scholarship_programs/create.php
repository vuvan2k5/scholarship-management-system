<?php
// ============================================================
// admin/scholarship_programs/create.php
// ============================================================

$pageTitle = 'Create Scholarship Program';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
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
        $sql = "INSERT INTO scholarship_programs (name, description, budget, slots, start_date, end_date, status)
                VALUES (:name, :description, :budget, :slots, :start_date, :end_date, :status)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':budget' => $budget,
            ':slots' => $slots,
            ':start_date' => !empty($start_date) ? $start_date : null,
            ':end_date' => !empty($end_date) ? $end_date : null,
            ':status' => $status
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
        <h1 class="page-title">Create Scholarship Program</h1>
        <p class="page-subtitle">Add a new scholarship program to the system</p>
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
                        <input type="text" name="name" class="form-control" placeholder="e.g. Merit Scholarship 2026" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Enter program details..."></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Budget (VND) <span class="text-danger">*</span></label>
                            <input type="number" name="budget" class="form-control" placeholder="Total funding" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Slots <span class="text-danger">*</span></label>
                            <input type="number" name="slots" class="form-control" placeholder="Number of slots" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active (Open)</option>
                            <option value="inactive">Inactive (Closed)</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="submit" class="btn btn-primary">
                            Create Program
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