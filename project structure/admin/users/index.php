<?php

$pageTitle = 'Users Management';

require_once '../../config/db.php';

require_once '../../includes/auth.php';

requireLogin();

requireRole('admin');

require_once '../../includes/header.php';

require_once '../../includes/navbar.php';

$pdo = getDB();

$sql = "SELECT * FROM users ORDER BY id DESC";

$stmt = $pdo->query($sql);

$users = $stmt->fetchAll();

?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <h2>
            Users Management
        </h2>

        <a
            href="create.php"
            class="btn btn-primary"
        >

            <i class="bi bi-plus-circle"></i>

            Add New User

        </a>

    </div>

    <div class="card">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead>

                        <tr>

                            <th>ID</th>

                            <th>Full Name</th>

                            <th>Email</th>

                            <th>Role</th>

                            <th>Student Code</th>

                            <th>Actions</th>

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

                                    <span class="badge bg-secondary">

                                        <?= e($user['role']) ?>

                                    </span>

                                </td>

                                <td>
                                    <?= e($user['student_code']) ?>
                                </td>

                                <td>

                                    <a
                                        href="edit.php?id=<?= $user['id'] ?>"
                                        class="btn btn-warning btn-sm"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="delete.php?id=<?= $user['id'] ?>"
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('Are you sure?')"
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