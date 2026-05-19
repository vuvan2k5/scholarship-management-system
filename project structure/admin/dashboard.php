<?php

$pageTitle = 'Admin Dashboard';

require_once '../config/db.php';

require_once '../includes/auth.php';

requireLogin();

requireRole('admin');

require_once '../includes/header.php';

require_once '../includes/navbar.php';

$pdo = getDB();

/* =========================
   STATISTICS
========================= */

$totalApplications = $pdo->query("
    SELECT COUNT(*) FROM applications
")->fetchColumn();

$totalStudents = $pdo->query("
    SELECT COUNT(*) FROM student_profiles
")->fetchColumn();

$totalScores = $pdo->query("
    SELECT COUNT(*) FROM evaluation_scores
")->fetchColumn();

$totalNotifications = $pdo->query("
    SELECT COUNT(*) FROM notifications
")->fetchColumn();

/* =========================
   RECENT APPLICATIONS
========================= */

$recentApps = $pdo->query("
    SELECT *
    FROM applications
    ORDER BY id DESC
    LIMIT 5
");

?>

<div class="container py-4">

    <!-- PAGE HEADER -->

    <div class="mb-4">

        <h2 class="fw-bold">

            Welcome,
            <?= e(currentUserName()) ?>

        </h2>

        <p class="text-muted">

            Scholarship Management System Dashboard

        </p>

    </div>

    <!-- STATISTICS CARDS -->

    <div class="row g-4">

        <!-- Applications -->

        <div class="col-md-3">

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

        <!-- Students -->

        <div class="col-md-3">

            <div class="card h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="text-muted mb-2">

                                Students

                            </p>

                            <h2 class="fw-bold text-success">

                                <?= e($totalStudents) ?>

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

                            🎓

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- Scores -->

        <div class="col-md-3">

            <div class="card h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="text-muted mb-2">

                                Evaluation Scores

                            </p>

                            <h2 class="fw-bold text-warning">

                                <?= e($totalScores) ?>

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

                            ⭐

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- Notifications -->

        <div class="col-md-3">

            <div class="card h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="text-muted mb-2">

                                Notifications

                            </p>

                            <h2 class="fw-bold text-danger">

                                <?= e($totalNotifications) ?>

                            </h2>

                        </div>

                        <div
                            class="
                                bg-danger
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

                            🔔

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- SECOND ROW -->

    <div class="row mt-4">

        <!-- RECENT APPLICATIONS -->

        <div class="col-lg-8">

            <div class="card">

                <div class="card-body">

                    <h4 class="mb-4">

                        Recent Applications

                    </h4>

                    <div class="table-responsive">

                        <table class="table table-hover align-middle">

                            <thead>

                                <tr>

                                    <th>ID</th>

                                    <th>Status</th>

                                    <th>Submitted At</th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($recentApps as $app): ?>

                                    <tr>

                                        <td>

                                            #<?= e($app['id']) ?>

                                        </td>

                                        <td>

                                            <span class="badge bg-primary">

                                                <?= e($app['status']) ?>

                                            </span>

                                        </td>

                                        <td>

                                            <?= e($app['submitted_at']) ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

        <!-- SYSTEM STATUS -->

        <div class="col-lg-4">

            <div class="card">

                <div class="card-body">

                    <h4 class="mb-4">

                        System Status

                    </h4>

                    <div class="mb-3">

                        <strong>

                            Database:

                        </strong>

                        <span class="badge bg-success">

                            Online

                        </span>

                    </div>

                    <div class="mb-3">

                        <strong>

                            Server:

                        </strong>

                        <span class="badge bg-primary">

                            Running

                        </span>

                    </div>

                    <div class="mb-3">

                        <strong>

                            Authentication:

                        </strong>

                        <span class="badge bg-success">

                            Active

                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- QUICK ACTIONS -->

    <div class="card mt-4">

        <div class="card-body">

            <h4 class="mb-4">

                Quick Actions

            </h4>

            <div class="d-flex gap-3 flex-wrap">

                <a
                    href="users/index.php"
                    class="btn btn-primary"
                >
                    Manage Users
                </a>

                <a
                    href="applications/index.php"
                    class="btn btn-success"
                >
                    Applications
                </a>

                <a
                    href="evaluation_scores/index.php"
                    class="btn btn-warning"
                >
                    Evaluation Scores
                </a>

                <a
                    href="notifications/index.php"
                    class="btn btn-danger"
                >
                    Notifications
                </a>

            </div>

        </div>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>