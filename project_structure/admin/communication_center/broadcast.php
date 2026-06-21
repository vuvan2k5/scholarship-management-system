<?php
// ============================================================
// admin/communication_center/broadcast.php
// Broadcast notifications to groups: all students, all reviewers,
// awarded students, or applicants of a specific program.
// NO SMTP — fully internal.
// ============================================================
$pageTitle = 'Broadcast Notification';

require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/comm_helper.php';

requireLogin();
requireRole('admin');

$pdo     = getDB();
$adminId = currentUserId();

ensureCommTables($pdo);
seedCommTemplates($pdo);

$programs  = $pdo->query("SELECT id, name FROM scholarship_programs ORDER BY name ASC")->fetchAll();
$templates = $pdo->query("SELECT * FROM comm_templates WHERE is_active=1 ORDER BY template_type ASC")->fetchAll();

// ── Handle broadcast submit ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetGroup = $_POST['target_group'] ?? '';
    $programId   = (int)($_POST['program_id'] ?? 0);
    $subject     = trim($_POST['subject'] ?? '');
    $body        = trim($_POST['body']    ?? '');
    $hasAttach   = !empty($_POST['has_attachment']) ? 1 : 0;
    $attachNote  = trim($_POST['attachment_note'] ?? '');

    $validGroups = ['all_students','all_reviewers','awarded_students','program_applicants'];
    if (!in_array($targetGroup, $validGroups) || !$subject || !$body) {
        setFlash('error', 'All fields are required.');
    } else {
        // Build recipient list
        $recipIds = [];
        switch ($targetGroup) {
            case 'all_students':
                $rows = $pdo->query("SELECT id FROM users WHERE role='student'")->fetchAll();
                $recipIds = array_column($rows, 'id');
                break;
            case 'all_reviewers':
                $rows = $pdo->query("SELECT id FROM users WHERE role='reviewer'")->fetchAll();
                $recipIds = array_column($rows, 'id');
                break;
            case 'awarded_students':
                $rows = $pdo->query("
                    SELECT DISTINCT a.student_id AS id
                    FROM ranking_results rr
                    JOIN applications a ON rr.application_id = a.id
                    WHERE rr.awarded = 1
                ")->fetchAll();
                $recipIds = array_column($rows, 'id');
                break;
            case 'program_applicants':
                if ($programId > 0) {
                    $stmt = $pdo->prepare("SELECT DISTINCT student_id AS id FROM applications WHERE program_id=?");
                    $stmt->execute([$programId]);
                    $recipIds = array_column($stmt->fetchAll(), 'id');
                }
                break;
        }

        if (empty($recipIds)) {
            setFlash('warning', 'No recipients found for the selected group/program.');
        } else {
            $count = broadcastInternalMessage($pdo, $adminId, $recipIds, $subject, $body, (bool)$hasAttach, $attachNote ?: null);
            setFlash('success', "Broadcast sent to {$count} recipient(s).");
            header('Location: index.php?tab=outbox');
            exit;
        }
    }
}

// ── Live recipient count (AJAX helper) ────────────────────────
if (isset($_GET['count_group'])) {
    header('Content-Type: application/json');
    $g   = $_GET['count_group'];
    $pid = (int)($_GET['program_id'] ?? 0);
    $n   = 0;
    switch ($g) {
        case 'all_students':      $n = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(); break;
        case 'all_reviewers':     $n = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='reviewer'")->fetchColumn(); break;
        case 'awarded_students':  $n = (int)$pdo->query("SELECT COUNT(DISTINCT a.student_id) FROM ranking_results rr JOIN applications a ON rr.application_id=a.id WHERE rr.awarded=1")->fetchColumn(); break;
        case 'program_applicants':
            if ($pid > 0) { $s=$pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM applications WHERE program_id=?"); $s->execute([$pid]); $n=(int)$s->fetchColumn(); }
            break;
    }
    echo json_encode(['count' => $n]);
    exit;
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">
      <i class="bi bi-megaphone me-2" style="color:var(--warning);"></i>Broadcast Notification
    </h1>
    <p class="page-subtitle">Send a notification to multiple users simultaneously. Internal only — no external email.</p>
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
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-broadcast me-2" style="color:var(--warning);"></i>Compose Broadcast
        </div>
        <form method="POST" id="bcast-form">

          <!-- Template -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Template <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(optional)</span></label>
            <select id="bcast-tpl" class="form-select form-select-sm">
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

          <!-- Variable legend -->
          <div style="background:rgba(37,99,235,.04);border:1px solid rgba(37,99,235,.1);border-radius:8px;
                       padding:8px 12px;margin-bottom:14px;font-size:12px;">
            <div style="font-weight:700;margin-bottom:4px;color:var(--gray-700);">
              <i class="bi bi-braces me-1" style="color:var(--primary);"></i>Variables
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:4px;">
              <?php foreach (['{{student_name}}','{{program_name}}','{{ranking}}','{{score}}'] as $v): ?>
                <code style="background:rgba(37,99,235,.08);border-radius:4px;padding:2px 7px;
                             font-size:11px;cursor:pointer;" onclick="insertBcastVar('<?= $v ?>')"><?= $v ?></code>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Target group -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Send To <span class="text-danger">*</span></label>
            <select name="target_group" id="target-group" class="form-select form-select-sm"
                    onchange="updateGroupInfo()" required>
              <option value="">— Select group —</option>
              <option value="all_students">All Students</option>
              <option value="all_reviewers">All Reviewers</option>
              <option value="awarded_students">Awarded Students (All Programs)</option>
              <option value="program_applicants">Applicants of a Specific Program</option>
            </select>
            <!-- Live recipient count -->
            <div id="recipient-count" style="margin-top:6px;font-size:12px;color:var(--primary);
                                              font-weight:700;display:none;">
              <i class="bi bi-people me-1"></i><span id="count-val">0</span> recipients will receive this broadcast.
            </div>
          </div>

          <!-- Program selector (conditional) -->
          <div class="mb-3 d-none" id="program-field">
            <label class="form-label fw-semibold">Scholarship Program <span class="text-danger">*</span></label>
            <select name="program_id" id="bcast-prog" class="form-select form-select-sm"
                    onchange="updateGroupInfo()">
              <option value="">— Select Program —</option>
              <?php foreach ($programs as $p): ?>
                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Subject -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
            <input type="text" name="subject" id="bcast-subject" class="form-control form-control-sm"
                   placeholder="Notification subject…" required>
          </div>

          <!-- Body -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Message <span class="text-danger">*</span></label>
            <textarea name="body" id="bcast-body" class="form-control" rows="9"
                      placeholder="Type your broadcast message…" required></textarea>
          </div>

          <!-- Attachment note -->
          <div class="mb-3">
            <div class="form-check mb-1">
              <input class="form-check-input" type="checkbox" name="has_attachment" id="bcast-chk-attach" value="1"
                     onchange="document.getElementById('bcast-attach-note').classList.toggle('d-none',!this.checked)">
              <label class="form-check-label" style="font-size:13px;">Include attachment reference</label>
            </div>
            <div class="d-none" id="bcast-attach-note">
              <input type="text" name="attachment_note" class="form-control form-control-sm"
                     placeholder="e.g. Certificate PDF — see Student Portal">
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" onclick="previewBcast()">
              <i class="bi bi-eye me-1"></i>Preview
            </button>
            <button type="submit" class="btn btn-warning flex-fill" id="btn-broadcast-send"
                    onclick="return confirm('Send this broadcast?\n\nThis will notify all selected recipients.')">
              <i class="bi bi-megaphone me-2"></i>Send Broadcast
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ── RIGHT: Info panel ──────────────────────────────────── -->
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:8px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-info-circle me-2" style="color:var(--primary);"></i>Broadcast Groups
        </div>
        <?php
        $groups = [
            ['all_students',      'bi-mortarboard',    'All Students',              'All registered students in the system.'],
            ['all_reviewers',     'bi-person-check',   'All Reviewers',             'All reviewers assigned to evaluate applications.'],
            ['awarded_students',  'bi-trophy',         'Awarded Students',          'Students whose applications were awarded across all programs.'],
            ['program_applicants','bi-folder2-open',   'Program Applicants',        'All students who applied to a specific scholarship program.'],
        ];
        foreach ($groups as [$key, $icon, $name, $desc]): ?>
          <div style="padding:8px 0;border-bottom:1px solid var(--gray-100);display:flex;gap:10px;">
            <div style="width:32px;height:32px;background:rgba(37,99,235,.08);border-radius:8px;
                         display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="bi <?= $icon ?>" style="color:var(--primary);font-size:14px;"></i>
            </div>
            <div>
              <div style="font-weight:700;font-size:13px;"><?= $name ?></div>
              <div style="font-size:12px;color:var(--gray-400);"><?= $desc ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="card-title mb-2" style="padding-bottom:8px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-shield-check me-2" style="color:var(--success);"></i>Internal System Only
        </div>
        <ul style="font-size:12.5px;color:var(--gray-500);padding-left:18px;margin:0;line-height:2.1;">
          <li>No SMTP. No Gmail. No external services.</li>
          <li>Messages delivered to recipient <strong>Notifications</strong> panel.</li>
          <li>All communications logged in <strong>History</strong>.</li>
          <li>Read tracking shows who has seen the broadcast.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Preview Modal -->
<div id="bcast-preview-modal" style="display:none;position:fixed;inset:0;z-index:9999;
     background:rgba(0,0,0,.55);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:14px;width:min(640px,96vw);max-height:85vh;
               overflow-y:auto;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.3);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <strong style="font-size:15px;"><i class="bi bi-eye me-2" style="color:var(--primary);"></i>Broadcast Preview</strong>
      <button onclick="document.getElementById('bcast-preview-modal').style.display='none'"
              class="btn btn-sm btn-secondary"><i class="bi bi-x-lg"></i></button>
    </div>
    <div style="background:var(--gray-50);border-radius:8px;padding:10px 14px;font-size:12.5px;margin-bottom:12px;">
      <strong>Subject:</strong> <span id="bcast-prev-subject" style="color:var(--primary);"></span>
    </div>
    <div style="border:1px solid var(--gray-200);border-radius:8px;padding:20px;
                 font-size:13.5px;line-height:1.8;white-space:pre-wrap;" id="bcast-prev-body"></div>
  </div>
</div>

<script>
document.getElementById('bcast-tpl').addEventListener('change', function() {
    const o = this.options[this.selectedIndex];
    if (o.value) {
        document.getElementById('bcast-subject').value = o.dataset.subject || '';
        document.getElementById('bcast-body').value    = o.dataset.body    || '';
    }
});

function insertBcastVar(v) {
    const ta = document.getElementById('bcast-body');
    const s = ta.selectionStart, e = ta.selectionEnd;
    ta.value = ta.value.slice(0,s) + v + ta.value.slice(e);
    ta.selectionStart = ta.selectionEnd = s + v.length;
    ta.focus();
}

function updateGroupInfo() {
    const g   = document.getElementById('target-group').value;
    const pid = document.getElementById('bcast-prog').value;
    const pfld = document.getElementById('program-field');
    const rcDiv = document.getElementById('recipient-count');
    const rcVal = document.getElementById('count-val');

    pfld.classList.toggle('d-none', g !== 'program_applicants');

    if (!g) { rcDiv.style.display = 'none'; return; }

    let url = `broadcast.php?count_group=${encodeURIComponent(g)}`;
    if (g === 'program_applicants' && pid) url += `&program_id=${pid}`;

    fetch(url)
        .then(r => r.json())
        .then(data => {
            rcVal.textContent = data.count;
            rcDiv.style.display = 'block';
        }).catch(() => { rcDiv.style.display = 'none'; });
}

function previewBcast() {
    document.getElementById('bcast-prev-subject').textContent = document.getElementById('bcast-subject').value;
    document.getElementById('bcast-prev-body').textContent    = document.getElementById('bcast-body').value;
    document.getElementById('bcast-preview-modal').style.display = 'flex';
}
document.getElementById('bcast-preview-modal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php require_once '../../includes/footer.php'; ?>
