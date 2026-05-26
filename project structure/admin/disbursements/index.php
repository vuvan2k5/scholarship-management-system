<?php
// ============================================================
// admin/disbursements/index.php
// ============================================================

$pageTitle = 'Disbursements';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

$pdo = getDB();

$sql = "
    SELECT d.*, a.id AS application_number, u.full_name AS student_name, sp.name AS program_name
    FROM disbursements d
    INNER JOIN applications a ON d.application_id = a.id
    INNER JOIN users u ON a.student_id = u.id
    INNER JOIN scholarship_programs sp ON a.program_id = sp.id
    ORDER BY d.id DESC
";
$disbursements = $pdo->query($sql)->fetchAll();
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Disbursements</h1>
    <p class="page-subtitle">Track and authorize payouts to qualified scholarship recipients.</p>
  </div>
  <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Disbursement</a>
</div>

<div class="table-card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th><th>Application</th><th>Student</th><th>Program</th>
          <th>Amount</th><th>Status</th><th>Disbursed At</th><th>Note</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($disbursements as $row): ?>
          <tr>
            <td><span class="text-muted">#<?= e($row['id']) ?></span></td>
            <td><a href="../applications/index.php?search=<?= e($row['application_id']) ?>" class="fw-semibold text-primary">#<?= e($row['application_id']) ?></a></td>
            <td><strong><?= e($row['student_name']) ?></strong></td>
            <td><?= e($row['program_name']) ?></td>
            <td class="text-success fw-semibold"><?= number_format($row['amount']) ?> VND</td>
            <td><span class="badge badge-status-<?= e($row['status']) ?>"><?= ucfirst(e($row['status'])) ?></span></td>
            <td class="text-muted"><?= e($row['disbursed_at'] ?: '—') ?></td>
            <td class="text-muted" style="font-size:13px;"><?= e($row['note'] ?: '—') ?></td>
            <td>
              <div class="d-flex gap-2">
                <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning btn-action"><i class="bi bi-pencil"></i> Edit</a>
                <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger btn-action" onclick="return confirm('Delete this disbursement?')"><i class="bi bi-trash"></i> Delete</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
