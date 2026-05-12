<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();
$error = '';
$students = $pdo->query('SELECT id, full_name FROM users ORDER BY full_name')->fetchAll(PDO::FETCH_ASSOC);
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

<h2 class="mb-4">Create Application</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label class="form-label">Student</label>
        <select name="student_id" class="form-control" required>
            <option value="">Select student</option>
            <?php foreach ($students as $student): ?>
                <option value="<?= $student['id'] ?>" <?= isset($_POST['student_id']) && intval($_POST['student_id']) === intval($student['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($student['full_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Program</label>
        <select name="program_id" class="form-control" required>
            <option value="">Select program</option>
            <?php foreach ($programs as $program): ?>
                <option value="<?= $program['id'] ?>" <?= isset($_POST['program_id']) && intval($_POST['program_id']) === intval($program['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($program['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" name="submit" class="btn btn-primary">Submit</button>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
