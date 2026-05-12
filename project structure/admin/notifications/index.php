<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/header.php';

$pageTitle = 'Notifications Management';
$pdo = getDB();
$sql = "SELECT n.*, u.full_name AS user_name FROM notifications n JOIN users u ON n.user_id = u.id ORDER BY n.id DESC";
$stmt = $pdo->query($sql);
$notifications = $stmt->fetchAll();
?>

<h2 class="mb-4">Notifications Management</h2>

<a href="create.php" class="btn btn-primary mb-3">Add Notification</a>

<table class="table table-bordered table-hover">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>User</th>
            <th>Title</th>
            <th>Message</th>
            <th>Type</th>
            <th>Read</th>
            <th>Created At</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($notifications as $notification): ?>
            <tr>
                <td><?= $notification['id'] ?></td>
                <td><?= htmlspecialchars($notification['user_name']) ?></td>
                <td><?= htmlspecialchars($notification['title']) ?></td>
                <td><?= htmlspecialchars($notification['message']) ?></td>
                <td><?= htmlspecialchars($notification['type']) ?></td>
                <td><?= $notification['is_read'] ? 'Yes' : 'No' ?></td>
                <td><?= $notification['created_at'] ?></td>
                <td>
                    <a href="edit.php?id=<?= $notification['id'] ?>" class="btn btn-sm btn-success">Edit</a>
                    <a href="delete.php?id=<?= $notification['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this notification?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

