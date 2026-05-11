<?php

include '../../config/db.php';
include '../../includes/header.php';

$pageTitle = 'Edit Notification';
$pdo = getDB();
$error = '';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = $pdo->prepare('SELECT * FROM notifications WHERE id = ?');
$stmt->execute([$id]);
$notification = $stmt->fetch();

if (!$notification) {
    echo '<div class="alert alert-danger">Notification not found.</div>';
    include '../../includes/footer.php';
    exit;
}

$users = $pdo->query('SELECT id, full_name FROM users ORDER BY full_name')->fetchAll();

if (isset($_POST['submit'])) {
    $userId = trim($_POST['user_id']);
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $type = trim($_POST['type']);
    $isRead = isset($_POST['is_read']) ? 1 : 0;

    if ($userId === '' || $title === '' || $message === '') {
        $error = 'User, title and message are required.';
    } else {
        $update = $pdo->prepare(
            'UPDATE notifications SET user_id = ?, title = ?, message = ?, type = ?, is_read = ? WHERE id = ?'
        );
        $update->execute([$userId, $title, $message, $type, $isRead, $id]);
        header('Location: index.php');
        exit;
    }
}
?>

<h2 class="mb-4">Edit Notification</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label class="form-label">User</label>
        <select name="user_id" class="form-control" required>
            <option value="">Select user</option>
            <?php foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>" <?= $user['id'] == $notification['user_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($user['full_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Title</label>
        <input type="text" name="title" value="<?= htmlspecialchars($notification['title']) ?>" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Message</label>
        <textarea name="message" class="form-control" required><?= htmlspecialchars($notification['message']) ?></textarea>
    </div>

    <div class="mb-3">
        <label class="form-label">Type</label>
        <select name="type" class="form-control">
            <option value="info" <?= $notification['type'] === 'info' ? 'selected' : '' ?>>Info</option>
            <option value="success" <?= $notification['type'] === 'success' ? 'selected' : '' ?>>Success</option>
            <option value="warning" <?= $notification['type'] === 'warning' ? 'selected' : '' ?>>Warning</option>
            <option value="error" <?= $notification['type'] === 'error' ? 'selected' : '' ?>>Error</option>
        </select>
    </div>

    <div class="mb-3 form-check">
        <input type="checkbox" name="is_read" id="is_read" class="form-check-input" value="1" <?= $notification['is_read'] ? 'checked' : '' ?> >
        <label class="form-check-label" for="is_read">Mark as read</label>
    </div>

    <button type="submit" name="submit" class="btn btn-success">Update Notification</button>
</form>

<?php include '../../includes/footer.php'; ?>
