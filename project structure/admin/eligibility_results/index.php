<?php
// ============================================================
// admin/eligibility_results/index.php
// ============================================================

$pageTitle = 'Eligibility Results';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

$pdo = getDB();
$sql = "
    SELECT er.*, a.id AS application_number, u.full_name AS student_name, sp.name AS program_name
    FROM eligibility_results er
    INNER JOIN applications a ON er.application_id = a.id
    INNER JOIN users u ON a.student_id = u.id
    INNER JOIN scholarship_programs sp ON a.program_id = sp.id
    ORDER BY er.id DESC
";
$results = $pdo->query($sql)->fetchAll();
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Eligibility Results</h1>
    <p class="page-subtitle">Historical logs of filter checking outcomes for candidates.</p>
  </div>
</div>

<div class="table-card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Application</th>
          <th>Student</th>
          <th>Program</th>
          <th>Status</th>
          <th>Reason</th>
          <th>Checked At</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($results as $result): ?>
          <tr>
            <td><span class="text-muted">#<?= e($result['id']) ?></span></td>
            <td>
              <a href="../applications/index.php?search=<?= e($result['application_number']) ?>" class="fw-semibold text-primary">
                #<?= e($result['application_number']) ?>
              </a>
            </td>
            <td><?= e($result['student_name']) ?></td>
            <td><?= e($result['program_name']) ?></td>
            <td>
              <?php if ($result['is_passed'] == 1): ?>
                <span class="badge badge-eligible"><i class="bi bi-patch-check me-1"></i>Pass</span>
              <?php else: ?>
                <span class="badge badge-ineligible"><i class="bi bi-patch-minus me-1"></i>Fail</span>
              <?php endif; ?>
            </td>
            <td class="text-muted" style="font-size:13px;"><?= e($result['reason'] ?: 'Meets all criteria') ?></td>
            <td class="text-muted"><?= e($result['checked_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>