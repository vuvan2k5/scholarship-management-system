<?php
$role = currentRole();
?>

<div class="d-flex">

    <!-- SIDEBAR -->

    <div
        class="sidebar d-flex flex-column flex-shrink-0 p-3 text-white"
        style="
            width: 260px;
            min-height: 100vh;
            background: linear-gradient(
                180deg,
                #0f172a,
                #1e293b
            );
            position: fixed;
            left: 0;
            top: 0;
        "
    >

        <a
            href="<?= BASE_URL ?>/index.php"
            class="d-flex align-items-center mb-4 text-white text-decoration-none"
        >

            <span class="fs-3 fw-bold">

                🎓 Scholarship

            </span>

        </a>

        <hr class="text-secondary">

        <ul class="nav nav-pills flex-column mb-auto">

            <?php if ($role === 'admin'): ?>

                <li class="nav-item mb-2">

                    <a
                        href="<?= BASE_URL ?>/admin/dashboard.php"
                        class="nav-link text-white"
                    >
                        📊 Dashboard
                    </a>

                </li>

                <li class="mb-2">

                    <a
                        href="<?= BASE_URL ?>/admin/users/index.php"
                        class="nav-link text-white"
                    >
                        👤 Users
                    </a>

                </li>

                <li class="mb-2">

                    <a
                        href="<?= BASE_URL ?>/admin/applications/index.php"
                        class="nav-link text-white"
                    >
                        📂 Applications
                    </a>

                </li>

                <li class="mb-2">

                    <a
                        href="<?= BASE_URL ?>/admin/student_profiles/index.php"
                        class="nav-link text-white"
                    >
                        🎓 Students
                    </a>

                </li>

                <li class="mb-2">

                    <a
                        href="<?= BASE_URL ?>/admin/evaluation_scores/index.php"
                        class="nav-link text-white"
                    >
                        ⭐ Evaluation
                    </a>

                </li>

                <li class="mb-2">

                    <a
                        href="<?= BASE_URL ?>/admin/notifications/index.php"
                        class="nav-link text-white"
                    >
                        🔔 Notifications
                    </a>

                </li>

            <?php endif; ?>

        </ul>

        <hr class="text-secondary">

        <div>

            <div class="mb-2">

                👤 <?= e(currentUserName()) ?>

            </div>

            <span class="badge bg-primary">

                <?= e($role) ?>

            </span>

            <div class="mt-3">

                <a
                    href="<?= BASE_URL ?>/logout.php"
                    class="btn btn-warning btn-sm w-100"
                >
                    Logout
                </a>

            </div>

        </div>

    </div>

    <!-- MAIN CONTENT -->

    <div
        style="
            margin-left: 260px;
            width: calc(100% - 260px);
            min-height: 100vh;
        "
    >