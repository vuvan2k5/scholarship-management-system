<?php
// ============================================================
// admin/users/edit.php
// ============================================================
ob_start();

$pageTitle = 'Edit User';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

// ── DB & data fetch BEFORE any HTML output ──────────────────
$pdo = getDB();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

$error = '';

if ($user && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $student_code = trim($_POST['student_code']);
    $password = trim($_POST['password']);

    if (empty($full_name) || empty($email) || empty($role)) {
        $error = 'Please fill in all required fields.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
        $check->execute([$email, $id]);
        if ($check->rowCount() > 0) {
            $error = 'Email is already in use by another user.';
        } else {
            if (!empty($password)) {
                $sql = "UPDATE users SET full_name = ?, email = ?, password_hash = ?, role = ?, student_code = ? WHERE id = ?";
                $args = [$full_name, $email, password_hash($password, PASSWORD_BCRYPT), $role, $student_code !== '' ? $student_code : null, $id];
            } else {
                $sql = "UPDATE users SET full_name = ?, email = ?, role = ?, student_code = ? WHERE id = ?";
                $args = [$full_name, $email, $role, $student_code !== '' ? $student_code : null, $id];
            }
            $pdo->prepare($sql)->execute($args);

            // Redirect BEFORE any HTML is sent
            header('Location: index.php');
            exit;
        }
    }
}

// ── HTML output starts here ──────────────────────────────────
require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

if (!$user): ?>
    <div class="container py-5">
        <div class="alert alert-danger">User not found.</div>
    </div>
    <?php
    require_once '../../includes/footer.php';
    exit;
endif;
?>

<div class="container py-4">
    <!-- PAGE HEADER -->
    <div class="mb-4">
        <h1 class="page-title">Edit User</h1>
        <p class="page-subtitle">Modify profile settings, credentials, and roles for user #<?= e($id) ?></p>
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
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>"
                            required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>"
                                required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password (Leave blank to keep current)</label>
                            <input type="password" name="password" class="form-control"
                                placeholder="Enter new password">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">System Role <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>Student
                                </option>
                                <option value="reviewer" <?= ($user['role'] === 'reviewer' || $user['role'] === 'council') ? 'selected' : '' ?>>Reviewer / Council</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Student Code (Optional)</label>
                            <input type="text" name="student_code" class="form-control"
                                value="<?= e($user['student_code']) ?>">
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update User</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php';
ob_end_flush(); ?>