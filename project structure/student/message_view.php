<?php
// ============================================================
// student/message_view.php
// Message thread view: read conversation with admin, reply back.
// Mirrors admin/communication_center/view.php for student role.
// ============================================================
$pageTitle = 'Message';

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/comm_helper.php';

requireLogin();
requireRole('student');

$pdo       = getDB();
$studentId = currentUserId();

ensureCommTables($pdo);

$msgId   = (int)($_GET['id'] ?? 0);
$notifId = (int)($_GET['notif_id'] ?? 0);

if (!$msgId) {
    header('Location: notifications.php');
    exit;
}

$msg = loadThreadRootForUser($pdo, $msgId, $studentId);
if (!$msg) {
    setFlash('error', 'Message not found or access denied.');
    header('Location: notifications.php');
    exit;
}

$msgId = (int)$msg['id'];

if ($notifId > 0) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
        ->execute([$notifId, $studentId]);
}

markMessageRead($pdo, $msgId, $studentId);

$thread = $pdo->prepare("
    SELECT m.*, s.full_name AS sender_name, s.role AS sender_role
    FROM messages m
    JOIN users s ON m.sender_id = s.id
    WHERE m.parent_id = ?
    ORDER BY m.created_at ASC
");
$thread->execute([$msgId]);
$replies = $thread->fetchAll();

foreach ($replies as $rep) {
    if ((int)$rep['recipient_id'] === $studentId) {
        markMessageRead($pdo, (int)$rep['id'], $studentId);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_body'])) {
    $replyBody = trim($_POST['reply_body']);

    if ($msg['message_type'] === 'broadcast') {
        setFlash('error', 'Broadcast messages cannot be replied to.');
    } elseif ($replyBody === '') {
        setFlash('error', 'Reply body is required.');
    } else {
        $replyTo = (int)($msg['recipient_id'] ?? 0);
        if ((int)$msg['sender_id'] === $studentId) {
            $replyTo = (int)$msg['recipient_id'];
        } else {
            $replyTo = (int)$msg['sender_id'];
        }

        if (!$replyTo) {
            foreach (array_reverse($replies) as $rep) {
                if ($rep['sender_role'] === 'admin') {
                    $replyTo = (int)$rep['sender_id'];
                    break;
                }
            }
        }

        if ($replyTo) {
            $baseSubject  = preg_replace('/^Re:\s*/i', '', (string)$msg['subject']);
            $replySubject = 'Re: ' . $baseSubject;
            sendInternalMessage($pdo, $studentId, $replyTo, $replySubject, $replyBody, 'reply', $msgId);
            setFlash('success', 'Reply sent.');
        } else {
            setFlash('error', 'Unable to determine the administrator to reply to.');
        }
    }
    // PRG: always redirect after POST so refresh/back cannot resubmit
    header('Location: message_view.php?id=' . $msgId);
    exit;
}

$roleColors = ['admin' => 'badge-info', 'student' => 'badge-warning', 'reviewer' => 'badge-eligible'];
$typeLabels = [
    'direct'       => 'Direct',
    'broadcast'    => 'Broadcast',
    'system_alert' => 'System Alert',
    'reply'        => 'Reply',
];

require_once __DIR__ . '/../includes/header.php';
//require_once __DIR__ . '/../includes/navbar.php';
?>
<?php require_once __DIR__ . '/../includes/student_header.php'; ?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title" style="font-size:18px;">
      <i class="bi bi-chat-text me-2" style="color:var(--primary);"></i>
      <?= e(mb_strimwidth($msg['subject'], 0, 80, '…')) ?>
    </h1>
    <p class="page-subtitle">
      <?= e(date('d M Y, H:i', strtotime($msg['created_at']))) ?>
      &nbsp;·&nbsp;
      <?= $typeLabels[$msg['message_type']] ?? ucfirst($msg['message_type']) ?>
    </p>
  </div>
  <div class="d-flex gap-2">
    <a href="notifications.php" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Back to Notifications
    </a>
  </div>
</div>

<?php showFlash(); ?>

<!-- ── Back navigation bar ────────────────────────────────── -->
<div class="mb-3">
  <a href="notifications.php" class="btn btn-secondary">
    <i class="bi bi-arrow-left me-2"></i>Back to Notifications
  </a>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-body">
        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:16px;
                     padding-bottom:12px;border-bottom:1px solid var(--gray-100);">
          <div style="width:42px;height:42px;border-radius:50%;background:rgba(37,99,235,.1);
                       display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi bi-person-fill" style="color:var(--primary);font-size:18px;"></i>
          </div>
          <div style="flex:1;">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
              <strong style="font-size:14px;"><?= e($msg['sender_name']) ?></strong>
              <span class="badge <?= $roleColors[$msg['sender_role']] ?? 'badge-info' ?>" style="font-size:10px;">
                <?= ucfirst(e($msg['sender_role'])) ?>
              </span>
            </div>
            <div style="font-size:12px;color:var(--gray-400);margin-top:2px;">
              To:
              <?php if ($msg['recipient_name']): ?>
                <strong><?= e($msg['recipient_name']) ?></strong>
                <span class="badge <?= $roleColors[$msg['recipient_role'] ?? ''] ?? 'badge-info' ?>"
                      style="font-size:9px;"><?= ucfirst($msg['recipient_role'] ?? '') ?></span>
              <?php else: ?>
                Administrator
              <?php endif; ?>
              &nbsp;·&nbsp;
              <?= e(date('d M Y H:i', strtotime($msg['created_at']))) ?>
            </div>
          </div>
        </div>

        <div style="font-size:14px;line-height:1.8;white-space:pre-wrap;color:#1e293b;">
          <?= nl2br(e($msg['body'])) ?>
        </div>
      </div>
    </div>

    <?php if (!empty($replies)): ?>
      <div style="border-left:3px solid var(--primary);padding-left:16px;margin-bottom:20px;">
        <?php foreach ($replies as $rep):
          $rc = $roleColors[$rep['sender_role']] ?? 'badge-info';
        ?>
          <div class="card mb-2" style="background:var(--gray-50);">
            <div class="card-body" style="padding:14px 18px;">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
                <i class="bi bi-reply" style="color:var(--primary);"></i>
                <strong style="font-size:13px;"><?= e($rep['sender_name']) ?></strong>
                <span class="badge <?= $rc ?>" style="font-size:10px;"><?= ucfirst($rep['sender_role']) ?></span>
                <span style="font-size:11px;color:var(--gray-400);margin-left:auto;">
                  <?= e(date('d M Y H:i', strtotime($rep['created_at']))) ?>
                </span>
              </div>
              <div style="font-size:13.5px;line-height:1.75;white-space:pre-wrap;color:#334155;">
                <?= nl2br(e($rep['body'])) ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($msg['message_type'] !== 'broadcast'): ?>
      <div class="card">
        <div class="card-body">
          <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
            <i class="bi bi-reply me-2" style="color:var(--primary);"></i>Reply to Administrator
          </div>
          <form method="POST" id="reply-form"
                onsubmit="if(!confirm('Send this reply?'))return false;
                          var b=document.getElementById('btn-send-reply');
                          b.disabled=true;b.innerHTML='<i class=\'bi bi-hourglass-split me-2\'></i>Sending…';
                          return true;">
            <div class="mb-3">
              <input type="text" class="form-control form-control-sm mb-2"
                     value="Re: <?= e(preg_replace('/^Re:\s*/i', '', (string)$msg['subject'])) ?>" readonly
                     style="background:var(--gray-50);color:var(--gray-500);">
              <textarea name="reply_body" class="form-control" rows="5"
                        placeholder="Type your reply…" required id="reply-body"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" id="btn-send-reply">
              <i class="bi bi-send me-2"></i>Send Reply
            </button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:8px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-info-circle me-2" style="color:var(--primary);"></i>Message Info
        </div>
        <?php
        $metaItems = [
            ['Type',    ucfirst(str_replace('_', ' ', $msg['message_type']))],
            ['Subject', mb_strimwidth($msg['subject'], 0, 40, '…')],
            ['Date',    date('d M Y, H:i', strtotime($msg['created_at']))],
            ['Replies', (string)count($replies)],
        ];
        foreach ($metaItems as [$label, $value]): ?>
          <div style="display:flex;justify-content:space-between;align-items:flex-start;
                       padding:7px 0;border-bottom:1px solid var(--gray-100);font-size:12.5px;">
            <span style="color:var(--gray-400);min-width:60px;"><?= $label ?></span>
            <span style="font-weight:600;text-align:right;max-width:200px;word-break:break-word;"><?= e($value) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
