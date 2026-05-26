// require_once 'includes/auth.php';
// require_once 'includes/header.php';
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'includes/auth.php';

$pdo   = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $password === $user['password_hash']) {
            // Normalize legacy 'council' role to 'reviewer'
            $userRole = ($user['role'] === 'council') ? 'reviewer' : $user['role'];

            $_SESSION['user_id']      = $user['id'];
            $_SESSION['user_name']    = $user['full_name'];
            $_SESSION['role']         = $userRole;
            $_SESSION['email']        = $user['email'];
            $_SESSION['student_code'] = $user['student_code'] ?? '';

            if ($userRole === 'admin') {
                header('Location: admin/dashboard.php'); exit;
            } elseif ($userRole === 'student') {
                header('Location: student/dashboard.php'); exit;
            } elseif ($userRole === 'reviewer') {
                header('Location: reviewer/dashboard.php'); exit;
            } else {
                header('Location: login.php'); exit;
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login – Scholarship System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<div class="login-page">
  <div class="login-card">

    <!-- Logo -->
    <div class="login-logo">
      <div class="login-logo-icon">🎓</div>
      <div class="login-title">Scholarship System</div>
      <div class="login-subtitle">Sign in to your account</div>
    </div>

    <!-- Error -->
    <?php if ($error): ?>
      <div class="alert alert-danger mb-4">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?= e($error) ?>
      </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" novalidate>
      <div class="mb-3">
        <label class="form-label">Email Address</label>
        <div class="input-group">
          <span class="input-group-text" style="background:#f8fafc;border-color:#e2e8f0;">
            <i class="bi bi-envelope" style="color:#94a3b8;"></i>
          </span>
          <input type="email" name="email" class="form-control"
                 placeholder="you@university.edu"
                 value="<?= isset($_POST['email']) ? e($_POST['email']) : '' ?>"
                 required style="border-left:none;">
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label">Password</label>
        <div class="input-group">
          <span class="input-group-text" style="background:#f8fafc;border-color:#e2e8f0;">
            <i class="bi bi-lock" style="color:#94a3b8;"></i>
          </span>
          <input type="password" name="password" class="form-control"
                 placeholder="Enter your password"
                 required style="border-left:none;">
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 btn-lg">
        <i class="bi bi-box-arrow-in-right me-2"></i> Sign In
      </button>
    </form>

    <p class="text-center mt-4 mb-0" style="font-size:12px;color:#94a3b8;">
      Scholarship Management System &copy; <?= date('Y') ?>
    </p>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous" defer></script>
</body>
</html>
