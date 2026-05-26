<?php
// ============================================================
// admin/scoring_criteria/edit.php
// ============================================================

$pageTitle = 'Edit Scoring Criterion';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch current criterion
$sql = "SELECT * FROM scoring_criteria WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$criterion = $stmt->fetch();

if (!$criterion) {
    require_once '../../includes/header.php';
    require_once '../../includes/navbar.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Scoring criterion not found.</div></div>';
    require_once '../../includes/footer.php';
    exit;
}

$error = "";

// Fetch all programs
$programs = $pdo->query("SELECT * FROM scholarship_programs ORDER BY id DESC")->fetchAll();

if (isset($_POST['submit'])) {
    $program_id = $_POST['program_id'];
    $criterion_name = trim($_POST['criterion_name']);
    $weight = trim($_POST['weight']);
    $max_score = trim($_POST['max_score']);

    if (empty($program_id) || empty($criterion_name) || empty($weight) || empty($max_score)) {
        $error = "Please fill in all required fields.";
    } else {
        $update = "UPDATE scoring_criteria SET
                    program_id = :program_id,
                    criterion_name = :criterion_name,
                    weight = :weight,
                    max_score = :max_score
                    WHERE id = :id";
        
        $stmt = $pdo->prepare($update);
        $stmt->execute([
            ':program_id' => $program_id,
            ':criterion_name' => $criterion_name,
            ':weight' => $weight,
            ':max_score' => $max_score,
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
        <h1 class="page-title">Edit Scoring Criterion</h1>
        <p class="page-subtitle">Modify reviewer grading metrics and weights</p>
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
                        <label class="form-label">Scholarship Program <span class="text-danger">*</span></label>
                        <select name="program_id" class="form-select" required>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?= e($program['id']) ?>" <?= $criterion['program_id'] == $program['id'] ? 'selected' : '' ?>>
                                    <?= e($program['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Criterion Name <span class="text-danger">*</span></label>
                        <input type="text" name="criterion_name" class="form-control" value="<?= e($criterion['criterion_name']) ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Weight (%) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="weight" class="form-control" value="<?= e($criterion['weight']) ?>" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Maximum Score <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" name="max_score" class="form-control" value="<?= e($criterion['max_score']) ?>" required>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" name="submit" class="btn btn-primary">
                            Update Criterion
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