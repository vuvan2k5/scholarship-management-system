<?php

$pageTitle = 'Applications Management';

require_once '../../config/db.php';

require_once '../../includes/auth.php';

requireLogin();

requireRole('admin');

require_once '../../includes/header.php';

require_once '../../includes/navbar.php';

$pdo = getDB();

$sql = "

    SELECT

        applications.*,

        users.full_name,

        scholarship_programs.name

    FROM applications

    JOIN users
        ON applications.student_id = users.id

    JOIN scholarship_programs
        ON applications.program_id = scholarship_programs.id

    ORDER BY applications.id DESC

";

$stmt = $pdo->query($sql);

$applications = $stmt->fetchAll();

?>

<div class="container py-4">

    <!-- PAGE TITLE -->

    <div class="mb-4">

        <h1 class="page-title">

            Applications Management

        </h1>

        <p class="page-subtitle">

            Manage all scholarship applications

        </p>

    </div>

    <!-- ACTION BUTTON -->

    <div class="mb-4">

        <a
            href="create.php"
            class="btn btn-primary"
        >

            ➕ Add Application

        </a>

    </div>

    <!-- TABLE -->

    <div class="table-card">

        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Student</th>

                        <th>Program</th>

                        <th>Status</th>

                        <th>Eligible</th>

                        <th>Submitted</th>

                        <th width="220">
                            Actions
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($applications as $app): ?>

                        <tr>

                            <td>

                                #<?= e($app['id']) ?>

                            </td>

                            <td>

                                <?= e($app['full_name']) ?>

                            </td>

                            <td>

                                <?= e($app['name']) ?>

                            </td>

                            <td>

                                <?php

                                $status = $app['status'];

                                $badge = 'bg-secondary';

                                if ($status === 'pending') {
                                    $badge = 'bg-warning';
                                }

                                if ($status === 'approved') {
                                    $badge = 'bg-success';
                                }

                                if ($status === 'rejected') {
                                    $badge = 'bg-danger';
                                }

                                ?>

                                <span class="badge <?= $badge ?>">

                                    <?= e($status) ?>

                                </span>

                            </td>

                            <td>

                                <?php if ($app['eligible']): ?>

                                    <span class="badge bg-success">

                                        YES

                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-danger">

                                        NO

                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?= e($app['submitted_at']) ?>

                            </td>

                            <td>

                                <div class="d-flex gap-2">

                                    <a
                                        href="edit.php?id=<?= $app['id'] ?>"
                                        class="
                                            btn
                                            btn-warning
                                            btn-sm
                                            btn-action
                                        "
                                    >

                                        Edit

                                    </a>

                                    <a
                                        href="delete.php?id=<?= $app['id'] ?>"
                                        class="
                                            btn
                                            btn-danger
                                            btn-sm
                                            btn-action
                                        "
                                        onclick="
                                            return confirm(
                                                'Delete this application?'
                                            )
                                        "
                                    >

                                        Delete

                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php require_once '../../includes/footer.php'; ?>