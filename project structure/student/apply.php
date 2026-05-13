<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();
requireRole('student');

$pdo = getDB();
$error = '';
$success = '';
$programs = $pdo->query('SELECT id, name FROM scholarship_programs ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['submit'])) {
    $programId = isset($_POST['program_id']) ? intval($_POST['program_id']) : 0;
    if ($programId <= 0) {
        $error = 'Vui lòng chọn chương trình học bổng.';
    } else {
        $studentId = currentUserId();
        $check = $pdo->prepare('SELECT id FROM applications WHERE student_id = ? AND program_id = ?');
        $check->execute([$studentId, $programId]);

        if ($check->rowCount() > 0) {
            $error = 'Bạn đã nộp hồ sơ cho chương trình này rồi.';
        } else {
            $insert = $pdo->prepare('INSERT INTO applications (student_id, program_id, status, submitted_at) VALUES (?, ?, ?, NOW())');
            $insert->execute([$studentId, $programId, 'submitted']);
            $success = 'Hồ sơ của bạn đã được nộp thành công.';
        }
    }
}
?>

<h2 class="mb-4">Nộp hồ sơ học bổng</h2>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label class="form-label">Chương trình</label>
        <select name="program_id" class="form-control" required>
            <option value="">Chọn chương trình</option>
            <?php foreach ($programs as $program): ?>
                <option value="<?= $program['id'] ?>" <?= isset($_POST['program_id']) && intval($_POST['program_id']) === intval($program['id']) ? 'selected' : '' ?>>
                    <?= e($program['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" name="submit" class="btn btn-primary">Nộp hồ sơ</button>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
