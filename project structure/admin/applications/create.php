<?php

include '../../config/db.php';
include '../../includes/header.php';

$pageTitle = 'Create Application';

$error = '';
$studentId = '';
$programId = '';

$pdo = getDB();

$students = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll();
$programs = $pdo->query("SELECT id, name FROM scholarship_programs ORDER BY name")->fetchAll();

if (isset($_POST['submit'])) {
    $studentId = trim($_POST['student_id']);
    $programId = trim($_POST['program_id']);

    if ($studentId === '' || $programId === '') {
        $error = 'All fields are required.';
    } else {
        $check = $pdo->prepare(
            "SELECT * FROM applications WHERE student_id = ? AND program_id = ?"
        );
        $check->execute([$studentId, $programId]);

        if ($check->rowCount() > 0) {
            $error = 'This student already applied for the selected program.';
        } else {
            $insert = $pdo->prepare(
                "INSERT INTO applications (student_id, program_id) VALUES (?, ?)"
            );
            $insert->execute([$studentId, $programId]);
            header('Location: index.php');
            exit;
        }
    }
}
?>

<h2 class="mb-4">Create Application</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label class="form-label">Student</label>
        <select name="student_id" class="form-control" required>
            <option value="">Select student</option>
            <?php foreach ($students as $student): ?>
                <option value="<?= $student['id'] ?>" <?= $student['id'] == $studentId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($student['full_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Scholarship Program</label>
        <select name="program_id" class="form-control" required>
            <option value="">Select program</option>
            <?php foreach ($programs as $program): ?>
                <option value="<?= $program['id'] ?>" <?= $program['id'] == $programId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($program['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" name="submit" class="btn btn-primary">Submit</button>
</form>

<?php include '../../includes/footer.php'; ?>
