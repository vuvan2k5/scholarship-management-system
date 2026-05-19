<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();
$error = '';
$users = $pdo->query('SELECT id, full_name FROM users ORDER BY full_name')->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['submit'])) {
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $faculty = trim($_POST['faculty'] ?? '');
    $major = trim($_POST['major'] ?? '');
    $gpa = trim($_POST['gpa'] ?? '');
    $activitiesCount = isset($_POST['activities_count']) ? intval($_POST['activities_count']) : 0;
    $familyIncome = trim($_POST['family_income'] ?? '');
    $isDisadvantaged = isset($_POST['is_disadvantaged']) ? 1 : 0;
    $researchCount = isset($_POST['research_count']) ? intval($_POST['research_count']) : 0;
    $failedSubjects = isset($_POST['failed_subjects']) ? intval($_POST['failed_subjects']) : 0;

    if ($userId <= 0 || $gpa === '') {
        $error = 'Student and GPA are required.';
    } else {
        $check = $pdo->prepare('SELECT id FROM student_profiles WHERE user_id = ?');
        $check->execute([$userId]);

        if ($check->rowCount() > 0) {
            $error = 'A profile already exists for this student.';
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO student_profiles (user_id, faculty, major, gpa, activities_count, family_income, is_disadvantaged, research_count, failed_subjects) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $userId,
                $faculty,
                $major,
                $gpa,
                $activitiesCount,
                $familyIncome !== '' ? $familyIncome : null,
                $isDisadvantaged,
                $researchCount,
                $failedSubjects,
            ]);

            header('Location: index.php');
            exit;
        }
    }
}
?>

<h2 class="mb-4">Create Student Profile</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label class="form-label">Student</label>
        <select name="user_id" class="form-control" required>
            <option value="">Select student</option>
            <?php foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>" <?= isset($_POST['user_id']) && intval($_POST['user_id']) === intval($user['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($user['full_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Faculty</label>
        <input type="text" name="faculty" value="<?= htmlspecialchars($_POST['faculty'] ?? '') ?>" class="form-control">
    </div>

    <div class="mb-3">
        <label class="form-label">Major</label>
        <input type="text" name="major" value="<?= htmlspecialchars($_POST['major'] ?? '') ?>" class="form-control">
    </div>

    <div class="mb-3">
        <label class="form-label">GPA</label>
        <input type="number" step="0.01" max="4" min="0" name="gpa" value="<?= htmlspecialchars($_POST['gpa'] ?? '') ?>" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Activities Count</label>
        <input type="number" min="0" name="activities_count" value="<?= htmlspecialchars($_POST['activities_count'] ?? '0') ?>" class="form-control">
    </div>

    <div class="mb-3">
        <label class="form-label">Family Income</label>
        <input type="number" step="0.01" min="0" name="family_income" value="<?= htmlspecialchars($_POST['family_income'] ?? '') ?>" class="form-control">
    </div>

    <div class="mb-3 form-check">
        <input class="form-check-input" type="checkbox" name="is_disadvantaged" id="is_disadvantaged" value="1" <?= isset($_POST['is_disadvantaged']) ? 'checked' : '' ?>>
        <label class="form-check-label" for="is_disadvantaged">Disadvantaged background</label>
    </div>

    <div class="mb-3">
        <label class="form-label">Research Count</label>
        <input type="number" min="0" name="research_count" value="<?= htmlspecialchars($_POST['research_count'] ?? '0') ?>" class="form-control">
    </div>

    <div class="mb-3">
        <label class="form-label">Failed Subjects</label>
        <input type="number" min="0" name="failed_subjects" value="<?= htmlspecialchars($_POST['failed_subjects'] ?? '0') ?>" class="form-control">
    </div>

    <button type="submit" name="submit" class="btn btn-primary">Save</button>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>