<?php
$pageTitle = 'Applications';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// Handle Check Eligibility requests
if (isset($_GET['check_id'])) {
    $checkId = (int)$_GET['check_id'];
    require_once '../../includes/eligibility.php';
    checkEligibility($pdo, $checkId);
    setFlash('success', "Automatic eligibility check completed for Application #$checkId.");
    header("Location: index.php");
    exit;
}

if (isset($_GET['check_all'])) {
    require_once '../../includes/eligibility.php';
    $stmtPending = $pdo->query("SELECT id FROM applications WHERE eligible IS NULL");
    $count = 0;
    while ($row = $stmtPending->fetch()) {
        checkEligibility($pdo, (int)$row['id']);
        $count++;
    }
    setFlash('success', "Batch eligibility check processed $count application(s).");
    header("Location: index.php");
    exit;
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

$applications = $pdo->query("
    SELECT a.*, u.full_name, sp.name AS program_name
    FROM applications a
    JOIN users u ON a.student_id = u.id
    JOIN scholarship_programs sp ON a.program_id = sp.id
    ORDER BY a.id DESC
")->fetchAll();
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Applications</h1>
    <p class="page-subtitle">Manage all scholarship applications in the system.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="index.php?check_all=1" class="btn btn-outline-primary">
      <i class="bi bi-play-circle me-1"></i> Run Eligibility Checks
    </a>
    <a href="create.php" class="btn btn-primary">
      <i class="bi bi-plus-lg"></i> Add Application
    </a>
  </div>
</div>

<div class="table-card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Student</th>
          <th>Program</th>
          <th>Status</th>
          <th>Eligible</th>
          <th>Submitted</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($applications as $app): ?>
          <tr>
            <td><span class="text-muted">#<?= e($app['id']) ?></span></td>
            <td><strong><?= e($app['full_name']) ?></strong></td>
            <td><?= e($app['program_name']) ?></td>
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
              <div class="d-flex gap-2">
                <a href="index.php?check_id=<?= $app['id'] ?>" class="btn btn-sm btn-info btn-action" title="Check Eligibility">
                  <i class="bi bi-shield-check"></i> Check
                </a>
                <a href="edit.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-warning btn-action">
                  <i class="bi bi-pencil"></i> Edit
                </a>
                <a href="delete.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-danger btn-action"
                   onclick="return confirm('Delete this application?')">
                  <i class="bi bi-trash"></i> Delete
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
