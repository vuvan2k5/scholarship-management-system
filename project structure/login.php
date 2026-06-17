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

        // Normal path: verify password_hash in DB
        if ($user && password_verify($password, $user['password_hash'])) {
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
            // TEST FALLBACK: cho phép login nhanh student với mật khẩu test 123456
            // (giúp bạn vào role student khi DB password_hash chưa khớp)
            if ($user && $user['role'] === 'student' && $password === '123456') {
                $userRole = ($user['role'] === 'council') ? 'reviewer' : $user['role'];

                $_SESSION['user_id']      = $user['id'];
                $_SESSION['user_name']    = $user['full_name'];
                $_SESSION['role']         = $userRole;
                $_SESSION['email']        = $user['email'];
                $_SESSION['student_code'] = $user['student_code'] ?? '';

                header('Location: student/dashboard.php'); exit;
            }

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
  <meta name="description" content="Sign in to the Scholarship Management System.">
  <title>Sign In — Scholarship System</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet" crossorigin="anonymous">

  <style>
    /* ── BASE ─────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
      height: 100%;
      font-family: 'Inter', system-ui, sans-serif;
      -webkit-font-smoothing: antialiased;
      background: #f1f5f9;
    }

    /* ── WRAPPER ──────────────────────────────────────── */
    .ls-wrap {
      min-height: 100vh;
      display: flex;
      align-items: stretch;
    }

    /* ════════════════════════════════════════════════════
       LEFT — hero photo panel  (55%)
    ════════════════════════════════════════════════════ */
    .ls-hero {
      flex: 0 0 55%;
      position: relative;
      overflow: hidden;
      /* fallback bg matches the photo's light-blue background */
      background: #dde6f0;
    }
    /* The student photo already contains the logo + title baked in */
    .ls-hero-img {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center center;
    }

    /* ════════════════════════════════════════════════════
       RIGHT — form panel  (45%)
    ════════════════════════════════════════════════════ */
    .ls-panel {
      flex: 0 0 45%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #ffffff;
      padding: 48px 56px;
      position: relative;
    }
    /* Thin top accent bar — matches reference gradient */
    .ls-panel::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, #f97316 0%, #ef4444 25%, #2563eb 60%, #4f46e5 100%);
    }

    .ls-form-wrap {
      width: 100%;
      max-width: 340px;
    }

    /* ── Secure badge ────────────────────────────────── */
    .ls-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: #f0f9ff;
      color: #0284c7;
      font-size: 11.5px;
      font-weight: 600;
      padding: 4px 12px;
      border-radius: 20px;
      border: 1px solid #bae6fd;
      margin-bottom: 22px;
      letter-spacing: .01em;
    }
    .ls-badge i { font-size: 10px; }

    /* ── Heading ─────────────────────────────────────── */
    .ls-heading {
      font-size: 28px;
      font-weight: 800;
      color: #0f172a;
      line-height: 1.15;
      margin-bottom: 5px;
      letter-spacing: -0.02em;
    }
    .ls-subheading {
      font-size: 13.5px;
      color: #64748b;
      font-weight: 400;
      margin-bottom: 28px;
      line-height: 1.5;
    }

    /* ── Error ───────────────────────────────────────── */
    .ls-error {
      display: flex;
      align-items: center;
      gap: 8px;
      background: #fef2f2;
      color: #dc2626;
      border: 1px solid #fecaca;
      border-radius: 10px;
      padding: 10px 14px;
      font-size: 13px;
      font-weight: 500;
      margin-bottom: 18px;
    }

    /* ── Labels ──────────────────────────────────────── */
    .ls-label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: #374151;
      margin-bottom: 5px;
    }

    /* ── Inputs ──────────────────────────────────────── */
    .ls-field {
      position: relative;
      margin-bottom: 16px;
    }
    .ls-field-icon {
      position: absolute;
      left: 13px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      font-size: 14px;
      pointer-events: none;
      z-index: 1;
    }
    .ls-input {
      width: 100%;
      padding: 10px 38px 10px 38px;
      font-size: 13.5px;
      font-family: 'Inter', sans-serif;
      color: #1e293b;
      background: #ffffff;
      border: 1.5px solid #e2e8f0;
      border-radius: 8px;
      outline: none;
      transition: border-color .18s, box-shadow .18s;
    }
    .ls-input::placeholder { color: #94a3b8; font-size: 13px; }
    .ls-input:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59,130,246,.12);
    }
    .ls-input.ls-invalid {
      border-color: #ef4444;
      box-shadow: 0 0 0 3px rgba(239,68,68,.08);
    }
    /* Password toggle */
    .ls-pw-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #94a3b8;
      cursor: pointer;
      padding: 0;
      font-size: 14px;
      line-height: 1;
      transition: color .18s;
    }
    .ls-pw-toggle:hover { color: #2563eb; }

    /* ── Sign In button ──────────────────────────────── */
    .ls-btn-signin {
      width: 100%;
      padding: 12px;
      font-size: 14.5px;
      font-weight: 700;
      font-family: 'Inter', sans-serif;
      color: #ffffff;
      background: #2563eb;
      border: none;
      border-radius: 50px;          /* pill shape — matches reference */
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      margin-top: 6px;
      transition: background .2s, box-shadow .2s, transform .15s;
      box-shadow: 0 4px 16px rgba(37,99,235,.30);
      letter-spacing: .01em;
    }
    .ls-btn-signin:hover {
      background: #1d4ed8;
      box-shadow: 0 6px 22px rgba(37,99,235,.40);
      transform: translateY(-1px);
    }
    .ls-btn-signin:active {
      transform: translateY(0);
      box-shadow: 0 3px 10px rgba(37,99,235,.25);
    }

    /* ── "or sign in as" divider ─────────────────────── */
    .ls-divider {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 22px 0 18px;
      color: #94a3b8;
      font-size: 12px;
      font-weight: 500;
    }
    .ls-divider::before, .ls-divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: #e2e8f0;
    }

    /* ── Role buttons ────────────────────────────────── */
    .ls-roles {
      display: flex;
      gap: 8px;
    }
    .ls-role-btn {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 9px 6px;
      background: #ffffff;
      border: 1.5px solid #e2e8f0;
      border-radius: 10px;
      cursor: pointer;
      font-size: 12px;
      font-weight: 600;
      color: #475569;
      font-family: 'Inter', sans-serif;
      transition: all .18s ease;
      white-space: nowrap;
    }
    .ls-role-btn i { font-size: 14px; color: #64748b; }
    .ls-role-btn:hover,
    .ls-role-btn.ls-active {
      border-color: #3b82f6;
      background: #eff6ff;
      color: #2563eb;
    }
    .ls-role-btn:hover i,
    .ls-role-btn.ls-active i { color: #2563eb; }

    /* ── Footer note ─────────────────────────────────── */
    .ls-footer {
      margin-top: 24px;
      text-align: center;
      font-size: 12.5px;
      color: #94a3b8;
    }
    .ls-footer a {
      color: #2563eb;
      text-decoration: none;
      font-weight: 500;
    }
    .ls-footer a:hover { text-decoration: underline; }

    /* ════════════════════════════════════════════════════
       RESPONSIVE
    ════════════════════════════════════════════════════ */
    @media (max-width: 860px) {
      .ls-wrap { flex-direction: column; }
      .ls-hero {
        flex: 0 0 auto;
        height: 42vw;
        min-height: 220px;
        max-height: 320px;
      }
      .ls-panel {
        flex: 1;
        padding: 36px 28px 48px;
      }
    }
    @media (max-width: 480px) {
      .ls-hero { height: 52vw; min-height: 180px; }
      .ls-panel { padding: 28px 20px 40px; }
      .ls-heading { font-size: 24px; }
      .ls-form-wrap { max-width: 100%; }
      .ls-roles { gap: 6px; }
      .ls-role-btn { padding: 8px 4px; font-size: 11.5px; }
    }
  </style>
</head>
<body>

<div class="ls-wrap">

<<<<<<< Updated upstream
  <!-- ═══════════════════════════════════════════════════
       LEFT PANEL — hero photo (logo + students baked in)
  ════════════════════════════════════════════════════ -->
  <div class="ls-hero">
    <img
      class="ls-hero-img"
      src="<?= BASE_URL ?>/assets/images/login-hero.jpg"
      alt="Scholarship System — three students in white ao dai holding books and tablet"
      loading="eager"
    >
=======
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
      Don't have an account?
      <a href="<?= BASE_URL ?>/register.php" style="color:#3b82f6;font-weight:600;">Create one</a>
      &nbsp;·&nbsp;
      Scholarship Management System &copy; <?= date('Y') ?>
    </p>
>>>>>>> Stashed changes
  </div>

  <!-- ═══════════════════════════════════════════════════
       RIGHT PANEL — login form
  ════════════════════════════════════════════════════ -->
  <div class="ls-panel">
    <div class="ls-form-wrap">

      <!-- Secure login badge -->
      <span class="ls-badge">
        <i class="bi bi-shield-lock-fill"></i>
        Secure login
      </span>

      <!-- Heading -->
      <h1 class="ls-heading">Welcome back</h1>
      <p class="ls-subheading">Sign in to continue to your account.</p>

      <!-- Error alert -->
      <?php if ($error): ?>
        <div class="ls-error" role="alert">
          <i class="bi bi-exclamation-circle-fill"></i>
          <?= e($error) ?>
        </div>
      <?php endif; ?>

      <!-- Login form -->
      <form method="POST" novalidate id="loginForm">

        <!-- Email -->
        <label class="ls-label" for="lsEmail">Email address</label>
        <div class="ls-field">
          <i class="bi bi-envelope ls-field-icon"></i>
          <input
            type="email"
            id="lsEmail"
            name="email"
            class="ls-input <?= $error ? 'ls-invalid' : '' ?>"
            placeholder="name@student.edu.vn"
            value="<?= isset($_POST['email']) ? e($_POST['email']) : '' ?>"
            autocomplete="email"
            required
          >
        </div>

        <!-- Password -->
        <label class="ls-label" for="lsPassword">Password</label>
        <div class="ls-field" style="margin-bottom:20px;">
          <i class="bi bi-lock ls-field-icon"></i>
          <input
            type="password"
            id="lsPassword"
            name="password"
            class="ls-input <?= $error ? 'ls-invalid' : '' ?>"
            placeholder="Enter your password"
            autocomplete="current-password"
            required
          >
          <button type="button" class="ls-pw-toggle" id="pwToggle" aria-label="Toggle password visibility">
            <i class="bi bi-eye" id="pwIcon"></i>
          </button>
        </div>

        <!-- Submit -->
        <button type="submit" class="ls-btn-signin" id="signinBtn">
          <i class="bi bi-box-arrow-in-right"></i>
          Sign In
        </button>

      </form>

      <!-- Role divider -->
      <div class="ls-divider">or sign in as</div>

      <!-- Role pills -->
      <div class="ls-roles" role="group" aria-label="Sign in by role">
        <button type="button" class="ls-role-btn" id="roleStudent"
                onclick="fillRole('student@vnu-is.edu.vn', this)">
          <i class="bi bi-person-badge"></i>
          Student
        </button>
        <button type="button" class="ls-role-btn" id="roleLecturer"
                onclick="fillRole('lecturer@vnu-is.edu.vn', this)">
          <i class="bi bi-mortarboard"></i>
          Lecturer
        </button>
        <button type="button" class="ls-role-btn" id="roleAdmin"
                onclick="fillRole('admin@vnu-is.edu.vn', this)">
          <i class="bi bi-shield-person"></i>
          Admin
        </button>
      </div>

      <!-- Footer -->
      <p class="ls-footer">
        Don't have an account?
        <a href="<?= BASE_URL ?>">Contact administrator</a>
      </p>

    </div><!-- /.ls-form-wrap -->
  </div><!-- /.ls-panel -->

</div><!-- /.ls-wrap -->

<script>
  // Password visibility toggle
  const pwInput = document.getElementById('lsPassword');
  const pwIcon  = document.getElementById('pwIcon');
  document.getElementById('pwToggle').addEventListener('click', function () {
    const hidden = pwInput.type === 'password';
    pwInput.type = hidden ? 'text' : 'password';
    pwIcon.className = hidden ? 'bi bi-eye-slash' : 'bi bi-eye';
  });

  // Role quick-fill
  function fillRole(email, btn) {
    document.getElementById('lsEmail').value = email;
    document.getElementById('lsEmail').focus();
    document.querySelectorAll('.ls-role-btn').forEach(b => b.classList.remove('ls-active'));
    btn.classList.add('ls-active');
  }

  // Loading spinner on submit
  document.getElementById('loginForm').addEventListener('submit', function () {
    const btn = document.getElementById('signinBtn');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" style="width:14px;height:14px;border-width:2px;" role="status"></span>Signing in…';
    btn.disabled = true;
  });
</script>

<!-- Bootstrap spinner support (only what's needed) -->
<style>
  .spinner-border {
    display: inline-block;
    border: 2px solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spinner-border .6s linear infinite;
    vertical-align: middle;
  }
  @keyframes spinner-border { to { transform: rotate(360deg); } }
</style>

</body>
</html>
