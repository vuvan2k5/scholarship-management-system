<?php
// ============================================================
// admin/communication_center/view.php
// Message detail view: full thread, sender/recipient info,
// attachment note, reply form. Admin read-only compose reply.
// ============================================================
$pageTitle = 'Message Detail';

require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/comm_helper.php';

requireLogin();
requireRole('admin');

$pdo     = getDB();
$adminId = currentUserId();

ensureCommTables($pdo);

$msgId = (int)($_GET['id'] ?? 0);
if (!$msgId) { header('Location: index.php'); exit; }

// ── Load message ──────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT m.*,
           s.full_name AS sender_name, s.role AS sender_role, s.email AS sender_email,
           r.full_name AS recipient_name, r.role AS recipient_role
    FROM messages m
    JOIN users s ON m.sender_id = s.id
    LEFT JOIN users r ON m.recipient_id = r.id
    WHERE m.id = ?
");
$stmt->execute([$msgId]);
$msg = $stmt->fetch();

if (!$msg) { setFlash('error','Message not found.'); header('Location: index.php'); exit; }

// ── Mark as read for admin ────────────────────────────────────
markMessageRead($pdo, $msgId, $adminId);

// ── Load thread (replies) ────────────────────────────────────
$thread = $pdo->prepare("
    SELECT m.*, s.full_name AS sender_name, s.role AS sender_role
    FROM messages m
    JOIN users s ON m.sender_id = s.id
    WHERE m.parent_id = ?
    ORDER BY m.created_at ASC
");
$thread->execute([$msgId]);
$replies = $thread->fetchAll();

// ── Broadcast recipients ──────────────────────────────────────
$bcastRecips = [];
if ($msg['message_type'] === 'broadcast') {
    $bStmt = $pdo->prepare("
        SELECT u.full_name, u.role, mr.is_read, mr.read_at
        FROM message_recipients mr
        JOIN users u ON mr.recipient_id = u.id
        WHERE mr.message_id = ?
        ORDER BY u.full_name ASC
    ");
    $bStmt->execute([$msgId]);
    $bcastRecips = $bStmt->fetchAll();
}

// ── Handle reply submit ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_body'])) {
    $replyBody = trim($_POST['reply_body']);
    $replyTo   = (int)($msg['sender_id'] ?? 0);  // Reply to original sender

    if ($replyBody && $replyTo) {
        $replySubject = 'Re: ' . $msg['subject'];
        sendInternalMessage($pdo, $adminId, $replyTo, $replySubject, $replyBody, 'reply', $msgId);
        setFlash('success', 'Reply sent.');
    } else {
        setFlash('error', 'Reply body is required.');
    }
    // PRG: always redirect after POST so refresh/back cannot resubmit
    header('Location: view.php?id=' . $msgId);
    exit;
}

$roleColors = ['admin'=>'badge-info','student'=>'badge-warning','reviewer'=>'badge-eligible'];
$typeLabels = [
    'direct'       => 'Direct',
    'broadcast'    => 'Broadcast',
    'system_alert' => 'System Alert',
    'reply'        => 'Reply',
];

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<!-- ── Page Header ──────────────────────────────────────────── -->
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
    <a href="index.php" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Back to Communication Center
    </a>
    <?php if ($msg['sender_id'] !== $adminId): ?>
      <a href="compose.php?reply_to=<?= $msgId ?>" class="btn btn-primary" id="btn-reply-top">
        <i class="bi bi-reply me-1"></i>Reply
      </a>
    <?php endif; ?>
  </div>
</div>

<?php showFlash(); ?>

<!-- ── Back navigation bar ────────────────────────────────── -->
<div class="mb-3">
  <a href="index.php" class="btn btn-secondary">
    <i class="bi bi-arrow-left me-2"></i>Back to Communication Center
  </a>
</div>

<div class="row g-4">

  <!-- ── LEFT: Message Thread ─────────────────────────────── -->
  <div class="col-lg-8">

    <!-- Original message -->
    <div class="card mb-3">
      <div class="card-body">
        <!-- Sender / Recipient info -->
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
              <?php if ($msg['sender_email'] ?? ''): ?>
                <span style="font-size:11px;color:var(--gray-400);"><?= e($msg['sender_email']) ?></span>
              <?php endif; ?>
            </div>
            <div style="font-size:12px;color:var(--gray-400);margin-top:2px;">
              To:
              <?php if ($msg['message_type'] === 'broadcast'): ?>
                <span style="color:var(--warning);font-weight:700;">
                  Broadcast (<?= count($bcastRecips) ?> recipients)
                </span>
              <?php elseif ($msg['recipient_name']): ?>
                <strong><?= e($msg['recipient_name']) ?></strong>
                <span class="badge <?= $roleColors[$msg['recipient_role'] ?? ''] ?? 'badge-info' ?>"
                      style="font-size:9px;"><?= ucfirst($msg['recipient_role'] ?? '') ?></span>
              <?php else: ?>
                Admin
              <?php endif; ?>
              &nbsp;·&nbsp;
              <?= e(date('d M Y H:i', strtotime($msg['created_at']))) ?>
            </div>
          </div>
          <?php if ($msg['has_attachment']): ?>
            <div style="font-size:12px;color:var(--info);background:rgba(6,182,212,.08);
                         border-radius:6px;padding:4px 10px;">
              <i class="bi bi-paperclip me-1"></i>
              <?= $msg['attachment_note'] ? e($msg['attachment_note']) : 'Attachment' ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Message body -->
        <div style="font-size:14px;line-height:1.8;white-space:pre-wrap;color:#1e293b;">
          <?= nl2br(e($msg['body'])) ?>
        </div>
      </div>
    </div>

    <!-- Replies thread -->
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

    <!-- Reply form (only if message is not from admin) -->
    <?php if ($msg['sender_id'] !== $adminId): ?>
      <div class="card">
        <div class="card-body">
          <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
            <i class="bi bi-reply me-2" style="color:var(--primary);"></i>Reply to <?= e($msg['sender_name']) ?>
          </div>
          <form method="POST" id="reply-form"
                onsubmit="if(!confirm('Send this reply?'))return false;
                          var b=document.getElementById('btn-send-reply');
                          b.disabled=true;b.innerHTML='<i class=\'bi bi-hourglass-split me-2\'></i>Sending…';
                          return true;">
            <div class="mb-3">
              <input type="text" class="form-control form-control-sm mb-2"
                     value="Re: <?= e($msg['subject']) ?>" readonly
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

  </div><!-- /col-lg-8 -->

  <!-- ── RIGHT: Meta / Broadcast recipients ───────────────── -->
  <div class="col-lg-4">

    <!-- Message metadata -->
    <div class="card mb-3">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:8px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-info-circle me-2" style="color:var(--primary);"></i>Message Info
        </div>
        <?php
        $metaItems = [
            ['Type',    ucfirst(str_replace('_',' ',$msg['message_type']))],
            ['Subject', mb_strimwidth($msg['subject'], 0, 40, '…')],
            ['Date',    date('d M Y, H:i', strtotime($msg['created_at']))],
            ['Read',    $msg['is_read'] ? 'Yes — '.date('d M Y H:i', strtotime($msg['read_at']??'')) : 'Not yet'],
        ];
        foreach ($metaItems as [$l, $v]): ?>
          <div style="display:flex;justify-content:space-between;align-items:flex-start;
                       padding:7px 0;border-bottom:1px solid var(--gray-100);font-size:12.5px;">
            <span style="color:var(--gray-400);min-width:60px;"><?= $l ?></span>
            <span style="font-weight:600;text-align:right;max-width:200px;word-break:break-word;"><?= e($v) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Broadcast recipients list -->
    <?php if ($msg['message_type'] === 'broadcast' && !empty($bcastRecips)): ?>
      <div class="card">
        <div class="card-body">
          <div class="card-title mb-3" style="padding-bottom:8px;border-bottom:1px solid var(--gray-100);">
            <i class="bi bi-people me-2" style="color:var(--warning);"></i>
            Recipients (<?= count($bcastRecips) ?>)
          </div>
          <?php
          $readCount = count(array_filter($bcastRecips, fn($r) => $r['is_read']));
          $pct = count($bcastRecips) ? round($readCount/count($bcastRecips)*100) : 0;
          ?>
          <div style="margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--gray-500);margin-bottom:4px;">
              <span><?= $readCount ?> / <?= count($bcastRecips) ?> read</span>
              <span><?= $pct ?>%</span>
            </div>
            <div style="height:6px;background:var(--gray-200);border-radius:99px;overflow:hidden;">
              <div style="width:<?= $pct ?>%;height:100%;background:var(--success);border-radius:99px;"></div>
            </div>
          </div>
          <div style="max-height:300px;overflow-y:auto;">
            <?php foreach ($bcastRecips as $br): ?>
              <div style="display:flex;align-items:center;justify-content:space-between;
                           padding:6px 0;border-bottom:1px solid var(--gray-100);font-size:12px;">
                <div>
                  <span><?= e($br['full_name']) ?></span>
                  <span class="badge <?= $roleColors[$br['role']] ?? 'badge-info' ?>"
                        style="font-size:9px;margin-left:4px;"><?= ucfirst($br['role']) ?></span>
                </div>
                <?php if ($br['is_read']): ?>
                  <span style="color:var(--success);font-size:10px;font-weight:600;">
                    <i class="bi bi-check2"></i> Read
                  </span>
                <?php else: ?>
                  <span style="color:var(--gray-400);font-size:10px;">Unread</span>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div><!-- /col-lg-4 -->

</div><!-- /row -->

<?php require_once '../../includes/footer.php'; ?>
