<?php

include '../../config/db.php';
include '../../includes/header.php';

$pageTitle = 'Add Notification';
$pdo = getDB();
$error = '';
$userId = '';
$title = '';
$message = '';
$type = 'info';
$isRead = 0;

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
        $insert = $pdo->prepare(
            'INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, ?, ?)'
        );
        $insert->execute([$userId, $title, $message, $type, $isRead]);
        header('Location: index.php');
        exit;
    }
}
?>

<h2 class="mb-4">Add Notification</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label class="form-label">User</label>
        <select name="user_id" class="form-control" required>
            <option value="">Select user</option>
            <?php foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>" <?= $user['id'] == $userId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($user['full_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Title</label>
        <input type="text" name="title" value="<?= htmlspecialchars($title) ?>" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Message</label>
        <textarea name="message" class="form-control" required><?= htmlspecialchars($message) ?></textarea>
    </div>

    <div class="mb-3">
        <label class="form-label">Type</label>
        <select name="type" class="form-control">
            <option value="info" <?= $type === 'info' ? 'selected' : '' ?>>Info</option>
            <option value="success" <?= $type === 'success' ? 'selected' : '' ?>>Success</option>
            <option value="warning" <?= $type === 'warning' ? 'selected' : '' ?>>Warning</option>
            <option value="error" <?= $type === 'error' ? 'selected' : '' ?>>Error</option>
        </select>
    </div>

    <div class="mb-3 form-check">
        <input type="checkbox" name="is_read" id="is_read" class="form-check-input" value="1" <?= $isRead ? 'checked' : '' ?> >
        <label class="form-check-label" for="is_read">Mark as read</label>
    </div>

    <button type="submit" name="submit" class="btn btn-primary">Save Notification</button>
</form>

<?php include '../../includes/footer.php'; ?>
