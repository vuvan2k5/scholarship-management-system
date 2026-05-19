<?php

$pageTitle = 'Users Management';

require_once '../../config/db.php';

require_once '../../includes/auth.php';

requireLogin();

requireRole('admin');

require_once '../../includes/header.php';

require_once '../../includes/navbar.php';

$pdo = getDB();

$sql = "
    SELECT *
    FROM users
    ORDER BY id DESC
";

$stmt = $pdo->query($sql);

$users = $stmt->fetchAll();

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">
                Users Management
            </h2>

            <p class="text-muted">
                Manage all system users
            </p>

        </div>

        <a
            href="create.php"
            class="btn btn-primary"
        >
            <i class="bi bi-plus-circle"></i>

            Add User
        </a>

    </div>

    <div class="card">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                        <tr>

                            <th>ID</th>

                            <th>Full Name</th>

                            <th>Email</th>

                            <th>Role</th>

                            <th>Student Code</th>

                            <th width="180">
                                Actions
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($users as $user): ?>

                            <tr>

                                <td>
                                    <?= e($user['id']) ?>
                                </td>

                                <td>
                                    <?= e($user['full_name']) ?>
                                </td>

                                <td>
                                    <?= e($user['email']) ?>
                                </td>

                                <td>

                                    <?php if ($user['role'] === 'admin'): ?>

                                        <span class="badge bg-danger">

                                            Admin

                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-primary">

                                            Student

                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?= e($user['student_code']) ?>
                                </td>

                                <td>

                                    <a
                                        href="edit.php?id=<?= $user['id'] ?>"
                                        class="btn btn-sm btn-warning"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="delete.php?id=<?= $user['id'] ?>"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Delete this user?')"
                                    >
                                        Delete
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<?php require_once '../../includes/footer.php'; ?>