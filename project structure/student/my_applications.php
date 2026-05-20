<?php

$pageTitle = 'My Applications';

require_once __DIR__ . '/../../config/db.php';

require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

requireRole('student');

require_once __DIR__ . '/../../includes/header.php';

require_once __DIR__ . '/../../includes/navbar.php';

$pdo = getDB();

$studentId = currentUserId();

/* =========================
   FETCH APPLICATIONS
========================= */

$sql = "

    SELECT

        applications.*,

        scholarship_programs.name AS program_name

    FROM applications

    JOIN scholarship_programs
        ON applications.program_id = scholarship_programs.id

    WHERE applications.student_id = ?

    ORDER BY applications.submitted_at DESC

";

$stmt = $pdo->prepare($sql);

$stmt->execute([$studentId]);

$applications = $stmt->fetchAll();

?>

<div class="container py-4">

    <!-- PAGE HEADER -->

    <div class="mb-4">

        <h1 class="page-title">

            My Applications

        </h1>

        <p class="page-subtitle">

            Track your scholarship application progress

        </p>

    </div>

    <!-- EMPTY STATE -->

    <?php if (empty($applications)): ?>

        <div class="card text-center py-5">

            <div class="card-body">

                <h4 class="mb-3">

                    No Applications Found

                </h4>

                <p class="text-muted mb-4">

                    You have not submitted any scholarship applications yet.

                </p>

                <a
                    href="apply.php"
                    class="btn btn-primary"
                >

                    Apply Scholarship

                </a>

            </div>

        </div>

    <?php else: ?>

        <!-- APPLICATION TABLE -->

        <div class="card">

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>

                            <tr>

                                <th>ID</th>

                                <th>Scholarship Program</th>

                                <th>Status</th>

                                <th>Eligible</th>

                                <th>Submitted At</th>

                                <th width="120">

                                    Action

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

                                        <?= e($app['program_name']) ?>

                                    </td>

                                    <td>

                                        <span
                                            class="
                                                badge
                                                badge-status-<?= e($app['status']) ?>
                                            "
                                        >

                                            <?= e(
                                                ucfirst($app['status'])
                                            ) ?>

                                        </span>

                                    </td>

                                    <td>

                                        <?php if ($app['eligible'] === null): ?>

                                            <span class="badge bg-secondary">

                                                Pending

                                            </span>

                                        <?php elseif ($app['eligible']): ?>

                                            <span class="badge bg-success">

                                                Yes

                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-danger">

                                                No

                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?= e($app['submitted_at']) ?>

                                    </td>

                                    <td>

                                        <a
                                            href="application_details.php?id=<?= $app['id'] ?>"
                                            class="btn btn-sm btn-primary"
                                        >

                                            View

                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>