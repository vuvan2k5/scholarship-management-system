<?php
// ============================================================
// admin/users/create.php
// ============================================================

$pageTitle = 'Create User';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo   = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);
    $student_code = trim($_POST['student_code']);

    if (empty($full_name) || empty($email) || empty($password) || empty($role)) {
        $error = 'Please fill in all required fields.';
    } else {
        $password_to_store = password_hash($password, PASSWORD_BCRYPT);

        // Let's check duplicate email
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) {
            $error = 'Email is already registered.';
        } else {
            $sql = "
                INSERT INTO users (full_name, email, password_hash, role, student_code)
                VALUES (?, ?, ?, ?, ?)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $full_name,
                $email,
                $password_to_store,
                $role,
                $student_code !== '' ? $student_code : null
            ]);

            header('Location: index.php');
            exit;
        }
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="container py-4">
    <!-- PAGE HEADER -->
    <div class="mb-4">
        <h1 class="page-title">Create User</h1>
        <p class="page-subtitle">Add a new user account with administrative, student, or reviewer access</p>
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
                        <input type="text" name="full_name" class="form-control" placeholder="e.g. John Doe" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="e.g. johndoe@university.edu" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">System Role <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="">Select Role</option>
                                <option value="admin">Admin</option>
                                <option value="student">Student</option>
                                <option value="reviewer">Reviewer / Council</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Student Code (Optional)</label>
                            <input type="text" name="student_code" class="form-control" placeholder="e.g. B21DCCN123">
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Create User</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>