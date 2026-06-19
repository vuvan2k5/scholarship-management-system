<?php
$pageTitle = 'Ask Admin';

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/comm_helper.php';

requireLogin();
requireRole('student');

$pdo       = getDB();
$studentId = currentUserId();

ensureCommTables($pdo);

$subject = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($subject === '' || $message === '') {
        setFlash('error', 'Subject and message are required.');
    } else {
        $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_COLUMN);

        if (empty($admins)) {
            setFlash('error', 'No administrators are available to receive your message. Please try again later.');
        } else {
            foreach ($admins as $adminId) {
                sendInternalMessage($pdo, $studentId, (int)$adminId, $subject, $message, 'direct');
            }

            setFlash('success', 'Your question has been sent to the administrator. You will receive a reply via notifications when they respond.');
            header('Location: ask_admin.php');
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">
      <i class="bi bi-chat-dots me-2 text-primary"></i>Ask Admin
    </h1>
    <p class="page-subtitle">Send a question to the scholarship office. An administrator will respond through the system.</p>
  </div>
  <a href="notifications.php" class="btn btn-outline-secondary">
    <i class="bi bi-bell me-1"></i>Notifications
  </a>
</div>

<?php showFlash(); ?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-body">
        <form method="POST">
          <div class="mb-3">
            <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
            <input type="text" name="subject" class="form-control"
                   value="<?= e($subject) ?>" placeholder="Brief summary of your question" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Message <span class="text-danger">*</span></label>
            <textarea name="message" class="form-control" rows="8"
                      placeholder="Describe your question in detail…" required><?= e($message) ?></textarea>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-send me-2"></i>Send Question
            </button>
            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-body">
        <p class="mb-0 text-muted" style="font-size:13px;line-height:1.7;">
          <i class="bi bi-info-circle me-1"></i>
          Messages are delivered internally. Check your
          <a href="notifications.php">Notifications</a> page for administrator replies.
        </p>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
