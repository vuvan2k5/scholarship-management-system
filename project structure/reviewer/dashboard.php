<?php
$pageTitle = 'Reviewer Dashboard';

require_once '../config/db.php';
require_once '../includes/auth.php';

requireLogin();
requireRole('council', 'reviewer');

require_once '../includes/header.php';
require_once '../includes/navbar.php';

$pdo = getDB();

$totalApplications  = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$pendingApplications = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'submitted'")->fetchColumn();
$totalScores = $pdo->prepare("SELECT COUNT(*) FROM evaluation_scores WHERE council_id = ?");
$totalScores->execute([currentUserId()]);
$totalScores = $totalScores->fetchColumn();

$recentApplications = $pdo->query("
    SELECT a.*, u.full_name, sp.name AS program_name
    FROM applications a
    JOIN users u ON a.student_id = u.id
    JOIN scholarship_programs sp ON a.program_id = sp.id
    ORDER BY a.id DESC LIMIT 8
");
?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Reviewer Dashboard</h1>
    <p class="page-subtitle">Review and evaluate scholarship applications.</p>
  </div>
  <a href="applications.php" class="btn btn-primary">
    <i class="bi bi-folder2-open"></i> Review Applications
  </a>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-sm-4">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-folder2-open"></i></div>
      <div class="stat-body">
        <div class="stat-label">Total Applications</div>
        <div class="stat-value"><?= e($totalApplications) ?></div>
        <div class="stat-trend">In the system</div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card">
      <div class="stat-icon yellow"><i class="bi bi-hourglass-split"></i></div>
      <div class="stat-body">
        <div class="stat-label">Pending Reviews</div>
        <div class="stat-value"><?= e($pendingApplications) ?></div>
        <div class="stat-trend">Awaiting evaluation</div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-star-half"></i></div>
      <div class="stat-body">
        <div class="stat-label">My Evaluations</div>
        <div class="stat-value"><?= e($totalScores) ?></div>
        <div class="stat-trend">Scores submitted by you</div>
      </div>
    </div>
  </div>
</div>

<!-- Recent Applications Table -->
<div class="table-card">
  <div class="table-card-header">
    <span class="table-card-title">Recent Applications</span>
    <a href="applications.php" class="btn btn-sm btn-outline-primary">View All</a>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Student</th>
          <th>Program</th>
          <th>Status</th>
          <th>Submitted</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentApplications as $app): ?>
          <tr>
            <td><span class="text-muted">#<?= e($app['id']) ?></span></td>
            <td><strong><?= e($app['full_name']) ?></strong></td>
            <td><?= e($app['program_name']) ?></td>
            <td>
              <span class="badge badge-status-<?= e($app['status']) ?>">
                <?= ucfirst(e($app['status'])) ?>
              </span>
            </td>
            <td class="text-muted"><?= e($app['submitted_at']) ?></td>
            <td>
              <a href="review.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-pencil-square"></i> Review
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
