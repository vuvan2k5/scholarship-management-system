<?php
// ============================================================
// admin/award_certificates/index.php
// ============================================================

$pageTitle = 'Award Certificates';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

$pdo = getDB();

$sql = "
    SELECT ac.*, a.id AS application_number, u.full_name AS student_name, sp.name AS program_name
    FROM award_certificates ac
    INNER JOIN applications a ON ac.application_id = a.id
    INNER JOIN users u ON a.student_id = u.id
    INNER JOIN scholarship_programs sp ON a.program_id = sp.id
    ORDER BY ac.id DESC
";
$certificates = $pdo->query($sql)->fetchAll();
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Award Certificates</h1>
    <p class="page-subtitle">Manage official scholarship certificates issued to approved candidates.</p>
  </div>
  <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Issue Certificate</a>
</div>

<div class="table-card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th><th>Application</th><th>Student</th><th>Program</th><th>Certificate Code</th><th>Issued At</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($certificates as $row): ?>
          <tr>
            <td><span class="text-muted">#<?= e($row['id']) ?></span></td>
            <td><a href="../applications/index.php?search=<?= e($row['application_id']) ?>" class="fw-semibold text-primary">#<?= e($row['application_id']) ?></a></td>
            <td><strong><?= e($row['student_name']) ?></strong></td>
            <td><?= e($row['program_name']) ?></td>
            <td><code style="font-size:12px;background:#f1f5f9;padding:2px 8px;border-radius:4px;"><?= e($row['certificate_code']) ?></code></td>
            <td class="text-muted"><?= e($row['issued_at']) ?></td>
            <td>
              <div class="d-flex gap-2">
                <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning btn-action"><i class="bi bi-pencil"></i> Edit</a>
                <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger btn-action" onclick="return confirm('Delete this certificate?')"><i class="bi bi-trash"></i> Delete</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
