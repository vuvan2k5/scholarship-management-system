<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/db.php';

$pdo = getDB();
$email = 'admin@scholarship.edu.vn';

// CLI usage: php reset_admin_password.php newpassword
if (php_sapi_name() === 'cli') {
    if ($argc < 2) {
        echo "Usage: php reset_admin_password.php <new_password>\n";
        exit(1);
    }
    $new = $argv[1];
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $stmt->execute([$hash, $email]);
    echo "Password for {$email} updated.\n";
    exit(0);
}

// Web form usage (local only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $_POST['password'] ?? '';
    if (trim($new) === '') {
        $message = 'Please provide a non-empty password.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        $stmt->execute([$hash, $email]);
        $message = 'Password updated for ' . htmlspecialchars($email) . '. Please delete this script after use.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Admin Password</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <div class="container" style="max-width:540px;">
    <h3>Reset Admin Password (local only)</h3>
    <?php if (!empty($message)): ?>
      <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="mb-3">
        <label class="form-label">New password for <?= htmlspecialchars($email) ?></label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button class="btn btn-primary">Set Password</button>
    </form>
    <hr>
    <p class="small text-muted">After use, remove this file: <strong>admin/reset_admin_password.php</strong></p>
  </div>
</body>
</html>
