<?php
$pageTitle = 'Student Dashboard';

require_once '../config/db.php';
require_once '../includes/auth.php';

requireLogin();
requireRole('student');

require_once '../includes/header.php';
require_once '../includes/navbar.php';

$pdo       = getDB();
$studentId = currentUserId();

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ?");
$stmtTotal->execute([$studentId]);
$totalApplications = $stmtTotal->fetchColumn();

$stmtApproved = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ? AND status = 'approved'");
$stmtApproved->execute([$studentId]);
$approvedApplications = $stmtApproved->fetchColumn();

$stmtNotif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmtNotif->execute([$studentId]);
$unreadNotifications = $stmtNotif->fetchColumn();

$stmtRecent = $pdo->prepare("
    SELECT a.*, sp.name AS program_name
    FROM applications a
    JOIN scholarship_programs sp ON a.program_id = sp.id
    WHERE a.student_id = ?
    ORDER BY a.id DESC LIMIT 6
");
$stmtRecent->execute([$studentId]);
$recentApplications = $stmtRecent->fetchAll();
?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Welcome, <?= e(currentUserName()) ?> 👋</h1>
    <p class="page-subtitle">Track your scholarship applications and results.</p>
  </div>
  <a href="apply.php" class="btn btn-primary">
    <i class="bi bi-file-earmark-plus"></i> Apply for Scholarship
  </a>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-sm-4">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-folder2-open"></i></div>
      <div class="stat-body">
        <div class="stat-label">My Applications</div>
        <div class="stat-value"><?= e($totalApplications) ?></div>
        <div class="stat-trend">Total submitted</div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-patch-check"></i></div>
      <div class="stat-body">
        <div class="stat-label">Approved</div>
        <div class="stat-value"><?= e($approvedApplications) ?></div>
        <div class="stat-trend">Scholarship awarded</div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card">
      <div class="stat-icon yellow"><i class="bi bi-bell"></i></div>
      <div class="stat-body">
        <div class="stat-label">Notifications</div>
        <div class="stat-value"><?= e($unreadNotifications) ?></div>
        <div class="stat-trend">Unread messages</div>
      </div>
    </div>
  </div>
</div>

<!-- Recent Applications -->
<?php if (empty($recentApplications)): ?>
  <div class="card">
    <div class="card-body">
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bi bi-folder-x"></i></div>
        <div class="empty-state-title">No Applications Yet</div>
        <div class="empty-state-text">You haven't submitted any scholarship applications.</div>
        <a href="apply.php" class="btn btn-primary">
          <i class="bi bi-file-earmark-plus"></i> Apply Now
        </a>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="table-card">
    <div class="table-card-header">
      <span class="table-card-title">My Recent Applications</span>
      <a href="my_applications.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Scholarship Program</th>
            <th>Status</th>
            <th>Eligible</th>
            <th>Submitted At</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentApplications as $app): ?>
            <tr>
              <td><span class="text-muted">#<?= e($app['id']) ?></span></td>
              <td><strong><?= e($app['program_name']) ?></strong></td>
              <td>
                <span class="badge badge-status-<?= e($app['status']) ?>">
                  <?= ucfirst(e($app['status'])) ?>
                </span>
              </td>
              <td>
                <?php if ($app['eligible'] === null): ?>
                  <span class="badge badge-pending">Pending</span>
                <?php elseif ($app['eligible']): ?>
                  <span class="badge badge-eligible">Yes</span>
                <?php else: ?>
                  <span class="badge badge-ineligible">No</span>
                <?php endif; ?>
              </td>
              <td class="text-muted"><?= e($app['submitted_at']) ?></td>
              <td>
                <a href="application_details.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-eye"></i> View
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
