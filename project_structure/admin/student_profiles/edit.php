<?php
// ============================================================
// admin/student_profiles/edit.php
// ============================================================

$pageTitle = 'Edit Student Profile';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';

$stmt = $pdo->prepare('SELECT * FROM student_profiles WHERE id = ?');
$stmt->execute([$id]);
$profile = $stmt->fetch();

$users = $pdo->query("SELECT id, full_name FROM users WHERE role = 'student' ORDER BY full_name")->fetchAll();

if (isset($_POST['update'])) {
    $userId          = trim($_POST['user_id']);
    $faculty         = trim($_POST['faculty']);
    $major           = trim($_POST['major']);
    $gpa             = trim($_POST['gpa']);
    $activitiesCount = trim($_POST['activities_count']);
    // $familyIncome    = trim($_POST['family_income']);
    $isDisadvantaged = isset($_POST['is_disadvantaged']) ? 1 : 0;
    $researchCount   = trim($_POST['research_count']);
    $failedSubjects  = trim($_POST['failed_subjects']);

    if ($userId === '' || $gpa === '') {
        $error = 'User and GPA are required.';
    } else {
        $check = $pdo->prepare('SELECT id FROM student_profiles WHERE student_id = ? AND id <> ?');
        $check->execute([$userId, $id]);
        if ($check->rowCount() > 0) {
            $error = 'Another profile already exists for this user.';
        } else {
            $update = $pdo->prepare(
                "UPDATE student_profiles SET student_id = ?, faculty = ?, major = ?, gpa = ?, activities_count = ?, is_disadvantaged = ?, research_count = ?, failed_subjects = ? WHERE id = ?"
            );
            $update->execute([
                $userId, $faculty, $major, $gpa,
                $activitiesCount ?: 0,
                // $familyIncome    ?: null,
                $isDisadvantaged,
                $researchCount   ?: 0,
                $failedSubjects  ?: 0,
                $id,
            ]);
            header('Location: index.php');
            exit;
        }
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

if (!$profile): ?>
    <div class="container py-5"><div class="alert alert-danger">Profile not found.</div></div>
<?php
    require_once '../../includes/footer.php';
    exit;
endif;
?>

<div class="container py-4">
    <!-- PAGE HEADER -->
    <div class="mb-4">
        <h1 class="page-title">Edit Student Profile</h1>
        <p class="page-subtitle">Modify student profile records and metrics</p>
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
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= $user['id'] == $profile['student_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Faculty</label>
                            <input type="text" name="faculty" value="<?= htmlspecialchars($profile['faculty']) ?>" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Major</label>
                            <input type="text" name="major" value="<?= htmlspecialchars($profile['major']) ?>" class="form-control">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">GPA (4.0 scale) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" max="4" min="0" name="gpa" value="<?= htmlspecialchars($profile['gpa']) ?>" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Extracurricular Activities</label>
                            <input type="number" min="0" name="activities_count" value="<?= htmlspecialchars($profile['activities_count']) ?>" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Research Papers Count</label>
                            <input type="number" min="0" name="research_count" value="<?= htmlspecialchars($profile['research_count']) ?>" class="form-control">
                        </div>
                    </div>

                    <div class="row">
                        <!-- <div class="col-md-6 mb-3">
                            <label class="form-label">Monthly Family Income (VND)</label>
                            <input type="number" step="0.01" min="0" name="family_income" value="<?= htmlspecialchars($profile['family_income']) ?>" class="form-control">
                        </div> -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Failed Subjects Count</label>
                            <input type="number" min="0" name="failed_subjects" value="<?= htmlspecialchars($profile['failed_subjects']) ?>" class="form-control">
                        </div>
                    </div>

                    <div class="mb-4 form-check">
                        <input class="form-check-input" type="checkbox" name="is_disadvantaged" id="is_disadvantaged" value="1" <?= $profile['is_disadvantaged'] ? 'checked' : '' ?> >
                        <label class="form-check-label" for="is_disadvantaged">Disadvantaged / Difficult Background</label>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="update" class="btn btn-primary">Update Profile</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
