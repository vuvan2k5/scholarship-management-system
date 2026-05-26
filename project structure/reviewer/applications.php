<?php

$pageTitle = 'Review Applications';

require_once '../config/db.php';

require_once '../includes/auth.php';

requireLogin();

requireRole('council', 'reviewer');

require_once '../includes/header.php';

require_once '../includes/navbar.php';

$pdo = getDB();

/* =========================
   FETCH APPLICATIONS
========================= */

$sql = "

    SELECT

        applications.*,

        users.full_name,

        scholarship_programs.name AS program_name

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

    <!-- PAGE HEADER -->

    <div class="mb-4">

        <h1 class="page-title">

            Review Applications

        </h1>

        <p class="page-subtitle">

            Review and evaluate student scholarship applications

        </p>

    </div>

    <!-- APPLICATION TABLE -->

    <div class="table-card">

        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Student</th>

                        <th>Scholarship</th>

                        <th>Status</th>

                        <th>Submitted At</th>

                        <th width="180">

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

                                <?= e($app['program_name']) ?>

                            </td>

                            <td>

                                <?php

                                $badge = 'bg-secondary';

                                if ($app['status'] === 'submitted') {
                                    $badge = 'bg-warning';
                                }

                                if ($app['status'] === 'approved') {
                                    $badge = 'bg-success';
                                }

                                if ($app['status'] === 'rejected') {
                                    $badge = 'bg-danger';
                                }

                                ?>

                                <span class="badge <?= $badge ?>">

                                    <?= e($app['status']) ?>

                                </span>

                            </td>

                            <td>

                                <?= e($app['submitted_at']) ?>

                            </td>

                            <td>

                                <div class="d-flex gap-2">

                                    <a
                                        href="review.php?id=<?= $app['id'] ?>"
                                        class="btn btn-primary btn-sm"
                                    >

                                        Review

                                    </a>

                                    <a
                                        href="scores.php?id=<?= $app['id'] ?>"
                                        class="btn btn-success btn-sm"
                                    >

                                        Scores

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

<?php require_once '../includes/footer.php'; ?>