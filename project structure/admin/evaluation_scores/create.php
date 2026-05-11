<?php

include '../../config/db.php';
include 'helpers.php';
include '../../includes/header.php';

$pageTitle = 'Create Evaluation Score';
$pdo = getDB();
$error = '';

$applications = $pdo->query(
    "SELECT a.id, u.full_name AS student_name, s.name AS program_name
      FROM applications a
      JOIN users u ON a.student_id = u.id
      JOIN scholarship_programs s ON a.program_id = s.id
      ORDER BY a.id DESC"
)->fetchAll();
$criteria = $pdo->query('SELECT id, name, max_score FROM scoring_criteria ORDER BY name')->fetchAll();
$reviewers = $pdo->query('SELECT id, full_name FROM users ORDER BY full_name')->fetchAll();

$applicationId = '';
$criteriaId = '';
$councilId = '';
$score = '';
$note = '';

if (isset($_POST['submit'])) {
    $applicationId = trim($_POST['application_id']);
    $criteriaId = trim($_POST['criteria_id']);
    $councilId = trim($_POST['council_id']);
    $score = trim($_POST['score']);
    $note = trim($_POST['note']);

    if ($applicationId === '' || $criteriaId === '' || $councilId === '' || $score === '') {
        $error = 'All fields are required.';
    } else {
        $maxStmt = $pdo->prepare('SELECT max_score FROM scoring_criteria WHERE id = ?');
        $maxStmt->execute([$criteriaId]);
        $criteriaData = $maxStmt->fetch();

        if (!$criteriaData) {
            $error = 'Selected criteria does not exist.';
        } elseif ((float)$score > (float)$criteriaData['max_score']) {
            $error = 'Score exceeds maximum value for this criteria.';
        } else {
            $duplicateStmt = $pdo->prepare(
                'SELECT id FROM evaluation_scores WHERE application_id = ? AND criteria_id = ? AND council_id = ?'
            );
            $duplicateStmt->execute([$applicationId, $criteriaId, $councilId]);

            if ($duplicateStmt->rowCount() > 0) {
                $error = 'This reviewer already submitted a score for this criteria and application.';
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO evaluation_scores (application_id, criteria_id, council_id, score, note) VALUES (?, ?, ?, ?, ?)'
                );
                $insert->execute([$applicationId, $criteriaId, $councilId, $score, $note]);
                processApplicationScores($pdo, (int)$applicationId);
                header('Location: index.php');
                exit;
            }
        }
    }
}
?>

<h2 class="mb-4">Create Evaluation Score</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label class="form-label">Application</label>
        <select name="application_id" class="form-control" required>
            <option value="">Select application</option>
            <?php foreach ($applications as $application): ?>
                <option value="<?= $application['id'] ?>" <?= $application['id'] == $applicationId ? 'selected' : '' ?>>
                    #<?= $application['id'] ?> - <?= htmlspecialchars($application['student_name']) ?> (<?= htmlspecialchars($application['program_name']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Criteria</label>
        <select name="criteria_id" class="form-control" required>
            <option value="">Select criteria</option>
            <?php foreach ($criteria as $item): ?>
                <option value="<?= $item['id'] ?>" <?= $item['id'] == $criteriaId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($item['name']) ?> (Max <?= $item['max_score'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Reviewer</label>
        <select name="council_id" class="form-control" required>
            <option value="">Select reviewer</option>
            <?php foreach ($reviewers as $reviewer): ?>
                <option value="<?= $reviewer['id'] ?>" <?= $reviewer['id'] == $councilId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($reviewer['full_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Score</label>
        <input type="number" step="0.01" min="0" name="score" value="<?= htmlspecialchars($score) ?>" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Note</label>
        <textarea name="note" class="form-control"><?= htmlspecialchars($note) ?></textarea>
    </div>

    <button type="submit" name="submit" class="btn btn-primary">Submit Score</button>
</form>

<?php include '../../includes/footer.php'; ?>
