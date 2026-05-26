<?php
// ============================================================
// admin/evaluation_scores/index.php
// ============================================================

$pageTitle = 'Evaluation Scores';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

$pdo = getDB();
$sql = "SELECT es.*, c.criterion_name AS criteria_name, u.full_name AS reviewer_name,
               a.student_id, su.full_name AS student_name, sp.name AS program_name
        FROM evaluation_scores es
        JOIN scoring_criteria c ON es.criteria_id = c.id
        JOIN users u ON es.council_id = u.id
        JOIN applications a ON es.application_id = a.id
        JOIN users su ON a.student_id = su.id
        JOIN scholarship_programs sp ON a.program_id = sp.id
        ORDER BY es.id DESC";
$stmt = $pdo->query($sql);
$scores = $stmt->fetchAll();
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Evaluation Scores</h1>
    <p class="page-subtitle">Candidate criterion grades assigned by council reviewers.</p>
  </div>
  <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Score</a>
</div>

<div class="table-card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th><th>App</th><th>Student</th><th>Program</th>
          <th>Criteria</th><th>Reviewer</th><th>Score</th><th>Note</th><th>Scored At</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($scores as $score): ?>
          <tr>
            <td><span class="text-muted">#<?= e($score['id']) ?></span></td>
            <td><a href="../applications/index.php?search=<?= e($score['application_id']) ?>" class="fw-semibold text-primary">#<?= e($score['application_id']) ?></a></td>
            <td><?= e($score['student_name']) ?></td>
            <td><?= e($score['program_name']) ?></td>
            <td><span class="badge badge-info"><?= e($score['criteria_name']) ?></span></td>
            <td><?= e($score['reviewer_name']) ?></td>
            <td><strong class="text-primary"><?= number_format($score['score'], 2) ?></strong></td>
            <td class="text-muted" style="font-size:13px;"><?= e($score['note'] ?: '—') ?></td>
            <td class="text-muted"><?= e($score['scored_at']) ?></td>
            <td>
              <div class="d-flex gap-2">
                <a href="edit.php?id=<?= $score['id'] ?>" class="btn btn-sm btn-warning btn-action"><i class="bi bi-pencil"></i> Edit</a>
                <a href="delete.php?id=<?= $score['id'] ?>" class="btn btn-sm btn-danger btn-action" onclick="return confirm('Delete this score?')"><i class="bi bi-trash"></i> Delete</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
