<?php
// ============================================================
// admin/ranking_results/index.php
// ============================================================

$pageTitle = 'Ranking Results';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

$pdo = getDB();

$sql = "
    SELECT rr.*, a.id AS application_number, u.full_name AS student_name, sp.name AS program_name
    FROM ranking_results rr
    INNER JOIN applications a ON rr.application_id = a.id
    INNER JOIN users u ON a.student_id = u.id
    INNER JOIN scholarship_programs sp ON a.program_id = sp.id
    ORDER BY rr.rank ASC, rr.total_score DESC
";
$rankings = $pdo->query($sql)->fetchAll();
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Ranking Results</h1>
    <p class="page-subtitle">Ranked applicants based on weighted evaluation scores.</p>
  </div>
  <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Ranking</a>
</div>

<div class="table-card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Rank</th><th>Application</th><th>Student</th><th>Program</th><th>Total Score</th><th>Recommended</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rankings as $row): ?>
          <tr>
            <td>
              <?php $rank = $row['rank']; ?>
              <span class="badge <?= $rank == 1 ? 'badge-warning' : 'badge-info' ?>" style="font-size:13px;">#<?= e($rank) ?></span>
            </td>
            <td><a href="../applications/index.php?search=<?= e($row['application_id']) ?>" class="fw-semibold text-primary">#<?= e($row['application_id']) ?></a></td>
            <td><strong><?= e($row['student_name']) ?></strong></td>
            <td><?= e($row['program_name']) ?></td>
            <td><strong><?= e($row['total_score']) ?> / 100</strong></td>
            <td>
              <?php if ($row['recommended']): ?>
                <span class="badge badge-eligible"><i class="bi bi-hand-thumbs-up me-1"></i>Recommended</span>
              <?php else: ?>
                <span class="badge badge-pending">Under Review</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex gap-2">
                <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning btn-action"><i class="bi bi-pencil"></i> Edit</a>
                <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger btn-action" onclick="return confirm('Delete this ranking record?')"><i class="bi bi-trash"></i> Delete</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
