<?php
$pageTitle = 'Scholarship Programs';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

$pdo      = getDB();
$programs = $pdo->query("SELECT * FROM scholarship_programs ORDER BY id DESC")->fetchAll();
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Scholarship Programs</h1>
    <p class="page-subtitle">Manage scholarship program specifications and availability.</p>
  </div>
  <a href="create.php" class="btn btn-primary">
    <i class="bi bi-plus-lg"></i> Add Program
  </a>
</div>

<div class="table-card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Budget</th>
          <th>Slots</th>
          <th>Start Date</th>
          <th>End Date</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($programs as $p): ?>
          <tr>
            <td><span class="text-muted">#<?= e($p['id']) ?></span></td>
            <td><strong><?= e($p['name']) ?></strong></td>
            <td class="text-success fw-semibold"><?= number_format($p['budget']) ?> VND</td>
            <td><?= e($p['slots']) ?></td>
            <td class="text-muted"><?= e($p['start_date']) ?></td>
            <td class="text-muted"><?= e($p['end_date']) ?></td>
            <td>
              <span class="badge badge-status-<?= e($p['status']) ?>">
                <?= ucfirst(e($p['status'])) ?>
              </span>
            </td>
            <td>
              <div class="d-flex gap-2">
                <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-warning btn-action">
                  <i class="bi bi-pencil"></i> Edit
                </a>
                <a href="delete.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-danger btn-action"
                   onclick="return confirm('Delete this program?')">
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
