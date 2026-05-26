<?php
$pageTitle = 'Users';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

$pdo   = getDB();
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Users</h1>
    <p class="page-subtitle">Manage system user accounts and role assignments.</p>
  </div>
  <a href="create.php" class="btn btn-primary">
    <i class="bi bi-plus-lg"></i> Add User
  </a>
</div>

<div class="table-card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Full Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Student Code</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $user): ?>
          <tr>
            <td><span class="text-muted">#<?= e($user['id']) ?></span></td>
            <td><strong><?= e($user['full_name']) ?></strong></td>
            <td class="text-muted"><?= e($user['email']) ?></td>
            <td>
              <?php
              $r = $user['role'];
              if ($r === 'admin'):
              ?>
                <span class="badge badge-admin">Admin</span>
              <?php elseif ($r === 'reviewer' || $r === 'council'): ?>
                <span class="badge badge-council">Reviewer</span>
              <?php else: ?>
                <span class="badge badge-student">Student</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($user['student_code']): ?>
                <code style="font-size:12px;background:#f1f5f9;padding:2px 6px;border-radius:4px;">
                  <?= e($user['student_code']) ?>
                </code>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex gap-2">
                <a href="edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-warning btn-action">
                  <i class="bi bi-pencil"></i> Edit
                </a>
                <a href="delete.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-danger btn-action"
                   onclick="return confirm('Delete this user?')">
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
