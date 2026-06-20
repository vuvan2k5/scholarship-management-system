<?php
$pageTitle = 'Notifications';

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/comm_helper.php';

requireLogin();
requireRole('student');

$pdo    = getDB();
$userId = currentUserId();

ensureCommTables($pdo);

// ── Mark single as read ────────────────────────────────────────
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")
        ->execute([(int)$_GET['mark_read'], $userId]);
    header('Location: notifications.php?type=' . urlencode($_GET['type'] ?? '') . '&read_status=' . urlencode($_GET['read_status'] ?? ''));
    exit;
}

// ── Mark all as read ───────────────────────────────────────────
if (isset($_GET['mark_all'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")
        ->execute([$userId]);
    setFlash('success', 'All notifications marked as read.');
    header('Location: notifications.php');
    exit;
}

// ── Filter ─────────────────────────────────────────────────────
$filterType = $_GET['type'] ?? '';
$filterRead = $_GET['read_status'] ?? '';

$where  = "WHERE user_id = ?";
$params = [$userId];
if ($filterType !== '') { $where .= " AND type = ?"; $params[] = $filterType; }
if ($filterRead === 'unread') { $where .= " AND is_read = 0"; }
if ($filterRead === 'read')   { $where .= " AND is_read = 1"; }

$notifications = $pdo->prepare("
    SELECT * FROM notifications $where ORDER BY created_at DESC
");
$notifications->execute($params);
$notifications = $notifications->fetchAll();

$unreadCount = (int)$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0")
    ->execute([$userId]) ? 0 : 0; // placeholder, re-query below
$stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$stmtUnread->execute([$userId]);
$unreadCount = (int)$stmtUnread->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
//require_once __DIR__ . '/../includes/navbar.php';
?>
<?php require_once __DIR__ . '/../includes/student_header.php'; ?>
<style>
.student-page{
    max-width:1400px;
    margin:0 auto;
    padding:32px;
}

.page-header{
    margin-bottom:32px;
}

.page-title{
    font-size:48px;
    font-weight:800;
    color:#0F172A;
    margin-bottom:10px;
    line-height:1.1;
}

.page-subtitle{
    font-size:18px;
    color:#64748B;
    line-height:1.6;
}

.filter-card{
    margin-bottom:24px;
}
</style>

<div class="student-page">

<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">
      <i class="bi bi-bell me-2 text-primary"></i>Notifications
      <?php if ($unreadCount > 0): ?>
        <span style="font-size:14px;background:#2563eb;color:#fff;border-radius:20px;padding:3px 10px;margin-left:8px;vertical-align:middle;">
          <?= $unreadCount ?> new
        </span>
      <?php endif; ?>
    </h1>
    <p class="page-subtitle">System updates and application status notifications.</p>
  </div>
  <?php if ($unreadCount > 0): ?>
  <a href="?mark_all=1" class="btn btn-secondary">
    <i class="bi bi-check2-all"></i> Mark All as Read
  </a>
  <?php endif; ?>
</div>

<?php showFlash(); ?>

<!-- Filter bar -->
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 20px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
  <span style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em;">Filter:</span>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <?php
    $typeFilters = [
      '' => 'All Types', 'info' => 'Info', 'success' => 'Success',
      'warning' => 'Warning', 'error' => 'Error',
    ];
    foreach ($typeFilters as $val => $label):
      $active = ($filterType === $val);
    ?>
    <a href="?type=<?= $val ?>&read_status=<?= $filterRead ?>"
       style="padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;
              background:<?= $active ? '#2563eb' : '#f1f5f9' ?>;color:<?= $active ? '#fff' : '#475569' ?>;">
      <?= $label ?>
    </a>
    <?php endforeach; ?>
  </div>
  <div style="width:1px;height:20px;background:#e2e8f0;"></div>
  <div style="display:flex;gap:8px;">
    <?php foreach (['' => 'All', 'unread' => 'Unread', 'read' => 'Read'] as $val => $label):
      $active = ($filterRead === $val);
    ?>
    <a href="?type=<?= $filterType ?>&read_status=<?= $val ?>"
       style="padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;
              background:<?= $active ? '#0f172a' : '#f1f5f9' ?>;color:<?= $active ? '#fff' : '#475569' ?>;">
      <?= $label ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (empty($notifications)): ?>
  <div class="card">
    <div class="card-body">
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bi bi-bell-slash"></i></div>
        <div class="empty-state-title">No Notifications</div>
        <div class="empty-state-text">You currently have no notifications matching the filter.</div>
      </div>
    </div>
  </div>
<?php else: ?>

  <div style="display:flex;flex-direction:column;gap:10px;">
    <?php foreach ($notifications as $note):
      $typeConfig = [
        'success' => ['#f0fdf4','#16a34a','bi-check-circle-fill','Success'],
        'error'   => ['#fef2f2','#dc2626','bi-x-circle-fill','Error'],
        'warning' => ['#fffbeb','#d97706','bi-exclamation-triangle-fill','Warning'],
        'info'    => ['#eff6ff','#2563eb','bi-info-circle-fill','Info'],
      ];
      $tc = $typeConfig[$note['type']] ?? $typeConfig['info'];
      $isUnread = !$note['is_read'];
      $threadRootId = resolveNotificationThreadRoot($pdo, $userId, $note);
      $markUrl  = '?mark_read=' . $note['id']
                . '&type='        . urlencode($filterType)
                . '&read_status=' . urlencode($filterRead);
      if ($threadRootId) {
          $noteUrl = 'message_view.php?id=' . $threadRootId
                   . '&notif_id=' . (int)$note['id']
                   . '&type=' . urlencode($filterType)
                   . '&read_status=' . urlencode($filterRead);
      } else {
          $noteUrl = $isUnread ? $markUrl : '#';
      }
      $isClickable = $threadRootId || $isUnread;
    ?>
    <a href="<?= $noteUrl ?>"
       style="display:flex;gap:14px;align-items:flex-start;
              background:<?= $isUnread ? '#fff' : '#f8fafc' ?>;
              border:1.5px solid <?= $isUnread ? $tc[1] . '40' : '#e2e8f0' ?>;
              border-radius:12px;padding:16px 20px;
              text-decoration:none;
              transition:all .2s;
              <?= $isUnread ? 'box-shadow:0 2px 8px rgba(0,0,0,.06);' : '' ?>
              cursor:<?= $isClickable ? 'pointer' : 'default' ?>;"
       <?= $isClickable ? '' : 'onclick="return false;"' ?>>

      <!-- Icon -->
      <div style="width:40px;height:40px;border-radius:10px;
                  background:<?= $tc[0] ?>;
                  display:flex;align-items:center;justify-content:center;
                  font-size:18px;color:<?= $tc[1] ?>;flex-shrink:0;">
        <i class="bi <?= $tc[2] ?>"></i>
      </div>

      <!-- Content -->
      <div style="flex:1;min-width:0;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:4px;">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span style="font-size:14px;font-weight:<?= $isUnread ? '700' : '600' ?>;color:#0f172a;">
              <?= e($note['title']) ?>
            </span>
            <?php if ($isUnread): ?>
              <span style="width:7px;height:7px;border-radius:50%;
                           background:#2563eb;display:inline-block;flex-shrink:0;"></span>
            <?php endif; ?>
            <span style="background:<?= $tc[0] ?>;color:<?= $tc[1] ?>;
                         border-radius:6px;padding:2px 8px;
                         font-size:10.5px;font-weight:700;text-transform:uppercase;">
              <?= $tc[3] ?>
            </span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
            <span style="font-size:11.5px;color:#94a3b8;white-space:nowrap;">
              <?= date('M d, H:i', strtotime($note['created_at'])) ?>
            </span>
            <?php if ($isUnread): ?>
              <span style="color:#94a3b8;font-size:13px;" title="Nhấn để đánh dấu đã đọc">
                <i class="bi bi-check2"></i>
              </span>
            <?php else: ?>
              <span style="color:#c8d5e0;font-size:13px;" title="Đã đọc">
                <i class="bi bi-check2-all"></i>
              </span>
            <?php endif; ?>
          </div>
        </div>
        <p style="font-size:13.5px;color:<?= $isUnread ? '#334155' : '#64748b' ?>;margin:0;line-height:1.6;">
          <?= e($note['message']) ?>
        </p>
        <?php if ($threadRootId): ?>
        <div style="font-size:11.5px;color:#94a3b8;margin-top:6px;">
          <i class="bi bi-chat-dots me-1"></i>Open conversation
        </div>
        <?php elseif ($isUnread): ?>
        <div style="font-size:11.5px;color:#94a3b8;margin-top:6px;">
          <i class="bi bi-cursor me-1"></i>Nhấn để đánh dấu đã đọc
        </div>
        <?php endif; ?>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>