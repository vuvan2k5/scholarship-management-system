<?php

include '../../config/db.php';
include 'helpers.php';
include '../../includes/header.php';

$pageTitle = 'Edit Evaluation Score';
$pdo = getDB();
$error = '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$scoreStmt = $pdo->prepare('SELECT * FROM evaluation_scores WHERE id = ?');
$scoreStmt->execute([$id]);
$scoreData = $scoreStmt->fetch();

if (!$scoreData) {
    echo '<div class="alert alert-danger">Score record not found.</div>';
    include '../../includes/footer.php';
    exit;
}

$applications = $pdo->query(
    "SELECT a.id, u.full_name AS student_name, s.name AS program_name
      FROM applications a
      JOIN users u ON a.student_id = u.id
      JOIN scholarship_programs s ON a.program_id = s.id
      ORDER BY a.id DESC"
)->fetchAll();
$criteria = $pdo->query('SELECT id, name, max_score FROM scoring_criteria ORDER BY name')->fetchAll();
$reviewers = $pdo->query('SELECT id, full_name FROM users ORDER BY full_name')->fetchAll();

if (isset($_POST['update'])) {
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
                'SELECT id FROM evaluation_scores WHERE application_id = ? AND criteria_id = ? AND council_id = ? AND id <> ?'
            );
            $duplicateStmt->execute([$applicationId, $criteriaId, $councilId, $id]);

            if ($duplicateStmt->rowCount() > 0) {
                $error = 'Another score already exists for this reviewer, criteria, and application.';
            } else {
                $updateQuery = $pdo->prepare(
                    'UPDATE evaluation_scores SET application_id = ?, criteria_id = ?, council_id = ?, score = ?, note = ? WHERE id = ?'
                );
                $updateQuery->execute([$applicationId, $criteriaId, $councilId, $score, $note, $id]);
                processApplicationScores($pdo, (int)$applicationId);
                header('Location: index.php');
                exit;
            }
        }
    }
}

?>

<h2 class="mb-4">Edit Evaluation Score</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label class="form-label">Application</label>
        <select name="application_id" class="form-control" required>
            <option value="">Select application</option>
            <?php foreach ($applications as $application): ?>
                <option value="<?= $application['id'] ?>" <?= $application['id'] == $scoreData['application_id'] ? 'selected' : '' ?>>
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
                <option value="<?= $item['id'] ?>" <?= $item['id'] == $scoreData['criteria_id'] ? 'selected' : '' ?>>
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
                <option value="<?= $reviewer['id'] ?>" <?= $reviewer['id'] == $scoreData['council_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($reviewer['full_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Score</label>
        <input type="number" step="0.01" min="0" name="score" value="<?= htmlspecialchars($scoreData['score']) ?>" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Note</label>
        <textarea name="note" class="form-control"><?= htmlspecialchars($scoreData['note']) ?></textarea>
    </div>

    <button type="submit" name="update" class="btn btn-success">Update Score</button>
</form>

<?php include '../../includes/footer.php'; ?>
