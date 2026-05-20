<?php

$role = currentRole();

$currentPage = basename($_SERVER['PHP_SELF']);

?>

<div class="d-flex">

    <!-- SIDEBAR -->

    <div class="sidebar d-flex flex-column flex-shrink-0 p-3 text-white">

        <!-- LOGO -->

        <a
            href="<?= BASE_URL ?>/index.php"
            class="
                d-flex
                align-items-center
                mb-4
                text-white
                text-decoration-none
            "
        >

            <span class="fs-3 fw-bold">

                🎓 Scholarship

            </span>

        </a>

        <hr class="text-secondary">

        <!-- NAVIGATION -->

        <ul class="nav nav-pills flex-column mb-auto">

            <!-- ADMIN MENU -->

            <?php if ($role === 'admin'): ?>

                <li class="nav-item mb-2">

                    <a
                        href="<?= BASE_URL ?>/admin/dashboard.php"
                        class="
                            nav-link
                            <?= $currentPage === 'dashboard.php'
                                ? 'active'
                                : 'text-white'
                            ?>
                        "
                    >

                        <i class="bi bi-speedometer2 me-2"></i>

                        Dashboard

                    </a>

                </li>

                <li class="mb-2">

                    <a
                        href="<?= BASE_URL ?>/admin/users/index.php"
                        class="
                            nav-link
                            <?= $currentPage === 'index.php'
                                && str_contains(
                                    $_SERVER['REQUEST_URI'],
                                    '/users/'
                                )
                                ? 'active'
                                : 'text-white'
                            ?>
                        "
                    >

                        <i class="bi bi-people me-2"></i>

                        Users

                    </a>

                </li>

                <li class="mb-2">

                    <a
                        href="<?= BASE_URL ?>/admin/applications/index.php"
                        class="
                            nav-link
                            <?= str_contains(
                                $_SERVER['REQUEST_URI'],
                                '/applications/'
                            )
                                ? 'active'
                                : 'text-white'
                            ?>
                        "
                    >

                        <i class="bi bi-folder2-open me-2"></i>

                        Applications

                    </a>

                </li>

                <li class="mb-2">

                    <a
                        href="<?= BASE_URL ?>/admin/student_profiles/index.php"
                        class="
                            nav-link
                            <?= str_contains(
                                $_SERVER['REQUEST_URI'],
                                '/student_profiles/'
                            )
                                ? 'active'
                                : 'text-white'
                            ?>
                        "
                    >

                        <i class="bi bi-mortarboard me-2"></i>

                        Student Profiles

                    </a>

                </li>

                <li class="mb-2">

                    <a
                        href="<?= BASE_URL ?>/admin/evaluation_scores/index.php"
                        class="
                            nav-link
                            <?= str_contains(
                                $_SERVER['REQUEST_URI'],
                                '/evaluation_scores/'
                            )
                                ? 'active'
                                : 'text-white'
                            ?>
                        "
                    >

                        <i class="bi bi-star-half me-2"></i>

                        Evaluation Scores

                    </a>

                </li>

                <li class="mb-2">

                    <a
                        href="<?= BASE_URL ?>/admin/notifications/index.php"
                        class="
                            nav-link
                            <?= str_contains(
                                $_SERVER['REQUEST_URI'],
                                '/notifications/'
                            )
                                ? 'active'
                                : 'text-white'
                            ?>
                        "
                    >

                        <i class="bi bi-bell me-2"></i>

                        Notifications

                    </a>

                </li>

            <?php endif; ?>

            <!-- REVIEWER MENU -->

            <?php if ($role === 'council'): ?>

                <li class="nav-item mb-2">

                    <a
                        href="<?= BASE_URL ?>/reviewer/dashboard.php"
                        class="
                            nav-link
                            <?= $currentPage === 'dashboard.php'
                                ? 'active'
                                : 'text-white'
                            ?>
                        "
                    >

                        <i class="bi bi-speedometer2 me-2"></i>

                        Dashboard

                    </a>

                </li>

                <li class="mb-2">

                    <a
                        href="<?= BASE_URL ?>/admin/applications/index.php"
                        class="
                            nav-link
                            <?= str_contains(
                                $_SERVER['REQUEST_URI'],
                                '/applications/'
                            )
                                ? 'active'
                                : 'text-white'
                            ?>
                        "
                    >

                        <i class="bi bi-folder2-open me-2"></i>

                        Review Applications

                    </a>

                </li>

                <li class="mb-2">

                    <a
                        href="<?= BASE_URL ?>/admin/evaluation_scores/index.php"
                        class="
                            nav-link
                            <?= str_contains(
                                $_SERVER['REQUEST_URI'],
                                '/evaluation_scores/'
                            )
                                ? 'active'
                                : 'text-white'
                            ?>
                        "
                    >

                        <i class="bi bi-star-half me-2"></i>

                        Evaluation

                    </a>

                </li>
                <li class="mb-2">

    <a
        href="<?= BASE_URL ?>/reviewer/comments.php"
        class="
            nav-link
            <?= str_contains(
                $_SERVER['REQUEST_URI'],
                '/comments.php'
            )
                ? 'active'
                : 'text-white'
            ?>
        "
    >

        <i class="bi bi-chat-left-text me-2"></i>

        Comments

    </a>

</li>

            <?php endif; ?>

            <!-- STUDENT MENU -->

            <?php if ($role === 'student'): ?>

                <li class="nav-item mb-2">

                    <a
                        href="<?= BASE_URL ?>/student/dashboard.php"
                        class="
                            nav-link
                            <?= $currentPage === 'dashboard.php'
                                ? 'active'
                                : 'text-white'
                            ?>
                        "
                    >

                        <i class="bi bi-speedometer2 me-2"></i>

                        Dashboard

                    </a>

                </li>

                <li class="mb-2">

                    <a
                        href="<?= BASE_URL ?>/student/apply.php"
                        class="
                            nav-link
                            <?= $currentPage === 'apply.php'
                                ? 'active'
                                : 'text-white'
                            ?>
                        "
                    >

                        <i class="bi bi-file-earmark-plus me-2"></i>

                        Apply Scholarship

                    </a>

                </li>

                <li class="mb-2">

                    <a
                        href="<?= BASE_URL ?>/student/my_applications.php"
                        class="
                            nav-link
                            <?= $currentPage === 'my_applications.php'
                                ? 'active'
                                : 'text-white'
                            ?>
                        "
                    >

                        <i class="bi bi-folder-check me-2"></i>

                        My Applications

                    </a>

                </li>

                <li class="mb-2">

                    <a
                        href="<?= BASE_URL ?>/student/my_results.php"
                        class="
                            nav-link
                            <?= $currentPage === 'my_results.php'
                                ? 'active'
                                : 'text-white'
                            ?>
                        "
                    >

                        <i class="bi bi-trophy me-2"></i>

                        Results

                    </a>

                </li>

            <?php endif; ?>

        </ul>

        <!-- FOOTER -->

        <hr class="text-secondary">

        <div>

            <div class="mb-2">

                <i class="bi bi-person-circle me-2"></i>

                <?= e(currentUserName()) ?>

            </div>

            <span class="badge bg-primary">

                <?= strtoupper(e($role)) ?>

            </span>

            <div class="mt-3">

                <a
                    href="<?= BASE_URL ?>/logout.php"
                    class="btn btn-warning btn-sm w-100"
                >

                    <i class="bi bi-box-arrow-right me-2"></i>

                    Logout

                </a>

            </div>

        </div>

    </div>

    <!-- MAIN CONTENT -->

    <div class="main-content">