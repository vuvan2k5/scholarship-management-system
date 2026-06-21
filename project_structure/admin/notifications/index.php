<?php
// ============================================================
// admin/notifications/index.php
// ============================================================

$pageTitle = 'Notifications Management';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

$pdo = getDB();
$sql = "SELECT n.*, u.full_name AS user_name FROM notifications n JOIN users u ON n.user_id = u.id ORDER BY n.id DESC";
$stmt = $pdo->query($sql);
$notifications = $stmt->fetchAll();
?>

<div class="container py-4">
    <!-- PAGE TITLE -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">Notifications Management</h1>
            <p class="page-subtitle">Send and review system alerts dispatched to candidates or reviewers</p>
        </div>
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i> Add Notification
        </a>
    </div>

    <!-- TABLE -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Target User</th>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Type</th>
                        <th>Read</th>
                        <th>Sent At</th>
                        <th width="180">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $notification): ?>
                        <tr>
                            <td>#<?= e($notification['id']) ?></td>
                            <td><strong><?= e($notification['user_name']) ?></strong></td>
                            <td><?= e($notification['title']) ?></td>
                            <td><span class="small text-muted"><?= e($notification['message']) ?></span></td>
                            <td><span class="badge bg-secondary text-uppercase"><?= e($notification['type']) ?></span></td>
                            <td>
                                <?php if($notification['is_read']): ?>
                                    <span class="badge bg-light text-muted">Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">No</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($notification['created_at']) ?></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="edit.php?id=<?= $notification['id'] ?>" class="btn btn-warning btn-sm btn-action">Edit</a>
                                    <a href="delete.php?id=<?= $notification['id'] ?>" class="btn btn-danger btn-sm btn-action" onclick="return confirm('Delete this notification?')">Delete</a>
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
