<?php
// ============================================================
// admin/reports/index.php
// ============================================================

$pageTitle = 'Reports';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

$pdo = getDB();
$reports = $pdo->query("SELECT * FROM reports ORDER BY id DESC")->fetchAll();
?>

<div class="container py-4">
    <!-- PAGE TITLE -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">Reports Management</h1>
            <p class="page-subtitle">View and generate administrative summaries, logs, and system audits</p>
        </div>
        <a class="btn btn-primary" href="create.php">
            <i class="bi bi-plus-lg me-2"></i> Add Report
        </a>
    </div>

    <!-- TABLE -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th width="80">ID</th>
                        <th>Title</th>
                        <th>Summary / Description</th>
                        <th>Created At</th>
                        <th width="180">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $row): ?>
                        <tr>
                            <td>#<?= e($row['id']) ?></td>
                            <td><strong><?= e($row['title']) ?></strong></td>
                            <td><span class="text-muted"><?= e($row['content']) ?></span></td>
                            <td><?= e($row['created_at']) ?></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a class="btn btn-warning btn-sm btn-action" href="edit.php?id=<?= $row['id'] ?>">Edit</a>
                                    <a class="btn btn-danger btn-sm btn-action" href="delete.php?id=<?= $row['id'] ?>" onclick="return confirm('Delete this report?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
