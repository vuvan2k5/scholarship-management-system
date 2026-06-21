<?php
// ============================================================
// admin/notifications/create.php
// ============================================================

$pageTitle = 'Add Notification';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$error = '';
$userId = '';
$title = '';
$message = '';
$type = 'info';
$isRead = 0;

$users = $pdo->query('SELECT id, full_name, role FROM users ORDER BY full_name')->fetchAll();

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
            'INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $insert->execute([$userId, $title, $message, $type, $isRead]);
        header('Location: index.php');
        exit;
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="container py-4">
    <!-- PAGE HEADER -->
    <div class="mb-4">
        <h1 class="page-title">Add Notification</h1>
        <p class="page-subtitle">Send a system alert or dynamic update to a user</p>
    </div>

    <!-- ALERTS -->
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- FORM -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="form-card">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Target User <span class="text-danger">*</span></label>
                        <select name="user_id" class="form-select" required>
                            <option value="">Select user</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= $user['id'] == $userId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['full_name']) ?> (<?= ucfirst($user['role']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notification Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" value="<?= htmlspecialchars($title) ?>" class="form-control" placeholder="e.g. Application Review Complete" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notification Message <span class="text-danger">*</span></label>
                        <textarea name="message" class="form-control" rows="4" placeholder="Enter detailed text message to be shown to the user..." required><?= htmlspecialchars($message) ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Notification Level/Type</label>
                        <select name="type" class="form-select">
                            <option value="info" <?= $type === 'info' ? 'selected' : '' ?>>Info (General)</option>
                            <option value="success" <?= $type === 'success' ? 'selected' : '' ?>>Success (Approved/Passed)</option>
                            <option value="warning" <?= $type === 'warning' ? 'selected' : '' ?>>Warning (Pending Action)</option>
                            <option value="error" <?= $type === 'error' ? 'selected' : '' ?>>Error (Rejected/Failed)</option>
                        </select>
                    </div>

                    <div class="mb-4 form-check">
                        <input type="checkbox" name="is_read" id="is_read" class="form-check-input" value="1" <?= $isRead ? 'checked' : '' ?> >
                        <label class="form-check-label" for="is_read">Mark as read immediately</label>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="submit" class="btn btn-primary">Save Notification</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
