<?php
// ============================================================
// admin/communication_center/compose.php
// Compose a direct message OR reply to an existing message.
// Recipients: Students or Reviewers only.
// ============================================================
$pageTitle = 'Compose Message';

require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/comm_helper.php';

requireLogin();
requireRole('admin');

$pdo     = getDB();
$adminId = currentUserId();

ensureCommTables($pdo);
seedCommTemplates($pdo);

// ── Pre-fill for reply ────────────────────────────────────────
$replyToId  = (int)($_GET['reply_to'] ?? 0);
$replyMsg   = null;
$prefillSubj = '';
$prefillBody = '';
$prefillRecip = 0;

if ($replyToId > 0) {
    $rs = $pdo->prepare("SELECT m.*, u.full_name AS sender_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.id = ?");
    $rs->execute([$replyToId]);
    $replyMsg = $rs->fetch();
    if ($replyMsg) {
        $prefillSubj  = 'Re: ' . $replyMsg['subject'];
        $prefillBody  = "\n\n---\n[Original message from {$replyMsg['sender_name']}]\n" . $replyMsg['body'];
        $prefillRecip = (int)$replyMsg['sender_id'];
    }
}

// ── Load templates ────────────────────────────────────────────
$templates = $pdo->query("SELECT * FROM comm_templates WHERE is_active=1 ORDER BY template_type ASC")->fetchAll();

// ── Load recipients ───────────────────────────────────────────
$recipients = $pdo->query("
    SELECT id, full_name, role, student_code, email
    FROM users
    WHERE role IN ('student','reviewer')
    ORDER BY role ASC, full_name ASC
")->fetchAll();

// ── Handle submit ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipId    = (int)($_POST['recipient_id'] ?? 0);
    $subject    = trim($_POST['subject']       ?? '');
    $body       = trim($_POST['body']          ?? '');
    $parentId   = (int)($_POST['parent_id']    ?? 0) ?: null;
    $hasAttach  = !empty($_POST['has_attachment']) ? 1 : 0;
    $attachNote = trim($_POST['attachment_note'] ?? '');
    $msgType    = $parentId ? 'reply' : 'direct';

    if (!$recipId || !$subject || !$body) {
        setFlash('error', 'Recipient, subject, and message body are required.');
    } else {
        // Validate recipient exists and is student/reviewer
        $rCheck = $pdo->prepare("SELECT id FROM users WHERE id=? AND role IN ('student','reviewer')");
        $rCheck->execute([$recipId]);
        if (!$rCheck->fetch()) {
            setFlash('error', 'Invalid recipient.');
        } else {
            sendInternalMessage($pdo, $adminId, $recipId, $subject, $body, $msgType, $parentId, (bool)$hasAttach, $attachNote ?: null);
            setFlash('success', 'Message sent successfully.');
            header('Location: index.php?tab=outbox');
            exit;
        }
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">
      <i class="bi bi-pencil-square me-2" style="color:var(--primary);"></i>
      <?= $replyMsg ? 'Reply to Message' : 'Compose Message' ?>
    </h1>
    <p class="page-subtitle">Send an internal message to a student or reviewer. No external email used.</p>
  </div>
  <a href="index.php" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
</div>

<?php showFlash(); ?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-body">
        <form method="POST" id="compose-form">
          <?php if ($replyMsg): ?>
            <input type="hidden" name="parent_id" value="<?= $replyToId ?>">
          <?php endif; ?>

          <!-- Template selector -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Use Template <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(optional)</span></label>
            <select id="tpl-sel" class="form-select form-select-sm">
              <option value="">— No template —</option>
              <?php foreach ($templates as $t): ?>
                <option value="<?= $t['id'] ?>"
                        data-subject="<?= htmlspecialchars($t['subject']) ?>"
                        data-body="<?= htmlspecialchars($t['body']) ?>">
                  <?= e($t['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Variable chips -->
          <div style="background:rgba(37,99,235,.04);border:1px solid rgba(37,99,235,.1);border-radius:8px;
                       padding:8px 12px;margin-bottom:14px;font-size:12px;">
            <div style="font-weight:700;margin-bottom:4px;color:var(--gray-700);">
              <i class="bi bi-braces me-1" style="color:var(--primary);"></i>Variables (click to insert)
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:4px;">
              <?php foreach (['{{student_name}}','{{program_name}}','{{ranking}}','{{score}}'] as $v): ?>
                <code style="background:rgba(37,99,235,.08);border-radius:4px;padding:2px 7px;
                             font-size:11px;cursor:pointer;" onclick="insertVar('<?= $v ?>')"><?= $v ?></code>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Recipient -->
          <div class="mb-3">
            <label class="form-label fw-semibold">To <span class="text-danger">*</span></label>
            <select name="recipient_id" id="recip-sel" class="form-select form-select-sm" required>
              <option value="">— Select Recipient —</option>
              <?php
              $lastRole = null;
              foreach ($recipients as $rec):
                if ($rec['role'] !== $lastRole) {
                    if ($lastRole !== null) echo '</optgroup>';
                    echo '<optgroup label="' . ucfirst($rec['role']) . 's">';
                    $lastRole = $rec['role'];
                }
              ?>
                <option value="<?= $rec['id'] ?>" <?= $prefillRecip == $rec['id'] ? 'selected' : '' ?>>
                  <?= e($rec['full_name']) ?><?= $rec['student_code'] ? ' ('.$rec['student_code'].')' : '' ?>
                </option>
              <?php endforeach; ?>
              <?php if ($lastRole !== null) echo '</optgroup>'; ?>
            </select>
          </div>

          <!-- Subject -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
            <input type="text" name="subject" id="msg-subject" class="form-control form-control-sm"
                   value="<?= e($prefillSubj) ?>" placeholder="Message subject…" required>
          </div>

          <!-- Body -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Message <span class="text-danger">*</span></label>
            <textarea name="body" id="msg-body" class="form-control" rows="10"
                      placeholder="Type your message here…" required><?= e($prefillBody) ?></textarea>
          </div>

          <!-- Attachment note -->
          <div class="mb-3">
            <div class="form-check mb-1">
              <input class="form-check-input" type="checkbox" name="has_attachment" id="chk-attach" value="1"
                     onchange="document.getElementById('attach-note-row').classList.toggle('d-none',!this.checked)">
              <label class="form-check-label" style="font-size:13px;">Include attachment reference</label>
            </div>
            <div class="d-none" id="attach-note-row">
              <input type="text" name="attachment_note" class="form-control form-control-sm"
                     placeholder="e.g. Certificate PDF — see Student Portal">
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" onclick="previewMsg()">
              <i class="bi bi-eye me-1"></i>Preview
            </button>
            <button type="submit" class="btn btn-primary flex-fill" id="btn-send-msg"
                    onclick="return confirm('Send this message?')">
              <i class="bi bi-send me-2"></i><?= $replyMsg ? 'Send Reply' : 'Send Message' ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ── RIGHT: Reply context ─────────────────────────────── -->
  <div class="col-lg-5">
    <?php if ($replyMsg): ?>
      <div class="card mb-3" style="background:var(--gray-50);">
        <div class="card-body">
          <div class="card-title mb-2" style="font-size:12.5px;padding-bottom:8px;border-bottom:1px solid var(--gray-200);">
            <i class="bi bi-envelope-open me-1" style="color:var(--gray-400);"></i>
            Original Message
          </div>
          <div style="font-size:12.5px;font-weight:700;"><?= e($replyMsg['subject']) ?></div>
          <div style="font-size:11.5px;color:var(--gray-400);margin-bottom:8px;">
            From: <?= e($replyMsg['sender_name']) ?> ·
            <?= e(date('d M Y H:i', strtotime($replyMsg['created_at']))) ?>
          </div>
          <div style="font-size:13px;line-height:1.7;white-space:pre-wrap;color:var(--gray-600);
                       max-height:200px;overflow-y:auto;">
            <?= nl2br(e(mb_strimwidth($replyMsg['body'], 0, 400, '…'))) ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Quick tips -->
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-2" style="padding-bottom:8px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-lightbulb me-2" style="color:#f59e0b;"></i>Tips
        </div>
        <ul style="font-size:12.5px;color:var(--gray-500);padding-left:18px;margin:0;line-height:2;">
          <li>All messages are <strong>internal only</strong> — no email is sent.</li>
          <li>Recipients see messages in their <strong>Notifications</strong> panel.</li>
          <li>Use templates for consistent communication.</li>
          <li>Use <strong>Broadcast</strong> to reach multiple users at once.</li>
          <li>Variables like <code>{{student_name}}</code> are replaced when composing.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Preview modal -->
<div id="preview-modal" style="display:none;position:fixed;inset:0;z-index:9999;
     background:rgba(0,0,0,.55);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:14px;width:min(640px,96vw);max-height:85vh;
               overflow-y:auto;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.3);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <strong style="font-size:15px;"><i class="bi bi-eye me-2" style="color:var(--primary);"></i>Message Preview</strong>
      <button onclick="document.getElementById('preview-modal').style.display='none'"
              class="btn btn-sm btn-secondary"><i class="bi bi-x-lg"></i></button>
    </div>
    <div style="background:var(--gray-50);border-radius:8px;padding:10px 14px;font-size:12.5px;margin-bottom:12px;">
      <strong>Subject:</strong> <span id="prev-subject" style="color:var(--primary);"></span>
    </div>
    <div style="border:1px solid var(--gray-200);border-radius:8px;padding:20px;
                 font-size:13.5px;line-height:1.8;white-space:pre-wrap;" id="prev-body"></div>
  </div>
</div>

<script>
// Template auto-fill
document.getElementById('tpl-sel').addEventListener('change', function() {
    const o = this.options[this.selectedIndex];
    if (o.value) {
        document.getElementById('msg-subject').value = o.dataset.subject || '';
        document.getElementById('msg-body').value    = o.dataset.body    || '';
    }
});

// Insert variable
function insertVar(v) {
    const ta = document.getElementById('msg-body');
    const s = ta.selectionStart, e = ta.selectionEnd;
    ta.value = ta.value.slice(0,s) + v + ta.value.slice(e);
    ta.selectionStart = ta.selectionEnd = s + v.length;
    ta.focus();
}

// Preview
function previewMsg() {
    document.getElementById('prev-subject').textContent = document.getElementById('msg-subject').value;
    document.getElementById('prev-body').textContent    = document.getElementById('msg-body').value;
    document.getElementById('preview-modal').style.display = 'flex';
}
document.getElementById('preview-modal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php require_once '../../includes/footer.php'; ?>
