<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/header.php';

$pageTitle = 'Edit Student Profile';

$pdo = getDB();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';

$stmt = $pdo->prepare('SELECT * FROM student_profiles WHERE id = ?');
$stmt->execute([$id]);
$profile = $stmt->fetch();

if (!$profile) {
    echo '<div class="alert alert-danger">Profile not found.</div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$users = $pdo->query('SELECT id, full_name FROM users ORDER BY full_name')->fetchAll();

if (isset($_POST['update'])) {
    $userId = trim($_POST['user_id']);
    $faculty = trim($_POST['faculty']);
    $major = trim($_POST['major']);
    $gpa = trim($_POST['gpa']);
    $activitiesCount = trim($_POST['activities_count']);
    $familyIncome = trim($_POST['family_income']);
    $isDisadvantaged = isset($_POST['is_disadvantaged']) ? 1 : 0;
    $researchCount = trim($_POST['research_count']);
    $failedSubjects = trim($_POST['failed_subjects']);

    if ($userId === '' || $gpa === '') {
        $error = 'User and GPA are required.';
    } else {
        $check = $pdo->prepare('SELECT id FROM student_profiles WHERE user_id = ? AND id <> ?');
        $check->execute([$userId, $id]);
        if ($check->rowCount() > 0) {
            $error = 'Another profile already exists for this user.';
        } else {
            $update = $pdo->prepare(
                "UPDATE student_profiles SET user_id = ?, faculty = ?, major = ?, gpa = ?, activities_count = ?, family_income = ?, is_disadvantaged = ?, research_count = ?, failed_subjects = ? WHERE id = ?"
            );
            $update->execute([
                $userId,
                $faculty,
                $major,
                $gpa,
                $activitiesCount ?: 0,
                $familyIncome ?: null,
                $isDisadvantaged,
                $researchCount ?: 0,
                $failedSubjects ?: 0,
                $id,
            ]);
            header('Location: index.php');
            exit;
        }
    }
}

?>

<h2 class="mb-4">Edit Student Profile</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label class="form-label">Student</label>
        <select name="user_id" class="form-control" required>
            <option value="">Select student</option>
            <?php foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>" <?= $user['id'] == $profile['user_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($user['full_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Faculty</label>
        <input type="text" name="faculty" value="<?= htmlspecialchars($profile['faculty']) ?>" class="form-control">
    </div>

    <div class="mb-3">
        <label class="form-label">Major</label>
        <input type="text" name="major" value="<?= htmlspecialchars($profile['major']) ?>" class="form-control">
    </div>

    <div class="mb-3">
        <label class="form-label">GPA</label>
        <input type="number" step="0.01" max="4" min="0" name="gpa" value="<?= htmlspecialchars($profile['gpa']) ?>" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Activities Count</label>
        <input type="number" min="0" name="activities_count" value="<?= htmlspecialchars($profile['activities_count']) ?>" class="form-control">
    </div>

    <div class="mb-3">
        <label class="form-label">Family Income</label>
        <input type="number" step="0.01" min="0" name="family_income" value="<?= htmlspecialchars($profile['family_income']) ?>" class="form-control">
    </div>

    <div class="mb-3 form-check">
        <input class="form-check-input" type="checkbox" name="is_disadvantaged" id="is_disadvantaged" value="1" <?= $profile['is_disadvantaged'] ? 'checked' : '' ?> >
        <label class="form-check-label" for="is_disadvantaged">Disadvantaged background</label>
    </div>

    <div class="mb-3">
        <label class="form-label">Research Count</label>
        <input type="number" min="0" name="research_count" value="<?= htmlspecialchars($profile['research_count']) ?>" class="form-control">
    </div>

    <div class="mb-3">
        <label class="form-label">Failed Subjects</label>
        <input type="number" min="0" name="failed_subjects" value="<?= htmlspecialchars($profile['failed_subjects']) ?>" class="form-control">
    </div>

    <button type="submit" name="update" class="btn btn-success">Update Profile</button>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

