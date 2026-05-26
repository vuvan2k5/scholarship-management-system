<?php
// ============================================================
// admin/evaluation_scores/create.php
// ============================================================

$pageTitle = 'Create Evaluation Score';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

include 'helpers.php';

$pdo = getDB();
$error = '';

$applications = $pdo->query(
    "SELECT a.id, u.full_name AS student_name, s.name AS program_name
      FROM applications a
      JOIN users u ON a.student_id = u.id
      JOIN scholarship_programs s ON a.program_id = s.id
      ORDER BY a.id DESC"
)->fetchAll();

// Note: column name is criterion_name
$criteria = $pdo->query('SELECT id, criterion_name AS name, max_score FROM scoring_criteria ORDER BY criterion_name')->fetchAll();

// Fetch council review users (role stored as 'reviewer')
$reviewers = $pdo->query("SELECT id, full_name FROM users WHERE role = 'reviewer' ORDER BY full_name")->fetchAll();

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
            $error = 'Score exceeds maximum value (' . $criteriaData['max_score'] . ') for this criteria.';
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

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="container py-4">
    <!-- PAGE HEADER -->
    <div class="mb-4">
        <h1 class="page-title">Add Evaluation Score</h1>
        <p class="page-subtitle">Submit or override candidate criteria evaluation score</p>
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
                        <label class="form-label">Application <span class="text-danger">*</span></label>
                        <select name="application_id" class="form-select" required>
                            <option value="">Select application</option>
                            <?php foreach ($applications as $application): ?>
                                <option value="<?= $application['id'] ?>" <?= $application['id'] == $applicationId ? 'selected' : '' ?>>
                                    #<?= $application['id'] ?> - <?= htmlspecialchars($application['student_name']) ?> (<?= htmlspecialchars($application['program_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Criteria <span class="text-danger">*</span></label>
                        <select name="criteria_id" class="form-select" required>
                            <option value="">Select criteria</option>
                            <?php foreach ($criteria as $item): ?>
                                <option value="<?= $item['id'] ?>" <?= $item['id'] == $criteriaId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($item['name']) ?> (Max <?= $item['max_score'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reviewer (Council Member) <span class="text-danger">*</span></label>
                        <select name="council_id" class="form-select" required>
                            <option value="">Select reviewer</option>
                            <?php foreach ($reviewers as $reviewer): ?>
                                <option value="<?= $reviewer['id'] ?>" <?= $reviewer['id'] == $councilId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($reviewer['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Assigned Score <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="score" value="<?= htmlspecialchars($score) ?>" class="form-control" placeholder="e.g. 85.5" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Notes / Remarks</label>
                        <textarea name="note" class="form-control" rows="3" placeholder="Enter feedback details, justification..."><?= htmlspecialchars($note) ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="submit" class="btn btn-primary">Submit Score</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
