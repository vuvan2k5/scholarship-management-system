<?php
// ============================================================
// admin/evaluation_scores/edit.php
// ============================================================

$pageTitle = 'Edit Evaluation Score';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

include 'helpers.php';

$pdo = getDB();
$error = '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$scoreStmt = $pdo->prepare('SELECT * FROM evaluation_scores WHERE id = ?');
$scoreStmt->execute([$id]);
$scoreData = $scoreStmt->fetch();

if (!$scoreData) {
    require_once '../../includes/header.php';
    require_once '../../includes/navbar.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Score record not found.</div></div>';
    require_once '../../includes/footer.php';
    exit;
}

$applications = $pdo->query(
    "SELECT a.id, u.full_name AS student_name, s.name AS program_name
      FROM applications a
      JOIN users u ON a.student_id = u.id
      JOIN scholarship_programs s ON a.program_id = s.id
      ORDER BY a.id DESC"
)->fetchAll();
$criteria = $pdo->query('SELECT id, criterion_name AS name, max_score FROM scoring_criteria ORDER BY criterion_name')->fetchAll();
// Fix role mismatch: use 'reviewer' to match users/create.php
$reviewers = $pdo->query("SELECT id, full_name FROM users WHERE role = 'reviewer' ORDER BY full_name")->fetchAll();

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
            $error = 'Score exceeds maximum value (' . $criteriaData['max_score'] . ') for this criteria.';
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

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="container py-4">
    <!-- PAGE HEADER -->
    <div class="mb-4">
        <h1 class="page-title">Edit Evaluation Score</h1>
        <p class="page-subtitle">Modify criteria evaluation score details</p>
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
                            <?php foreach ($applications as $application): ?>
                                <option value="<?= $application['id'] ?>" <?= $application['id'] == $scoreData['application_id'] ? 'selected' : '' ?>>
                                    #<?= $application['id'] ?> - <?= htmlspecialchars($application['student_name']) ?> (<?= htmlspecialchars($application['program_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Criteria <span class="text-danger">*</span></label>
                        <select name="criteria_id" class="form-select" required>
                            <?php foreach ($criteria as $item): ?>
                                <option value="<?= $item['id'] ?>" <?= $item['id'] == $scoreData['criteria_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($item['name']) ?> (Max <?= $item['max_score'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reviewer (Council Member) <span class="text-danger">*</span></label>
                        <select name="council_id" class="form-select" required>
                            <?php foreach ($reviewers as $reviewer): ?>
                                <option value="<?= $reviewer['id'] ?>" <?= $reviewer['id'] == $scoreData['council_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($reviewer['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Assigned Score <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="score" value="<?= htmlspecialchars($scoreData['score']) ?>" class="form-control" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Notes / Remarks</label>
                        <textarea name="note" class="form-control" rows="3"><?= htmlspecialchars($scoreData['note']) ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="update" class="btn btn-primary">Update Score</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
