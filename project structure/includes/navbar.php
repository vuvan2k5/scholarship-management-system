<?php
// includes/navbar.php – Thanh điều hướng theo role
$role = currentRole();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3">
    <a class="navbar-brand" href="<?= BASE_URL ?>/index.php">🎓 Scholarship</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
        <ul class="navbar-nav me-auto">
            <?php if ($role === 'admin'): ?>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/applications/index.php"><i class="bi bi-folder2-open"></i> Hồ sơ</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/evaluation_scores/index.php"><i class="bi bi-star-half"></i> Chấm điểm</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/student_profiles/index.php"><i class="bi bi-person-lines-fill"></i> Sinh viên</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/notifications/index.php"><i class="bi bi-bell"></i> Thông báo</a></li>
            <?php elseif ($role === 'council'): ?>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/evaluation_scores/index.php"><i class="bi bi-star-half"></i> Chấm điểm</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/applications/index.php"><i class="bi bi-folder2-open"></i> Hồ sơ</a></li>
            <?php elseif ($role === 'student'): ?>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/student/apply.php"><i class="bi bi-plus-circle"></i> Nộp hồ sơ</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/student/my_applications.php"><i class="bi bi-list-check"></i> Hồ sơ của tôi</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/student/my_results.php"><i class="bi bi-trophy"></i> Kết quả</a></li>
            <?php endif; ?>
        </ul>
        <ul class="navbar-nav">
            <li class="nav-item">
                <span class="nav-link text-light">
                    <i class="bi bi-person-circle"></i>
                    <?= e(currentUserName()) ?>
                    <span class="badge bg-secondary ms-1"><?= e($role ?? '') ?></span>
                </span>
            </li>
            <li class="nav-item">
                <a class="nav-link text-warning" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
            </li>
        </ul>
    </div>
</nav>
