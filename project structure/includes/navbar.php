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
<?php
// Add role-specific class to <body> for scoped CSS theming
$bodyRoleClass = '';
if ($role === 'student')  $bodyRoleClass = 'role-student';
elseif ($role === 'admin') $bodyRoleClass = 'role-admin';
elseif ($role === 'reviewer') $bodyRoleClass = 'role-reviewer';
if ($bodyRoleClass) {
    echo '<script>document.body.classList.add(' . json_encode($bodyRoleClass) . ');</script>';
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

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/admin/management_mail/index.php"
             class="nav-link <?= navActive('/admin/management_mail/') ?>">
            <i class="bi bi-envelope-paper"></i> Management Mail
            <?php
              if (function_exists('getDB')) {
                try {
                  $pdo3 = getDB();
                  $chk  = $pdo3->query("SHOW TABLES LIKE 'mail_log'");
                  if ($chk && $chk->rowCount() > 0) {
                    $mc = (int)$pdo3->query("SELECT COUNT(*) FROM mail_log WHERE status='pending'")->fetchColumn();
                    if ($mc > 0) echo "<span style='margin-left:auto;background:#f59e0b;color:#fff;border-radius:10px;padding:1px 7px;font-size:10px;font-weight:700;'>$mc</span>";
                  }
                } catch(Exception $e) {}
              }
            ?>
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
          <a href="<?= BASE_URL ?>/student/scholarships.php"
             class="nav-link <?= navPageActive('scholarships.php') ?>">
            <i class="bi bi-award"></i> Scholarships
          </a>
        </li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/student/apply.php"
             class="nav-link <?= navPageActive('apply.php') ?>">
            <i class="bi bi-file-earmark-plus"></i> Apply
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
            <?php
              // Unread count badge
              if (function_exists('currentUserId') && currentUserId()) {
                try {
                  $pdo2 = getDB();
                  $uc = $pdo2->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
                  $uc->execute([currentUserId()]);
                  $unc = (int)$uc->fetchColumn();
                  if ($unc > 0) echo "<span style='margin-left:auto;background:#ef4444;color:#fff;border-radius:10px;padding:1px 7px;font-size:10px;font-weight:700;'>$unc</span>";
                } catch(Exception $e) {}
              }
            ?>
          </a>
        </li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/student/ask_admin.php"
             class="nav-link <?= navPageActive('ask_admin.php') ?>">
            <i class="bi bi-chat-dots"></i> Ask Admin
          </a>
        </li>
        <li class="nav-item">
          <a href="<?= BASE_URL ?>/student/profile.php"
             class="nav-link <?= navPageActive('profile.php') ?>">
            <i class="bi bi-person-gear"></i> My Profile
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

    <header class="topbar" style="position:relative;">
      <div class="topbar-left" style="display:flex;align-items:center;gap:12px;min-width:180px;">
        <button class="btn btn-sm btn-secondary d-lg-none" id="sidebarToggle" style="padding:6px 10px;">
          <i class="bi bi-list fs-5"></i>
        </button>
        <a href="<?= BASE_URL ?>/index.php" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit">
          <div style="font-size:20px;line-height:1">🎓</div>
          <div style="display:flex;flex-direction:column;line-height:1">
            <span style="font-weight:700;">Scholarship</span>
            <small style="opacity:.7;font-weight:600">Management System</small>
          </div>
        </a>
      </div>

      <?php if ($role === 'student'): ?>
        <nav class="student-topnav" aria-label="Student navigation" style="flex:1;display:flex;justify-content:center;align-items:center;">
          <ul style="display:flex;align-items:center;gap:44px;list-style:none;margin:0;padding:0;">
            <li><a href="<?= BASE_URL ?>/student/dashboard.php" class="topnav-link <?= navPageActive('dashboard.php') ?>">Dashboard</a></li>
            <li><a href="<?= BASE_URL ?>/programs.php" class="topnav-link <?= navPageActive('programs.php') ?>">Scholarship Programs</a></li>
            <li><a href="<?= BASE_URL ?>/student/apply.php" class="topnav-link <?= navPageActive('apply.php') ?>">Apply</a></li>
            <li><a href="<?= BASE_URL ?>/student/my_applications.php" class="topnav-link <?= ($currentPage === 'my_applications.php' || $currentPage === 'application_details.php') ? 'active' : '' ?>">My Applications</a></li>
            <li><a href="<?= BASE_URL ?>/student/my_results.php" class="topnav-link <?= navPageActive('my_results.php') ?>">Results</a></li>
            <li>
              <a href="<?= BASE_URL ?>/student/notifications.php" class="topnav-link <?= navPageActive('notifications.php') ?>">
                Notifications
                <?php
                  if (function_exists('currentUserId') && currentUserId()) {
                    try {
                      $pdo2 = getDB();
                      $uc = $pdo2->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
                      $uc->execute([currentUserId()]);
                      $unc = (int)$uc->fetchColumn();
                      if ($unc > 0) echo "<span class='topnav-badge'>" . $unc . "</span>";
                    } catch(Exception $e) {}
                  }
                ?>
              </a>
            </li>
            <li><a href="<?= BASE_URL ?>/student/profile.php" class="topnav-link <?= navPageActive('profile.php') ?>">My Profile</a></li>
          </ul>
        </nav>
      <?php else: ?>
        <div style="flex:1"></div>
      <?php endif; ?>

      <div class="topbar-right" style="display:flex;align-items:center;gap:12px;min-width:200px;justify-content:flex-end;">
        <?php if ($role === 'admin' && function_exists('getDB') && function_exists('currentUserId')): ?>
          <?php
            $adminNotifCount = 0;
            try {
              $pdo_nb  = getDB();
              $uid_nb  = currentUserId();
              if ($uid_nb) {
                $stmt_nb = $pdo_nb->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                $stmt_nb->execute([$uid_nb]);
                $adminNotifCount = (int)$stmt_nb->fetchColumn();
              }
            } catch (Exception $e) {}
          ?>
          <a href="<?= BASE_URL ?>/admin/communication_center/index.php"
             title="Notifications"
             style="position:relative;display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);color:rgba(255,255,255,.75);text-decoration:none;transition:background .2s;"
             onmouseover="this.style.background='rgba(255,255,255,.13)'"
             onmouseout="this.style.background='rgba(255,255,255,.06)'>
            <span style="font-size:18px;">🔔</span>
            <?php if ($adminNotifCount > 0): ?>
              <span style="position:absolute;top:-5px;right:-5px;background:#ef4444;color:#fff;border-radius:10px;padding:1px 5px;font-size:10px;font-weight:700;line-height:1.4;min-width:16px;text-align:center;"><?= $adminNotifCount ?></span>
            <?php endif; ?>
          </a>
        <?php endif; ?>
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

      <style>
        /* Student topnav visuals */
        .student-topnav .topnav-link {
          display:inline-flex;align-items:center;gap:8px;padding:8px 6px;border-radius:8px;color:inherit;text-decoration:none;font-size:16px;font-weight:600;box-sizing:border-box;transition:background .12s,color .12s;
        }
        .student-topnav .topnav-link .topnav-badge{margin-left:8px}
        .student-topnav .topnav-badge{background:#ef4444;color:#fff;border-radius:10px;padding:1px 6px;font-size:12px;font-weight:700;}
        .student-topnav .topnav-link.active,
        .student-topnav .topnav-link.active:focus{
          background:rgba(99,102,241,.12);color:rgb(79,70,229);font-weight:700;outline:0;
        }
        /* Ensure active state doesn't change layout width */
        .student-topnav ul li{min-width:0}

        /* Hide the student items in the sidebar on large screens to avoid duplicate left-aligned menu */
        @media(min-width:992px){
          body.role-student .sidebar { width:72px; }
          body.role-student .sidebar .nav-section-label, body.role-student .sidebar .nav-item { display:none; }
          body.role-student .sidebar .sidebar-brand{display:flex;justify-content:center}
        }
      </style>
    </header>

    <div class="page-body"></div>