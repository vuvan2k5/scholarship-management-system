<?php
$pageTitle = 'Admin Dashboard';

require_once '../config/db.php';
require_once '../includes/auth.php';

requireLogin();
requireRole('admin');

require_once '../includes/header.php';
require_once '../includes/navbar.php';

$pdo = getDB();

$totalApplications  = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$pendingApps        = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'submitted'")->fetchColumn();

// New Dashboard Stats requested by User
$totalEligible = $pdo->query("SELECT COUNT(*) FROM applications WHERE eligible = 1")->fetchColumn();
$totalRejected = $pdo->query("SELECT COUNT(*) FROM applications WHERE eligible = 0")->fetchColumn();
$totalAwarded  = $pdo->query("SELECT COUNT(*) FROM ranking_results WHERE recommended = 1")->fetchColumn();
$totalBudget   = $pdo->query("SELECT SUM(budget) FROM scholarship_programs")->fetchColumn();

$recentApps = $pdo->query("
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
    <h1 class="page-title">Welcome back, <?= e(currentUserName()) ?> 👋</h1>
    <p class="page-subtitle">Here's what's happening in your scholarship system today.</p>
  </div>
  <div class="quick-actions">
    <a href="applications/index.php" class="btn btn-primary">
      <i class="bi bi-folder2-open"></i> View Applications
    </a>
  </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-folder2-open"></i></div>
      <div class="stat-body">
        <div class="stat-label">Applications</div>
        <div class="stat-value"><?= e($totalApplications) ?></div>
        <div class="stat-trend"><?= e($pendingApps) ?> pending</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
      <div class="stat-body">
        <div class="stat-label">Eligible</div>
        <div class="stat-value"><?= e($totalEligible) ?></div>
        <div class="stat-trend">Passed filter</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon red"><i class="bi bi-x-circle"></i></div>
      <div class="stat-body">
        <div class="stat-label">Rejected</div>
        <div class="stat-value"><?= e($totalRejected) ?></div>
        <div class="stat-trend">Failed filter</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon yellow"><i class="bi bi-trophy"></i></div>
      <div class="stat-body">
        <div class="stat-label">Awarded</div>
        <div class="stat-value"><?= e($totalAwarded) ?></div>
        <div class="stat-trend">Recommended</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon text-success" style="background: rgba(25, 135, 84, 0.15);"><i class="bi bi-cash-stack"></i></div>
      <div class="stat-body">
        <div class="stat-label">Total Budget</div>
        <div class="stat-value" style="font-size: 18px;"><?= number_format($totalBudget, 0, ',', '.') ?>đ</div>
        <div class="stat-trend">Across programs</div>
      </div>
    </div>
  </div>
</div>

<!-- Bottom Row -->
<div class="row g-3">

  <!-- Recent Applications -->
  <div class="col-lg-8">
    <div class="table-card">
      <div class="table-card-header">
        <span class="table-card-title">Recent Applications</span>
        <a href="applications/index.php" class="btn btn-sm btn-outline-primary">View All</a>
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
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentApps as $app): ?>
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
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Right Column -->
  <div class="col-lg-4 d-flex flex-column gap-3">

    <!-- System Status -->
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-3">System Status</div>
        <div class="d-flex align-items-center justify-content-between mb-3">
          <span class="text-muted" style="font-size:13.5px;">Database</span>
          <span class="badge badge-active"><span class="status-dot online"></span>Online</span>
        </div>
        <div class="d-flex align-items-center justify-content-between mb-3">
          <span class="text-muted" style="font-size:13.5px;">Server</span>
          <span class="badge badge-active"><span class="status-dot online"></span>Running</span>
        </div>
        <div class="d-flex align-items-center justify-content-between">
          <span class="text-muted" style="font-size:13.5px;">Authentication</span>
          <span class="badge badge-active"><span class="status-dot online"></span>Active</span>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-3">Quick Actions</div>
        <div class="quick-action-grid">
          <a href="users/index.php" class="quick-action-item">
            <i class="bi bi-people"></i> Users
          </a>
          <a href="applications/index.php" class="quick-action-item">
            <i class="bi bi-folder2-open"></i> Applications
          </a>
          <a href="evaluation_scores/index.php" class="quick-action-item">
            <i class="bi bi-star-half"></i> Scores
          </a>
          <a href="notifications/index.php" class="quick-action-item">
            <i class="bi bi-bell"></i> Notifications
          </a>
          <a href="disbursements/index.php" class="quick-action-item">
            <i class="bi bi-cash-coin"></i> Disbursements
          </a>
          <a href="reports/index.php" class="quick-action-item">
            <i class="bi bi-graph-up"></i> Reports
          </a>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
