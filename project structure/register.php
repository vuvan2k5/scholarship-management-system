<?php
// ============================================================
// register.php  –  Student self-registration
// ============================================================
require_once 'config/app.php';
require_once 'config/db.php';
require_once 'includes/auth.php';

// Already logged in → redirect
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo    = getDB();
$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'full_name'    => trim($_POST['full_name']    ?? ''),
        'email'        => trim($_POST['email']        ?? ''),
        'student_code' => trim($_POST['student_code'] ?? ''),
        'faculty'      => trim($_POST['faculty']      ?? ''),
        'major'        => trim($_POST['major']        ?? ''),
    ];

    $password  = $_POST['password']         ?? '';
    $password2 = $_POST['password_confirm'] ?? '';

    // Validation
    if (!$old['full_name'])                            $errors[] = 'Full name is required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (strlen($password) < 6)                         $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $password2)                       $errors[] = 'Passwords do not match.';
    if (!$old['student_code'])                         $errors[] = 'Student code is required.';
    if (!$old['faculty'])                              $errors[] = 'Faculty is required.';
    if (!$old['major'])                                $errors[] = 'Major is required.';

    // Unique email / student_code
    if (empty($errors)) {
        $dupEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $dupEmail->execute([$old['email']]);
        if ($dupEmail->fetch()) $errors[] = 'This email is already registered.';

        $dupCode = $pdo->prepare("SELECT id FROM users WHERE student_code = ?");
        $dupCode->execute([$old['student_code']]);
        if ($dupCode->fetch()) $errors[] = 'This student code is already registered.';
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // Insert user
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmtU = $pdo->prepare("
                INSERT INTO users (full_name, email, password_hash, role, student_code)
                VALUES (?, ?, ?, 'student', ?)
            ");
            $stmtU->execute([$old['full_name'], $old['email'], $hash, $old['student_code']]);
            $userId = (int)$pdo->lastInsertId();

            // Insert student_profile
            $gpa = (float)($_POST['gpa'] ?? 0);
            $stmtP = $pdo->prepare("
                INSERT INTO student_profiles
                    (student_id, faculty, major, gpa, activities_count, failed_subjects, research_count, is_disadvantaged)
                VALUES (?, ?, ?, ?, 0, 0, 0, 0)
            ");
            $stmtP->execute([$userId, $old['faculty'], $old['major'], $gpa]);

            // Welcome notification
            $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, 'Welcome to Scholarship System', 'Your account has been created. Browse available scholarships and apply today!', 'info')
            ")->execute([$userId]);

            $pdo->commit();

            // Auto-login
            $_SESSION['user_id']      = $userId;
            $_SESSION['user_name']    = $old['full_name'];
            $_SESSION['role']         = 'student';
            $_SESSION['email']        = $old['email'];
            $_SESSION['student_code'] = $old['student_code'];

            header('Location: ' . BASE_URL . '/student/dashboard.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register – Scholarship System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    .register-page {
      min-height: 100vh;
      background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
      display: flex; align-items: center; justify-content: center; padding: 32px 16px;
    }
    .register-card {
      background: #fff; border-radius: 20px;
      box-shadow: 0 24px 48px rgba(0,0,0,.2);
      padding: 40px; width: 100%; max-width: 580px;
    }
    .reg-logo { text-align: center; margin-bottom: 28px; }
    .reg-logo-icon {
      width: 56px; height: 56px; background: #2563eb; border-radius: 12px;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 26px; margin-bottom: 12px;
    }
    .section-divider {
      font-size: 11px; font-weight: 700; text-transform: uppercase;
      letter-spacing: .1em; color: #94a3b8;
      display: flex; align-items: center; gap: 10px; margin: 20px 0 16px;
    }
    .section-divider::before, .section-divider::after {
      content: ''; flex: 1; border-top: 1px solid #e2e8f0;
    }
  </style>
</head>
<body>
<div class="register-page">
  <div class="register-card">

    <div class="reg-logo">
      <div class="reg-logo-icon">🎓</div>
      <div style="font-size:22px;font-weight:800;color:#0f172a;">Create Account</div>
      <div style="font-size:13px;color:#64748b;">Register as a student to apply for scholarships</div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <div>
          <?php foreach ($errors as $err): ?>
            <div><?= e($err) ?></div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <form method="POST" novalidate>

      <div class="section-divider">Account Info</div>
      <div class="mb-3">
        <label class="form-label">Full Name <span class="text-danger">*</span></label>
        <input type="text" name="full_name" class="form-control"
               placeholder="Nguyen Van A" value="<?= e($old['full_name'] ?? '') ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Email Address <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control"
               placeholder="you@student.edu.vn" value="<?= e($old['email'] ?? '') ?>" required>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Password <span class="text-danger">*</span></label>
          <input type="password" name="password" class="form-control"
                 placeholder="Min. 6 characters" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
          <input type="password" name="password_confirm" class="form-control"
                 placeholder="Repeat password" required>
        </div>
      </div>

      <div class="section-divider">Student Info</div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Student Code <span class="text-danger">*</span></label>
          <input type="text" name="student_code" class="form-control"
                 placeholder="SV001" value="<?= e($old['student_code'] ?? '') ?>" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Current GPA</label>
          <input type="number" name="gpa" class="form-control"
                 placeholder="0.00 – 4.00" min="0" max="4" step="0.01"
                 value="<?= e($_POST['gpa'] ?? '') ?>">
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Faculty <span class="text-danger">*</span></label>
          <input type="text" name="faculty" class="form-control"
                 placeholder="Information Technology" value="<?= e($old['faculty'] ?? '') ?>" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Major <span class="text-danger">*</span></label>
          <input type="text" name="major" class="form-control"
                 placeholder="Software Engineering" value="<?= e($old['major'] ?? '') ?>" required>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 btn-lg mt-2">
        <i class="bi bi-person-plus-fill me-2"></i> Create Account
      </button>

      <p class="text-center mt-4 mb-0" style="font-size:13px;color:#64748b;">
        Already have an account?
        <a href="<?= BASE_URL ?>/login.php" style="color:#2563eb;font-weight:600;">Sign in</a>
      </p>
    </form>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
</body>
</html>
