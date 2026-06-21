<?php
// ============================================================
// admin/student_profiles/create.php
// ============================================================

$pageTitle = 'Create Student Profile';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$error = '';
$users = $pdo->query("SELECT id, full_name FROM users WHERE role = 'student' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['submit'])) {
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $faculty = trim($_POST['faculty'] ?? '');
    $major = trim($_POST['major'] ?? '');
    $gpa = trim($_POST['gpa'] ?? '');
    $activitiesCount = isset($_POST['activities_count']) ? intval($_POST['activities_count']) : 0;
    // $familyIncome = trim($_POST['family_income'] ?? '');
    $isDisadvantaged = isset($_POST['is_disadvantaged']) ? 1 : 0;
    $researchCount = isset($_POST['research_count']) ? intval($_POST['research_count']) : 0;
    $failedSubjects = isset($_POST['failed_subjects']) ? intval($_POST['failed_subjects']) : 0;

    if ($userId <= 0 || $gpa === '') {
        $error = 'Student and GPA are required.';
    } else {
        $check = $pdo->prepare('SELECT id FROM student_profiles WHERE student_id = ?');
        $check->execute([$userId]);

        if ($check->rowCount() > 0) {
            $error = 'A profile already exists for this student.';
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO student_profiles (student_id, faculty, major, gpa, activities_count, is_disadvantaged, research_count, failed_subjects) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $userId,
                $faculty,
                $major,
                $gpa,
                $activitiesCount,
                // $familyIncome !== '' ? $familyIncome : null,
                $isDisadvantaged,
                $researchCount,
                $failedSubjects,
            ]);

            header('Location: index.php');
            exit;
        }
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="container py-4">
    <!-- PAGE HEADER -->
    <div class="mb-4">
        <h1 class="page-title">Create Student Profile</h1>
        <p class="page-subtitle">Configure academic & financial status records for automated eligibility rule checks</p>
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
                        <select name="user_id" class="form-select" required>
                            <option value="">Select student</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= isset($_POST['user_id']) && intval($_POST['user_id']) === intval($user['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Faculty</label>
                            <input type="text" name="faculty" value="<?= htmlspecialchars($_POST['faculty'] ?? '') ?>" class="form-control" placeholder="e.g. Information Technology">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Major</label>
                            <input type="text" name="major" value="<?= htmlspecialchars($_POST['major'] ?? '') ?>" class="form-control" placeholder="e.g. Software Engineering">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">GPA (4.0 scale) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" max="4" min="0" name="gpa" value="<?= htmlspecialchars($_POST['gpa'] ?? '') ?>" class="form-control" placeholder="e.g. 3.5" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Extracurricular Activities</label>
                            <input type="number" min="0" name="activities_count" value="<?= htmlspecialchars($_POST['activities_count'] ?? '0') ?>" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Research Papers Count</label>
                            <input type="number" min="0" name="research_count" value="<?= htmlspecialchars($_POST['research_count'] ?? '0') ?>" class="form-control">
                        </div>
                    </div>

                    <div class="row">
                        <!-- <div class="col-md-6 mb-3">
                            <label class="form-label">Monthly Family Income (VND)</label>
                            <input type="number" step="1" min="0" name="family_income" value="" class="form-control" placeholder="e.g. 5000000">
                        </div> -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Failed Subjects Count</label>
                            <input type="number" min="0" name="failed_subjects" value="<?= htmlspecialchars($_POST['failed_subjects'] ?? '0') ?>" class="form-control">
                        </div>
                    </div>

                    <div class="mb-4 form-check">
                        <input class="form-check-input" type="checkbox" name="is_disadvantaged" id="is_disadvantaged" value="1" <?= isset($_POST['is_disadvantaged']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_disadvantaged">Disadvantaged / Difficult Background</label>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="submit" class="btn btn-primary">Save Profile</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>