<?php

$pageTitle = 'Edit User';

require_once '../../config/db.php';

require_once '../../includes/auth.php';

requireLogin();

requireRole('admin');

require_once '../../includes/header.php';

require_once '../../includes/navbar.php';

$pdo = getDB();

$id = $_GET['id'] ?? null;

if (!$id) {

    die('Invalid User ID');
}

$sql = "
    SELECT *
    FROM users
    WHERE id = ?
";

$stmt = $pdo->prepare($sql);

$stmt->execute([$id]);

$user = $stmt->fetch();

if (!$user) {

    die('User not found');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name']);

    $email = trim($_POST['email']);

    $role = trim($_POST['role']);

    $student_code = trim($_POST['student_code']);

    if (
        empty($full_name) ||
        empty($email) ||
        empty($role)
    ) {

        $error = 'Please fill in all required fields.';

    } else {

        $update = "
            UPDATE users
            SET
                full_name = ?,
                email = ?,
                role = ?,
                student_code = ?
            WHERE id = ?
        ";

        $stmt = $pdo->prepare($update);

        $stmt->execute([

            $full_name,
            $email,
            $role,
            $student_code,
            $id

        ]);

        header('Location: index.php');

        exit;
    }
}

?>

<div class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="card">

                <div class="card-body">

                    <h2 class="fw-bold mb-4">
                        Edit User
                    </h2>

                    <?php if (!empty($error)): ?>

                        <div class="alert alert-danger">

                            <?= e($error) ?>

                        </div>

                    <?php endif; ?>

                    <form method="POST">

                        <div class="mb-3">

                            <label class="form-label">
                                Full Name
                            </label>

                            <input
                                type="text"
                                name="full_name"
                                class="form-control"
                                value="<?= e($user['full_name']) ?>"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                value="<?= e($user['email']) ?>"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Role
                            </label>

                            <select
                                name="role"
                                class="form-select"
                                required
                            >

                                <option
                                    value="admin"
                                    <?= $user['role'] === 'admin' ? 'selected' : '' ?>
                                >
                                    Admin
                                </option>

                                <option
                                    value="student"
                                    <?= $user['role'] === 'student' ? 'selected' : '' ?>
                                >
                                    Student
                                </option>

                            </select>

                        </div>

                        <div class="mb-4">

                            <label class="form-label">
                                Student Code
                            </label>

                            <input
                                type="text"
                                name="student_code"
                                class="form-control"
                                value="<?= e($user['student_code']) ?>"
                            >

                        </div>

                        <div class="d-flex gap-2">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Update User
                            </button>

                            <a
                                href="index.php"
                                class="btn btn-secondary"
                            >
                                Back
                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require_once '../../includes/footer.php'; ?>