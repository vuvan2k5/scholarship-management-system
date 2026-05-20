<?php

$pageTitle = 'Reviewer Dashboard';

require_once '../config/db.php';

require_once '../includes/auth.php';

requireLogin();

requireRole('council');

require_once '../includes/header.php';

require_once '../includes/navbar.php';

$pdo = getDB();

/* =========================
   REVIEWER STATISTICS
========================= */

$totalApplications = $pdo->query("
    SELECT COUNT(*)
    FROM applications
")->fetchColumn();

$totalScores = $pdo->query("
    SELECT COUNT(*)
    FROM evaluation_scores
    WHERE council_id = " . currentUserId()
)->fetchColumn();

$pendingApplications = $pdo->query("
    SELECT COUNT(*)
    FROM applications
    WHERE status = 'submitted'
")->fetchColumn();

/* =========================
   RECENT APPLICATIONS
========================= */

$recentApplications = $pdo->query("
    SELECT
        applications.*,
        users.full_name
    FROM applications
    JOIN users
        ON applications.student_id = users.id
    ORDER BY applications.id DESC
    LIMIT 5
");

?>

<div class="container py-4">

    <!-- PAGE HEADER -->

    <div class="mb-4">

        <h1 class="page-title">

            Reviewer Dashboard

        </h1>

        <p class="page-subtitle">

            Review and evaluate scholarship applications

        </p>

    </div>

    <!-- STATISTICS -->

    <div class="row g-4">

        <!-- Total Applications -->

        <div class="col-md-4">

            <div class="card h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="text-muted mb-2">

                                Applications

                            </p>

                            <h2 class="fw-bold text-primary">

                                <?= e($totalApplications) ?>

                            </h2>

                        </div>

                        <div
                            class="
                                bg-primary
                                text-white
                                rounded-circle
                                d-flex
                                align-items-center
                                justify-content-center
                            "
                            style="
                                width:60px;
                                height:60px;
                                font-size:24px;
                            "
                        >

                            📂

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- Pending -->

        <div class="col-md-4">

            <div class="card h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="text-muted mb-2">

                                Pending Reviews

                            </p>

                            <h2 class="fw-bold text-warning">

                                <?= e($pendingApplications) ?>

                            </h2>

                        </div>

                        <div
                            class="
                                bg-warning
                                text-white
                                rounded-circle
                                d-flex
                                align-items-center
                                justify-content-center
                            "
                            style="
                                width:60px;
                                height:60px;
                                font-size:24px;
                            "
                        >

                            ⏳

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- Evaluations -->

        <div class="col-md-4">

            <div class="card h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="text-muted mb-2">

                                My Evaluations

                            </p>

                            <h2 class="fw-bold text-success">

                                <?= e($totalScores) ?>

                            </h2>

                        </div>

                        <div
                            class="
                                bg-success
                                text-white
                                rounded-circle
                                d-flex
                                align-items-center
                                justify-content-center
                            "
                            style="
                                width:60px;
                                height:60px;
                                font-size:24px;
                            "
                        >

                            ⭐

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- RECENT APPLICATIONS -->

    <div class="card mt-4">

        <div class="card-body">

            <h4 class="mb-4">

                Recent Applications

            </h4>

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                        <tr>

                            <th>ID</th>

                            <th>Student</th>

                            <th>Status</th>

                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($recentApplications as $app): ?>

                            <tr>

                                <td>

                                    #<?= e($app['id']) ?>

                                </td>

                                <td>

                                    <?= e($app['full_name']) ?>

                                </td>

                                <td>

                                    <span class="badge bg-primary">

                                        <?= e($app['status']) ?>

                                    </span>

                                </td>

                                <td>

                                    <a
                                        href="review.php?id=<?= $app['id'] ?>"
                                        class="btn btn-sm btn-primary"
                                    >

                                        Review

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

<?php require_once '../includes/footer.php'; ?>