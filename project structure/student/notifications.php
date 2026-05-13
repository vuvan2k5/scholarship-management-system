<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();
requireRole('student');

$pdo = getDB();
$userId = currentUserId();
$error = '';

if (isset($_GET['mark_all'])) {
    $update = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
    $update->execute([$userId]);
    header('Location: notifications.php');
    exit;
}

$notifications = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
$notifications->execute([$userId]);
$notifications = $notifications->fetchAll();
?>

<h2 class="mb-4">Thông báo của tôi</h2>
<p>
    <a href="?mark_all=1" class="btn btn-sm btn-secondary">Đánh dấu đã đọc tất cả</a>
</p>

<?php if (empty($notifications)): ?>
    <div class="alert alert-info">Bạn chưa có thông báo nào.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Tiêu đề</th>
                    <th>Nội dung</th>
                    <th>Loại</th>
                    <th>Đã đọc</th>
                    <th>Ngày</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notifications as $note): ?>
                    <tr>
                        <td><?= e($note['id']) ?></td>
                        <td><?= e($note['title']) ?></td>
                        <td><?= e($note['message']) ?></td>
                        <td><?= e(ucfirst($note['type'])) ?></td>
                        <td><?= $note['is_read'] ? 'Có' : 'Chưa' ?></td>
                        <td><?= e($note['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
