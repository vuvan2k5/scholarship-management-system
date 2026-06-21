<?php
// ============================================================
// admin/communication_center/index.php
// Communication Center -- Inbox/Outbox/History.
// Inbox merges: notifications table + messages/message_recipients.
// NO SMTP -- all internal system notifications.
// ============================================================
$pageTitle = 'Communication Center';

require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/comm_helper.php';

requireLogin();
requireRole('admin');

$pdo     = getDB();
$adminId = currentUserId();

ensureCommTables($pdo);
seedCommTemplates($pdo);

// -- Tab / filter state
$tab    = in_array($_GET['tab'] ?? 'inbox', ['inbox','outbox','history']) ? ($_GET['tab'] ?? 'inbox') : 'inbox';
$search = trim($_GET['search']  ?? '');
$roleF  = trim($_GET['role']    ?? '');
$statusF= trim($_GET['status']  ?? '');
$dateF  = trim($_GET['date']    ?? '');

// -- Handle Mark-as-read
if (isset($_GET['mark_read'])) {
    $mrid   = (int)$_GET['mark_read'];
    $src    = $_GET['src'] ?? 'message';
    if ($src === 'notification') {
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$mrid, $adminId]);
    } else {
        markMessageRead($pdo, $mrid, $adminId);
    }
    header('Location: index.php?tab=' . $tab);
    exit;
}

// -- Handle Mark-all-read
if (isset($_GET['mark_all_read'])) {
    // notifications table
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0")->execute([$adminId]);
    // messages table (safe: only if exists)
    try {
        $pdo->prepare("UPDATE messages SET is_read=1, read_at=NOW() WHERE recipient_id=? AND is_read=0")->execute([$adminId]);
        $pdo->prepare("UPDATE message_recipients SET is_read=1, read_at=NOW() WHERE recipient_id=? AND is_read=0")->execute([$adminId]);
    } catch (Exception $e) {}
    setFlash('success', 'All messages marked as read.');
    header('Location: index.php?tab=inbox');
    exit;
}

// ============================================================
// UNREAD COUNTERS
// Derived from: notifications.is_read=0  +  messages tables
// ============================================================
$unreadNotif     = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id={$adminId} AND is_read=0")->fetchColumn();
$unreadMsgDirect = 0;
$unreadMsgBcast  = 0;
$messagesTableExists = false;
try {
    $chk = $pdo->query("SHOW TABLES LIKE 'messages'");
    if ($chk && $chk->rowCount() > 0) {
        $messagesTableExists = true;
        $unreadMsgDirect = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE recipient_id={$adminId} AND is_read=0")->fetchColumn();
        $unreadMsgBcast  = (int)$pdo->query("SELECT COUNT(*) FROM message_recipients WHERE recipient_id={$adminId} AND is_read=0")->fetchColumn();
    }
} catch (Exception $e) {}

$unreadCount = $unreadNotif + $unreadMsgDirect + $unreadMsgBcast;

// ============================================================
// INBOX -- unified: notifications + messages merged
// ============================================================

// Part A: notifications
$nWhere  = ["n.user_id = {$adminId}"];
$nParams = [];

if ($search !== '') {
    $like    = "%{$search}%";
    $nWhere[] = "(n.title LIKE ? OR n.message LIKE ?)";
    $nParams[] = $like;
    $nParams[] = $like;
}
if ($statusF === 'unread') { $nWhere[] = "n.is_read = 0"; }
if ($statusF === 'read')   { $nWhere[] = "n.is_read = 1"; }
if ($dateF !== '')         { $nWhere[] = "DATE(n.created_at) = ?"; $nParams[] = $dateF; }
// role filter has no sender in notifications -- skipped

$nSql = "SELECT
    n.id          AS id,
    'notification' AS source,
    'System'      AS sender_name,
    'admin'       AS sender_role,
    n.title       AS subject,
    n.message     AS body,
    n.type        AS message_type,
    n.is_read     AS effective_read,
    0             AS has_attachment,
    NULL          AS parent_id,
    n.created_at  AS created_at
FROM notifications n
WHERE " . implode(' AND ', $nWhere);

// Part B: messages table
$mWhere  = ["(m.recipient_id = {$adminId} OR mr.recipient_id = {$adminId})"];
$mParams = [];

if ($search !== '') {
    $like    = "%{$search}%";
    $mWhere[] = "(su.full_name LIKE ? OR m.subject LIKE ?)";
    $mParams[] = $like;
    $mParams[] = $like;
}
if ($roleF !== '')         { $mWhere[] = "su.role = ?"; $mParams[] = $roleF; }
if ($statusF === 'unread') { $mWhere[] = "COALESCE(mr.is_read, m.is_read) = 0"; }
if ($statusF === 'read')   { $mWhere[] = "COALESCE(mr.is_read, m.is_read) = 1"; }
if ($dateF !== '')         { $mWhere[] = "DATE(m.created_at) = ?"; $mParams[] = $dateF; }

$mSql = "SELECT
    m.id                              AS id,
    'message'                         AS source,
    su.full_name                      AS sender_name,
    su.role                           AS sender_role,
    m.subject                         AS subject,
    m.body                            AS body,
    m.message_type                    AS message_type,
    COALESCE(mr.is_read, m.is_read)   AS effective_read,
    m.has_attachment                  AS has_attachment,
    m.parent_id                       AS parent_id,
    m.created_at                      AS created_at
FROM messages m
JOIN users su ON m.sender_id = su.id
LEFT JOIN message_recipients mr ON mr.message_id = m.id AND mr.recipient_id = {$adminId}
WHERE " . implode(' AND ', $mWhere);

// Fetch and merge
$inboxMessages = [];
try {
    $nStmt = $pdo->prepare($nSql . " ORDER BY n.created_at DESC LIMIT 300");
    $nStmt->execute($nParams);
    $notifRows = $nStmt->fetchAll();

    $msgRows = [];
    if ($messagesTableExists) {
        $mStmt = $pdo->prepare($mSql . " ORDER BY m.created_at DESC LIMIT 300");
        $mStmt->execute($mParams);
        $msgRows = $mStmt->fetchAll();
    }

    $inboxMessages = array_merge($notifRows, $msgRows);
    usort($inboxMessages, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $inboxMessages = array_slice($inboxMessages, 0, 300);

} catch (Exception $e) {
    // Fallback: notifications only
    try {
        $nStmt = $pdo->prepare($nSql . " ORDER BY n.created_at DESC LIMIT 300");
        $nStmt->execute($nParams);
        $inboxMessages = $nStmt->fetchAll();
    } catch (Exception $e2) {}
}

// ============================================================
// OUTBOX -- messages sent by admin
// ============================================================
$outboxMessages = [];
if ($messagesTableExists) {
    $oWhere  = ["m.sender_id = {$adminId}"];
    $oParams = [];
    if ($search !== '') {
        $like = "%{$search}%";
        $oWhere[]  = "(recip.full_name LIKE ? OR m.subject LIKE ?)";
        $oParams[] = $like;
        $oParams[] = $like;
    }
    if ($dateF !== '') { $oWhere[] = "DATE(m.created_at) = ?"; $oParams[] = $dateF; }

    try {
        $oStmt = $pdo->prepare("
            SELECT m.*,
                   recip.full_name AS recipient_name,
                   recip.role      AS recipient_role,
                   (SELECT COUNT(*) FROM message_recipients mr WHERE mr.message_id = m.id) AS broadcast_count,
                   (SELECT COUNT(*) FROM message_recipients mr WHERE mr.message_id = m.id AND mr.is_read = 1) AS broadcast_read_count
            FROM messages m
            LEFT JOIN users recip ON m.recipient_id = recip.id
            WHERE " . implode(' AND ', $oWhere) . "
            ORDER BY m.created_at DESC
            LIMIT 300
        ");
        $oStmt->execute($oParams);
        $outboxMessages = $oStmt->fetchAll();
    } catch (Exception $e) {}
}

// ============================================================
// STATS COUNTERS
// ============================================================
// Inbox Total = all notifications for user + all message deliveries
$totalNotifAll = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id={$adminId}")->fetchColumn();
$totalMsgAll   = 0;
if ($messagesTableExists) {
    try {
        $totalMsgAll  = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE recipient_id={$adminId}")->fetchColumn();
        $totalMsgAll += (int)$pdo->query("SELECT COUNT(*) FROM message_recipients WHERE recipient_id={$adminId}")->fetchColumn();
    } catch (Exception $e) {}
}

$totalInbox  = $totalNotifAll + $totalMsgAll;
$totalOutbox = count($outboxMessages);
$sentToday   = 0;
if ($messagesTableExists) {
    try {
        $sentToday = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE sender_id={$adminId} AND DATE(created_at)=CURDATE()")->fetchColumn();
    } catch (Exception $e) {}
}

// -- Role / type color maps
$roleColors = ['admin'=>'badge-info','student'=>'badge-warning','reviewer'=>'badge-eligible'];
$typeColors = [
    'direct'       => 'badge-info',
    'broadcast'    => 'badge-warning',
    'system_alert' => 'badge-ineligible',
    'reply'        => 'badge-eligible',
    // notification types
    'info'         => 'badge-info',
    'success'      => 'badge-eligible',
    'warning'      => 'badge-warning',
    'error'        => 'badge-ineligible',
];

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>
<div class="container py-4">
<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">
      <i class="bi bi-chat-square-dots me-2" style="color:var(--primary);"></i>Communication Center
    </h1>
    <p class="page-subtitle">
      Internal communication hub — no SMTP, no external email. All messages stay inside the system.
    </p>
  </div>
  <div class="d-flex gap-2">
    <a href="compose.php" class="btn btn-primary" id="btn-compose">
      <i class="bi bi-pencil-square me-1"></i>Compose
    </a>
    <a href="broadcast.php" class="btn btn-warning" id="btn-broadcast">
      <i class="bi bi-megaphone me-1"></i>Broadcast
    </a>
    <a href="templates.php" class="btn btn-outline-primary" id="btn-templates">
      <i class="bi bi-layout-text-window me-1"></i>Templates
    </a>
  </div>
</div>

<?php showFlash(); ?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
  <?php
  $statItems = [
      ['Unread',      $unreadCount, 'bi-envelope-fill',    'red'],
      ['Inbox Total', $totalInbox,  'bi-inbox',            'blue'],
      ['Sent',        $totalOutbox, 'bi-send',             'green'],
      ['Sent Today',  $sentToday,   'bi-clock',            'yellow'],
  ];
  foreach ($statItems as [$label, $val, $icon, $color]): ?>
    <div class="col-6 col-lg-3">
      <div class="stat-card">
        <div class="stat-icon <?= $color ?>"><i class="bi <?= $icon ?>"></i></div>
        <div class="stat-body">
          <div class="stat-label"><?= $label ?></div>
          <div class="stat-value"><?= number_format($val) ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Tabs -->
<div style="display:flex;gap:0;border-bottom:2px solid var(--gray-200);margin-bottom:20px;">
  <?php
  $tabs = [
      'inbox'   => ['bi-inbox',         'Inbox',   $unreadCount > 0 ? $unreadCount : null],
      'outbox'  => ['bi-send',          'Outbox',  null],
      'history' => ['bi-clock-history', 'History', null],
  ];
  foreach ($tabs as $key => [$icon, $label, $badge]): ?>
    <a href="index.php?tab=<?= $key ?><?= $search ? '&search='.urlencode($search) : '' ?>"
       style="padding:10px 22px;font-weight:700;font-size:13.5px;text-decoration:none;
              border-bottom:3px solid <?= $tab === $key ? 'var(--primary)' : 'transparent' ?>;
              color:<?= $tab === $key ? 'var(--primary)' : 'var(--gray-500)' ?>;
              margin-bottom:-2px;display:flex;align-items:center;gap:6px;transition:all .2s;">
      <i class="bi <?= $icon ?>"></i><?= $label ?>
      <?php if ($badge): ?>
        <span style="background:#ef4444;color:#fff;border-radius:10px;
                     padding:0 7px;font-size:10px;font-weight:700;"><?= $badge ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
  <?php if ($unreadCount > 0 && $tab === 'inbox'): ?>
    <a href="index.php?tab=inbox&mark_all_read=1"
       style="margin-left:auto;padding:10px 16px;font-size:12px;color:var(--primary);text-decoration:none;align-self:center;">
      <i class="bi bi-check2-all me-1"></i>Mark All Read
    </a>
  <?php endif; ?>
</div>

<!-- Filter Bar -->
<div class="table-card mb-3" style="padding:12px 16px;">
  <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div style="flex:1;min-width:180px;">
      <label class="form-label" style="font-size:12px;margin-bottom:3px;">Search</label>
      <input type="text" name="search" class="form-control form-control-sm"
             placeholder="Sender, subject, notification..." value="<?= e($search) ?>">
    </div>
    <?php if ($tab === 'inbox'): ?>
      <div style="min-width:130px;">
        <label class="form-label" style="font-size:12px;margin-bottom:3px;">Role</label>
        <select name="role" class="form-select form-select-sm">
          <option value="">All Roles</option>
          <option value="student"  <?= $roleF === 'student'  ? 'selected' : '' ?>>Student</option>
          <option value="reviewer" <?= $roleF === 'reviewer' ? 'selected' : '' ?>>Reviewer</option>
          <option value="admin"    <?= $roleF === 'admin'    ? 'selected' : '' ?>>Admin</option>
        </select>
      </div>
      <div style="min-width:120px;">
        <label class="form-label" style="font-size:12px;margin-bottom:3px;">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <option value="unread" <?= $statusF === 'unread' ? 'selected' : '' ?>>Unread</option>
          <option value="read"   <?= $statusF === 'read'   ? 'selected' : '' ?>>Read</option>
        </select>
      </div>
    <?php endif; ?>
    <div style="min-width:140px;">
      <label class="form-label" style="font-size:12px;margin-bottom:3px;">Date</label>
      <input type="date" name="date" class="form-control form-control-sm" value="<?= e($dateF) ?>">
    </div>
    <div style="padding-top:18px;display:flex;gap:4px;">
      <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i></button>
      <?php if ($search || $roleF || $statusF || $dateF): ?>
        <a href="index.php?tab=<?= $tab ?>" class="btn btn-sm btn-secondary"><i class="bi bi-x-lg"></i></a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- ============================================================
     INBOX TAB
     ============================================================ -->
<?php if ($tab === 'inbox'): ?>
  <div class="table-card">
    <div class="table-card-header">
      <span class="table-card-title"><i class="bi bi-inbox me-1"></i>Inbox</span>
      <span style="font-size:12px;color:var(--gray-400);"><?= count($inboxMessages) ?> messages</span>
    </div>
    <div class="table-responsive">
      <table class="table" style="font-size:13px;">
        <thead>
          <tr>
            <th style="width:32px;"></th>
            <th>Sender</th>
            <th>Role</th>
            <th>Subject / Title</th>
            <th>Type</th>
            <th>Date</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($inboxMessages)): ?>
            <tr><td colspan="8">
              <div class="empty-state" style="padding:48px 16px;">
                <span class="empty-state-icon"><i class="bi bi-inbox"></i></span>
                <div class="empty-state-title">No messages in inbox</div>
                <div class="empty-state-text">Notifications and messages from students and reviewers will appear here.</div>
              </div>
            </td></tr>
          <?php else: ?>
            <?php foreach ($inboxMessages as $m):
              $isUnread   = !(bool)$m['effective_read'];
              $isNotif    = ($m['source'] === 'notification');
              $rColor     = $roleColors[$m['sender_role']] ?? 'badge-info';
              $tColor     = $typeColors[$m['message_type']] ?? 'badge-info';
              $typeLabel  = $isNotif
                  ? ucfirst($m['message_type'])
                  : ucfirst(str_replace('_', ' ', $m['message_type']));
              // View URL: notifications go to a simple modal/view, messages to view.php
              $viewUrl    = $isNotif
                  ? "index.php?tab=inbox&mark_read={$m['id']}&src=notification"
                  : "view.php?id={$m['id']}";
            ?>
              <tr style="<?= $isUnread ? 'background:rgba(37,99,235,.03);font-weight:600;' : '' ?>">
                <td style="text-align:center;">
                  <?php if ($isUnread): ?>
                    <span style="width:8px;height:8px;background:var(--primary);border-radius:50%;
                                 display:inline-block;"></span>
                  <?php endif; ?>
                </td>
                <td>
                  <strong style="font-size:13px;"><?= e($m['sender_name']) ?></strong>
                </td>
                <td>
                  <span class="badge <?= $rColor ?>" style="font-size:10px;">
                    <?= ucfirst(e($m['sender_role'])) ?>
                  </span>
                </td>
                <td style="max-width:260px;">
                  <?php if ($isNotif): ?>
                    <span title="<?= e($m['body']) ?>">
                      <?= e(mb_strimwidth($m['subject'], 0, 60, '...')) ?>
                    </span>
                    <div style="font-size:11px;color:var(--gray-400);margin-top:2px;">
                      <?= e(mb_strimwidth($m['body'], 0, 80, '...')) ?>
                    </div>
                  <?php else: ?>
                    <a href="view.php?id=<?= $m['id'] ?>" style="color:inherit;text-decoration:none;">
                      <?= e(mb_strimwidth($m['subject'], 0, 60, '...')) ?>
                      <?php if ($m['has_attachment']): ?>
                        <i class="bi bi-paperclip text-muted" style="font-size:11px;margin-left:4px;"></i>
                      <?php endif; ?>
                    </a>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge <?= $tColor ?>" style="font-size:10px;">
                    <?= e($typeLabel) ?>
                  </span>
                </td>
                <td style="white-space:nowrap;color:var(--gray-400);font-size:12px;">
                  <?= e(date('d M Y, H:i', strtotime($m['created_at']))) ?>
                </td>
                <td>
                  <?php if ($isUnread): ?>
                    <span class="badge badge-warning" style="font-size:10px;">Unread</span>
                  <?php else: ?>
                    <span class="badge" style="font-size:10px;background:var(--gray-100);color:var(--gray-500);">Read</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="d-flex gap-1">
                    <?php if ($isNotif): ?>
                      <a href="index.php?tab=inbox&mark_read=<?= $m['id'] ?>&src=notification"
                         class="btn btn-xs btn-outline-primary"
                         style="padding:3px 8px;font-size:11px;" id="view-notif-<?= $m['id'] ?>"
                         title="Mark as Read">
                        <i class="bi bi-check2"></i>
                      </a>
                    <?php else: ?>
                      <a href="view.php?id=<?= $m['id'] ?>" class="btn btn-xs btn-outline-primary"
                         style="padding:3px 8px;font-size:11px;" id="view-msg-<?= $m['id'] ?>">
                        <i class="bi bi-eye"></i>
                      </a>
                      <a href="compose.php?reply_to=<?= $m['id'] ?>" class="btn btn-xs btn-outline-secondary"
                         style="padding:3px 8px;font-size:11px;" title="Reply">
                        <i class="bi bi-reply"></i>
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- ============================================================
     OUTBOX TAB
     ============================================================ -->
<?php if ($tab === 'outbox'): ?>
  <div class="table-card">
    <div class="table-card-header">
      <span class="table-card-title"><i class="bi bi-send me-1"></i>Outbox</span>
      <span style="font-size:12px;color:var(--gray-400);"><?= count($outboxMessages) ?> messages</span>
    </div>
    <div class="table-responsive">
      <table class="table" style="font-size:13px;">
        <thead>
          <tr>
            <th>Recipient</th>
            <th>Subject</th>
            <th>Type</th>
            <th>Date</th>
            <th>Delivery</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($outboxMessages)): ?>
            <tr><td colspan="6">
              <div class="empty-state" style="padding:48px 16px;">
                <span class="empty-state-icon"><i class="bi bi-send"></i></span>
                <div class="empty-state-title">No outgoing messages yet</div>
                <div class="empty-state-text">Messages you compose will appear here.</div>
              </div>
            </td></tr>
          <?php else: ?>
            <?php foreach ($outboxMessages as $m):
              $tColor  = $typeColors[$m['message_type']] ?? 'badge-info';
              $isBcast = ($m['message_type'] === 'broadcast');
            ?>
              <tr>
                <td>
                  <?php if ($isBcast): ?>
                    <span style="font-weight:700;color:var(--warning);">
                      <i class="bi bi-broadcast me-1"></i>Broadcast
                      (<?= (int)$m['broadcast_count'] ?> recipients)
                    </span>
                  <?php else: ?>
                    <strong><?= e($m['recipient_name'] ?? '&mdash;') ?></strong>
                    <?php if ($m['recipient_role']): ?>
                      <span class="badge <?= $roleColors[$m['recipient_role']] ?? 'badge-info' ?>"
                            style="font-size:10px;margin-left:4px;">
                        <?= ucfirst($m['recipient_role']) ?>
                      </span>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td style="max-width:240px;">
                  <a href="view.php?id=<?= $m['id'] ?>" style="color:inherit;text-decoration:none;">
                    <?= e(mb_strimwidth($m['subject'], 0, 55, '...')) ?>
                    <?php if ($m['has_attachment']): ?>
                      <i class="bi bi-paperclip text-muted" style="font-size:11px;margin-left:4px;"></i>
                    <?php endif; ?>
                  </a>
                </td>
                <td>
                  <span class="badge <?= $tColor ?>" style="font-size:10px;">
                    <?= ucfirst(str_replace('_', ' ', $m['message_type'])) ?>
                  </span>
                </td>
                <td style="white-space:nowrap;color:var(--gray-400);font-size:12px;">
                  <?= e(date('d M Y, H:i', strtotime($m['created_at']))) ?>
                </td>
                <td>
                  <?php if ($isBcast): ?>
                    <?php $bc = (int)$m['broadcast_count']; $br = (int)$m['broadcast_read_count']; ?>
                    <div style="font-size:11px;">
                      <span style="color:var(--success);"><?= $br ?> read</span> /
                      <span style="color:var(--gray-400);"><?= $bc - $br ?> unread</span>
                    </div>
                    <?php if ($bc > 0): ?>
                      <div style="width:80px;height:4px;background:var(--gray-200);border-radius:99px;overflow:hidden;margin-top:3px;">
                        <div style="width:<?= round($br/$bc*100) ?>%;height:100%;background:var(--success);border-radius:99px;"></div>
                      </div>
                    <?php endif; ?>
                  <?php elseif ($m['is_read']): ?>
                    <span class="badge badge-eligible" style="font-size:10px;">Read</span>
                  <?php else: ?>
                    <span class="badge badge-warning" style="font-size:10px;">Unread</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="view.php?id=<?= $m['id'] ?>" class="btn btn-xs btn-outline-primary"
                     style="padding:3px 8px;font-size:11px;" id="view-out-<?= $m['id'] ?>">
                    <i class="bi bi-eye"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- ============================================================
     HISTORY TAB
     Merges notifications history + messages history
     ============================================================ -->
<?php if ($tab === 'history'): ?>
  <?php
  // Notifications history
  $histNotif = $pdo->query("
      SELECT
          n.id, 'notification' AS source,
          'System' AS sender_name, 'admin' AS sender_role,
          NULL     AS recipient_name, NULL AS recipient_role,
          n.title  AS subject, n.type AS message_type,
          n.is_read, n.created_at
      FROM notifications n
      WHERE n.user_id = {$adminId}
      ORDER BY n.created_at DESC
      LIMIT 300
  ")->fetchAll();

  // Messages history
  $histMsg = [];
  if ($messagesTableExists) {
      try {
          $hStmt = $pdo->prepare("
              SELECT
                  m.id, 'message' AS source,
                  s.full_name AS sender_name, s.role AS sender_role,
                  r.full_name AS recipient_name, r.role AS recipient_role,
                  m.subject, m.message_type,
                  m.is_read, m.created_at
              FROM messages m
              JOIN users s ON m.sender_id = s.id
              LEFT JOIN users r ON m.recipient_id = r.id
              WHERE (m.sender_id = {$adminId} OR m.recipient_id = {$adminId} OR m.message_type = 'broadcast')
              ORDER BY m.created_at DESC
              LIMIT 300
          ");
          $hStmt->execute([]);
          $histMsg = $hStmt->fetchAll();
      } catch (Exception $e) {}
  }

  $histRows = array_merge($histNotif, $histMsg);
  usort($histRows, function($a, $b) {
      return strtotime($b['created_at']) - strtotime($a['created_at']);
  });
  $histRows = array_slice($histRows, 0, 500);
  ?>
  <div class="table-card">
    <div class="table-card-header">
      <span class="table-card-title"><i class="bi bi-clock-history me-1"></i>Notification History</span>
      <span style="font-size:12px;color:var(--gray-400);"><?= count($histRows) ?> records</span>
    </div>
    <div class="table-responsive">
      <table class="table" style="font-size:12.5px;">
        <thead>
          <tr>
            <th>Sender</th><th>Recipient</th><th>Subject / Title</th>
            <th>Type</th><th>Source</th><th>Date</th><th>Read</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($histRows)): ?>
            <tr><td colspan="8"><div class="empty-state" style="padding:40px;">
              <span class="empty-state-icon"><i class="bi bi-clock-history"></i></span>
              <div class="empty-state-title">No history yet</div>
            </div></td></tr>
          <?php else: ?>
            <?php foreach ($histRows as $h):
              $isNotifH = ($h['source'] === 'notification');
              $tColorH  = $typeColors[$h['message_type']] ?? 'badge-info';
            ?>
              <tr>
                <td>
                  <strong><?= e($h['sender_name']) ?></strong>
                  <span class="badge <?= $roleColors[$h['sender_role']] ?? 'badge-info' ?>"
                        style="font-size:9.5px;margin-left:3px;"><?= ucfirst($h['sender_role']) ?></span>
                </td>
                <td>
                  <?php if ($isNotifH): ?>
                    <span style="color:var(--gray-400);font-size:11px;">Me (notification)</span>
                  <?php elseif (isset($h['message_type']) && $h['message_type'] === 'broadcast'): ?>
                    <span style="color:var(--warning);font-weight:700;">Broadcast</span>
                  <?php elseif (!empty($h['recipient_name'])): ?>
                    <?= e($h['recipient_name']) ?>
                    <span class="badge <?= $roleColors[$h['recipient_role'] ?? ''] ?? 'badge-info' ?>"
                          style="font-size:9.5px;margin-left:3px;"><?= ucfirst($h['recipient_role'] ?? '') ?></span>
                  <?php else: ?>
                    &mdash;
                  <?php endif; ?>
                </td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    title="<?= e($h['subject']) ?>"><?= e($h['subject']) ?></td>
                <td>
                  <span class="badge <?= $tColorH ?>" style="font-size:10px;">
                    <?= ucfirst(str_replace('_', ' ', $h['message_type'])) ?>
                  </span>
                </td>
                <td>
                  <span style="font-size:10.5px;color:var(--gray-400);">
                    <?= $isNotifH ? 'Notification' : 'Message' ?>
                  </span>
                </td>
                <td style="white-space:nowrap;color:var(--gray-400);font-size:11px;">
                  <?= e(date('d M Y, H:i', strtotime($h['created_at']))) ?>
                </td>
                <td>
                  <?php if ($h['is_read']): ?>
                    <span class="badge" style="background:var(--gray-100);color:var(--gray-500);font-size:10px;">Read</span>
                  <?php else: ?>
                    <span class="badge badge-warning" style="font-size:10px;">Unread</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!$isNotifH): ?>
                    <a href="view.php?id=<?= $h['id'] ?>" class="btn btn-xs btn-outline-primary"
                       style="padding:3px 8px;font-size:11px;">
                      <i class="bi bi-eye"></i>
                    </a>
                  <?php else: ?>
                    <a href="index.php?tab=inbox&mark_read=<?= $h['id'] ?>&src=notification"
                       class="btn btn-xs btn-outline-secondary"
                       style="padding:3px 8px;font-size:11px;" title="Mark read">
                      <i class="bi bi-check2"></i>
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
