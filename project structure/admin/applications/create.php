<?php
// ============================================================
// admin/applications/create.php
// ============================================================

$pageTitle = 'Create Application';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

$pdo = getDB();
$error = '';

$students = $pdo->query('SELECT id, full_name FROM users WHERE role = "student" ORDER BY full_name')->fetchAll(PDO::FETCH_ASSOC);
$programs = $pdo->query('SELECT id, name FROM scholarship_programs ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['submit'])) {
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $program_id = isset($_POST['program_id']) ? intval($_POST['program_id']) : 0;

    if ($student_id <= 0 || $program_id <= 0) {
        $error = 'Student and program are required.';
    } else {
        $check = $pdo->prepare('SELECT id FROM applications WHERE student_id = ? AND program_id = ?');
        $check->execute([$student_id, $program_id]);

        if ($check->rowCount() > 0) {
            $error = 'This student has already applied for the selected program.';
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO applications (student_id, program_id, status, submitted_at) VALUES (?, ?, ?, NOW())'
            );
            $insert->execute([$student_id, $program_id, 'submitted']);
            header('Location: index.php');
            exit;
        }
    }
}
?>

<div class="container py-4">
    <!-- PAGE HEADER -->
    <div class="mb-4">
        <h1 class="page-title">Create Application</h1>
        <p class="page-subtitle">Submit a scholarship application record manually on behalf of a student</p>
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
                        <label class="form-label">Student <span class="text-danger">*</span></label>
                        <select name="student_id" class="form-select" required>
                            <option value="">Select student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['id'] ?>" <?= isset($_POST['student_id']) && intval($_POST['student_id']) === intval($student['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($student['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Program <span class="text-danger">*</span></label>
                        <select name="program_id" class="form-select" required>
                            <option value="">Select program</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?= $program['id'] ?>" <?= isset($_POST['program_id']) && intval($_POST['program_id']) === intval($program['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($program['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="submit" class="btn btn-primary">Create Application</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
