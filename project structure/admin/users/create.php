<?php

$pageTitle = 'Create User';

require_once '../../config/db.php';

require_once '../../includes/auth.php';

requireLogin();

requireRole('admin');

require_once '../../includes/header.php';

require_once '../../includes/navbar.php';

$pdo = getDB();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name']);

    $email = trim($_POST['email']);

    $password = password_hash(
        $_POST['password'],
        PASSWORD_DEFAULT
    );

    $role = trim($_POST['role']);

    $student_code = trim($_POST['student_code']);

    if (
        empty($full_name) ||
        empty($email) ||
        empty($_POST['password']) ||
        empty($role)
    ) {

        $error = 'Please fill in all required fields.';

    } else {

        $sql = "
            INSERT INTO users (
                full_name,
                email,
                password_hash,
                role,
                student_code
            )
            VALUES (?, ?, ?, ?, ?)
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([

            $full_name,
            $email,
            $password,
            $role,
            $student_code

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
                        Create User
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
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Password
                            </label>

                            <input
                                type="password"
                                name="password"
                                class="form-control"
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

                                <option value="">
                                    Select Role
                                </option>

                                <option value="admin">
                                    Admin
                                </option>

                                <option value="student">
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
                            >

                        </div>

                        <div class="d-flex gap-2">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Create User
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