<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$unreadNotifications = $unreadNotifications ?? 0;

// Dynamic check for unread notifications count if DB connection is active
if (isset($pdo) && function_exists('currentUserId')) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([currentUserId()]);
        $unreadNotifications = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        // Fallback
    }
}

// User details
$fullName = $_SESSION['user_name'] ?? 'Student';
$words = explode(' ', trim($fullName));
$initials = '';
if (count($words) >= 2) {
    $initials = mb_strtoupper(mb_substr($words[0], 0, 1, 'UTF-8') . mb_substr(end($words), 0, 1, 'UTF-8'), 'UTF-8');
} else {
    $initials = mb_strtoupper(mb_substr($fullName, 0, 2, 'UTF-8'), 'UTF-8');
}
?>

<style>
/* ── MODERN USER ACCOUNT ACTIONS ── */
.stu-header-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}

.stu-notif-bell {
  position: relative;
  color: rgba(255, 255, 255, 0.85);
  font-size: 20px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: color 0.2s ease, transform 0.2s ease;
  text-decoration: none;
}
.stu-notif-bell:hover {
  color: #fff;
  transform: scale(1.08);
}

.stu-notif-count {
  position: absolute;
  top: -6px;
  right: -6px;
  background: #ef4444;
  color: #fff;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  height: 18px;
  min-width: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
  border: 2px solid #0d1b3e;
  line-height: 1;
}

.stu-user-dropdown {
  position: relative;
}

.stu-user-trigger {
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 99px;
  transition: background-color 0.25s ease;
  user-select: none;
}
.stu-user-trigger:hover {
  background-color: rgba(255, 255, 255, 0.08);
}

.stu-user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #2563eb;
  color: #fff;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14.5px;
  letter-spacing: 0.5px;
  border: 2px solid rgba(255, 255, 255, 0.2);
  flex-shrink: 0;
  text-transform: uppercase;
}

.stu-user-meta {
  display: flex;
  flex-direction: column;
  line-height: 1.25;
}

.stu-user-fullname {
  color: #fff;
  font-weight: 600;
  font-size: 14px;
}

.stu-user-label {
  color: rgba(255, 255, 255, 0.6);
  font-size: 11px;
  font-weight: 500;
}

.stu-user-chevron {
  color: rgba(255, 255, 255, 0.7);
  font-size: 12px;
  transition: transform 0.25s ease;
}
.stu-user-dropdown.show .stu-user-chevron {
  transform: rotate(180deg);
}

/* ── Dropdown Menu Card ── */
.stu-dropdown-card {
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 10px;
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
  width: 230px;
  opacity: 0;
  visibility: hidden;
  transform: translateY(-12px);
  transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s;
  z-index: 1050;
  padding: 8px 0;
  overflow: hidden;
}

.stu-user-dropdown.show .stu-dropdown-card {
  opacity: 1;
  visibility: visible;
  transform: translateY(0);
}

.stu-dropdown-card-header {
  padding: 10px 18px 12px;
  border-bottom: 1px solid #f1f5f9;
  margin-bottom: 6px;
}

.stu-dropdown-card-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 18px;
  color: #334155 !important;
  text-decoration: none;
  font-size: 13.5px;
  font-weight: 500;
  transition: background-color 0.15s ease, color 0.15s ease;
}
.stu-dropdown-card-item:hover {
  background-color: #f8fafc;
  color: #2563eb !important;
}

.stu-dropdown-card-item.text-danger {
  color: #ef4444 !important;
}
.stu-dropdown-card-item.text-danger:hover {
  background-color: #fef2f2;
  color: #dc2626 !important;
}

.stu-dropdown-card-item i {
  font-size: 16px;
  display: inline-flex;
}

.stu-dropdown-card-divider {
  height: 1px;
  background-color: #f1f5f9;
  margin: 6px 0;
}

/* ── Mobile Responsive Overrides ── */
@media (max-width: 767px) {
  .stu-user-meta, .stu-user-chevron {
    display: none !important;
  }
  .stu-user-trigger {
    padding: 0;
  }
  .stu-dropdown-card {
    position: fixed;
    top: 60px;
    right: 16px;
    width: 210px;
  }
}
</style>

<div class="stu-header">

  <!-- TOP BAR: brand + modern right user account section -->
  <div class="stu-header-top">
    <a href="dashboard.php" class="stu-brand">
      <div class="stu-brand-icon">🎓</div>
      <div>
        <div class="stu-brand-text">Scholarship</div>
        <div class="stu-brand-sub">Management System</div>
      </div>
    </a>
    
    <div class="stu-header-actions">
      <!-- Notification Bell -->
      <a href="notifications.php" class="stu-notif-bell" title="Notifications">
        <i class="bi bi-bell"></i>
        <?php if ($unreadNotifications > 0): ?>
          <span class="stu-notif-count"><?= $unreadNotifications ?></span>
        <?php endif; ?>
      </a>

      <!-- User Profile Dropdown -->
      <div class="stu-user-dropdown" id="stuUserDropdown">
        <div class="stu-user-trigger" id="stuUserTrigger">
          <div class="stu-user-avatar">
            <?= e($initials) ?>
          </div>
          <div class="stu-user-meta">
            <span class="stu-user-fullname"><?= e($fullName) ?></span>
            <span class="stu-user-label">Student</span>
          </div>
          <i class="bi bi-chevron-down stu-user-chevron"></i>
        </div>

        <!-- Dropdown Menu Card -->
        <div class="stu-dropdown-card" id="stuDropdownCard">
          <div class="stu-dropdown-card-header d-md-none">
            <div class="fw-bold text-dark text-truncate" style="max-width: 170px;"><?= e($fullName) ?></div>
            <div class="text-muted small">Student</div>
          </div>
          <a href="profile.php" class="stu-dropdown-card-item">
            <i class="bi bi-person-circle"></i> My Profile
          </a>
          <a href="my_applications.php" class="stu-dropdown-card-item">
            <i class="bi bi-folder-check"></i> My Applications
          </a>
          <a href="notifications.php" class="stu-dropdown-card-item">
            <i class="bi bi-bell"></i> Notifications
          </a>
          <a href="ask_admin.php" class="stu-dropdown-card-item">
            <i class="bi bi-chat-dots"></i> Ask Admin
          </a>
          <div class="stu-dropdown-card-divider"></div>
          <a href="<?= BASE_URL ?>/logout.php" class="stu-dropdown-card-item text-danger">
            <i class="bi bi-box-arrow-right"></i> Logout
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- NAV BAR: bright blue — navigation links -->
  <nav class="stu-nav" aria-label="Student navigation">
    <?php
    $currentPage = basename($_SERVER['PHP_SELF']);
    function stuActive(string $page): string {
        global $currentPage;
        return $currentPage === $page ? 'active' : '';
    }
    ?>
    <a href="dashboard.php" class="stu-nav-link <?= stuActive('dashboard.php') ?>">
      <i class="bi bi-speedometer2"></i>
      <span class="nav-label">Dashboard</span>
    </a>
    <a href="scholarships.php" class="stu-nav-link <?= stuActive('scholarships.php') ?>">
      <i class="bi bi-stars"></i>
      <span class="nav-label">Scholarship Programs</span>
    </a>
    <a href="apply.php" class="stu-nav-link <?= stuActive('apply.php') ?>">
      <i class="bi bi-file-earmark-plus"></i>
      <span class="nav-label">Apply</span>
    </a>
    <a href="my_applications.php" class="stu-nav-link <?= ($currentPage === 'my_applications.php' || $currentPage === 'application_details.php') ? 'active' : '' ?>">
      <i class="bi bi-folder-check"></i>
      <span class="nav-label">My Applications</span>
    </a>
    <a href="my_results.php" class="stu-nav-link <?= stuActive('my_results.php') ?>">
      <i class="bi bi-trophy"></i>
      <span class="nav-label">Results</span>
    </a>
    <a href="notifications.php" class="stu-nav-link <?= stuActive('notifications.php') ?>">
      <i class="bi bi-bell"></i>
      <span class="nav-label">Notifications</span>
      <?php if ($unreadNotifications > 0): ?>
        <span class="stu-nav-badge"><?= $unreadNotifications ?></span>
      <?php endif; ?>
    </a>
    <a href="profile.php" class="stu-nav-link <?= stuActive('profile.php') ?>">
      <i class="bi bi-person-gear"></i>
      <span class="nav-label">My Profile</span>
    </a>
  </nav>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const trigger = document.getElementById('stuUserTrigger');
  const dropdown = document.getElementById('stuUserDropdown');

  if (trigger && dropdown) {
    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      dropdown.classList.toggle('show');
    });

    document.addEventListener('click', (e) => {
      if (!dropdown.contains(e.target)) {
        dropdown.classList.remove('show');
      }
    });
  }
});
</script>