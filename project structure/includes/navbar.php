<?php
// ============================================================
// includes/navbar.php  –  Sidebar + layout shell open
// ============================================================

$role        = currentRole();
$currentPage = basename($_SERVER['PHP_SELF']);
$currentUri  = $_SERVER['REQUEST_URI'];

if (!function_exists('navActive')) {
    function navActive(string $segment): string {
        global $currentUri;
        return strpos($currentUri, $segment) !== false ? 'active' : '';
    }
}

if (!function_exists('navPageActive')) {
    function navPageActive(string $page): string {
        global $currentPage;
        return $currentPage === $page ? 'active' : '';
    }
}
?>

<div class="app-shell">

  <aside class="sidebar" id="sidebar">

    <a href="<?= BASE_URL ?>/index.php" class="sidebar-brand">
      <div class="sidebar-brand-icon">🎓</div>
      <div>
        <div class="sidebar-brand-text">Scholarship</div>
        <div class="sidebar-brand-sub">Management System</div>
      </div>
    </a>

    <ul class="sidebar-nav">

      <?php if ($role === 'admin'): ?>

        <li class="nav-section-label">System Control</li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/dashboard.php"
             class="nav-link <?= navActive('/admin/dashboard') ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/users/index.php"
             class="nav-link <?= navActive('/admin/users/') ?>">
            <i class="bi bi-people"></i> Users
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/student_profiles/index.php"
             class="nav-link <?= navActive('/admin/student_profiles/') ?>">
            <i class="bi bi-mortarboard"></i> Student Profiles
          </a>
        </li>

        <li class="nav-section-label">Scholarship Config</li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/scholarship_programs/index.php"
             class="nav-link <?= navActive('/admin/scholarship_programs/') ?>">
            <i class="bi bi-award"></i> Programs
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/eligibility_rules/index.php"
             class="nav-link <?= navActive('/admin/eligibility_rules/') ?>">
            <i class="bi bi-check2-circle"></i> Eligibility Rules
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/scoring_criteria/index.php"
             class="nav-link <?= navActive('/admin/scoring_criteria/') ?>">
            <i class="bi bi-list-stars"></i> Scoring Criteria
          </a>
        </li>

        <li class="nav-section-label">Process & Evaluation</li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/applications/index.php"
             class="nav-link <?= navActive('/admin/applications/') ?>">
            <i class="bi bi-folder2-open"></i> Applications
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/eligibility_engine/index.php"
             class="nav-link <?= navActive('/admin/eligibility_engine/') ?>">
            <i class="bi bi-cpu"></i> Eligibility Engine
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/eligibility_results/index.php"
             class="nav-link <?= navActive('/admin/eligibility_results/') ?>">
            <i class="bi bi-clipboard-check"></i> Eligibility Results
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/evaluation_scores/index.php"
             class="nav-link <?= navActive('/admin/evaluation_scores/') ?>">
            <i class="bi bi-star-half"></i> Evaluation Scores
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/ranking_results/index.php"
             class="nav-link <?= navActive('/admin/ranking_results/') ?>">
            <i class="bi bi-bar-chart-line"></i> Ranking Results
          </a>
        </li>

        <li class="nav-section-label">Finance & Reporting</li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/award_certificates/index.php"
             class="nav-link <?= navActive('/admin/award_certificates/') ?>">
            <i class="bi bi-patch-check"></i> Certificates
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/disbursements/index.php"
             class="nav-link <?= navActive('/admin/disbursements/') ?>">
            <i class="bi bi-cash-coin"></i> Disbursements
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/reports/index.php"
             class="nav-link <?= navActive('/admin/reports/') ?>">
            <i class="bi bi-graph-up"></i> Reports
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/notifications/index.php"
             class="nav-link <?= navActive('/admin/notifications/') ?>">
            <i class="bi bi-bell"></i> Notifications
          </a>
        </li>

      <?php elseif ($role === 'reviewer'): ?>

        <li class="nav-section-label">Reviewer Panel</li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/reviewer/dashboard.php"
             class="nav-link <?= navPageActive('dashboard.php') ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/reviewer/evidence_verification.php"
             class="nav-link <?= navPageActive('evidence_verification.php') ?>">
            <i class="bi bi-shield-check"></i> Evidence Verification
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/reviewer/evaluation_scores.php"
             class="nav-link <?= navPageActive('evaluation_scores.php') ?>">
            <i class="bi bi-star"></i> Evaluation Scores
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/reviewer/recommendations.php"
             class="nav-link <?= navPageActive('recommendations.php') ?>">
            <i class="bi bi-lightbulb"></i> Recommendations
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/reviewer/review_analytics.php"
             class="nav-link <?= navPageActive('review_analytics.php') ?>">
            <i class="bi bi-bar-chart"></i> Review Analytics
          </a>
        </li>

      <?php elseif ($role === 'student'): ?>

        <li class="nav-section-label">My Portal</li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/student/dashboard.php"
             class="nav-link <?= navPageActive('dashboard.php') ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
          </a>
        </li>
         <li class="nav-item">
          <a href="<?= BASE_URL ?>/programs.php"
             class="nav-link <?= navPageActive('programs.php') ?>">
            <i class="bi bi-award"></i> Scholarship Programs
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/student/apply.php"
             class="nav-link <?= navPageActive('apply.php') ?>">
            <i class="bi bi-file-earmark-plus"></i> Apply Scholarship
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/student/my_applications.php"
             class="nav-link <?= ($currentPage === 'my_applications.php' || $currentPage === 'application_details.php') ? 'active' : '' ?>">
            <i class="bi bi-folder-check"></i> My Applications
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/student/my_results.php"
             class="nav-link <?= navPageActive('my_results.php') ?>">
            <i class="bi bi-trophy"></i> Results
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/student/notifications.php"
             class="nav-link <?= navPageActive('notifications.php') ?>">
            <i class="bi bi-bell"></i> Notifications
          </a>
        </li>

      <?php endif; ?>

    </ul>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar">
          <i class="bi bi-person-fill"></i>
        </div>
        <div style="overflow:hidden;">
          <div class="sidebar-user-name"><?= e(currentUserName()) ?></div>
          <div class="sidebar-user-role"><?= e($role) ?></div>
        </div>
      </div>

      <a href="<?= BASE_URL ?>/logout.php" class="btn btn-sm w-100"
         style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.75);border:1px solid rgba(255,255,255,.12);">
        <i class="bi bi-box-arrow-right"></i> Sign Out
      </a>
    </div>

  </aside>

  <div class="main-content">

    <header class="topbar">
      <div class="topbar-left">
        <button class="btn btn-sm btn-secondary d-lg-none" id="sidebarToggle" style="padding:6px 10px;">
          <i class="bi bi-list fs-5"></i>
        </button>
        <span class="topbar-title"><?= isset($pageTitle) ? e($pageTitle) : 'Scholarship System' ?></span>
      </div>
<<<<<<< HEAD
      <div class="topbar-right" style="display:flex;align-items:center;gap:10px;">
=======

      <div class="topbar-right">
>>>>>>> origin/mai-reviewer-role
        <span class="badge badge-<?= e($role) ?>" style="font-size:11px;padding:5px 10px;">
          <?= strtoupper(e($role)) ?>
        </span>
        <a href="<?= BASE_URL ?>/logout.php"
           class="btn btn-sm"
           style="background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.25);display:flex;align-items:center;gap:5px;font-size:13px;padding:5px 12px;border-radius:8px;text-decoration:none;transition:all .2s;"
           onmouseover="this.style.background='rgba(239,68,68,.22)'"
           onmouseout="this.style.background='rgba(239,68,68,.12)'"
           title="Đăng xuất">
          <i class="bi bi-box-arrow-right"></i>
          <span class="d-none d-sm-inline">Logout</span>
        </a>
      </div>
    </header>

    <div class="page-body"></div>