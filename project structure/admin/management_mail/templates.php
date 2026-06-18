<?php
// ============================================================
// admin/management_mail/templates.php
// Manage email templates: create, edit, preview, toggle.
// ============================================================
$pageTitle = 'Email Templates';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo     = getDB();
$adminId = currentUserId();

// ── Handle form actions ───────────────────────────────────────
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Delete
if ($action === 'delete' && isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM mail_templates WHERE id = ?")->execute([(int)$_GET['id']]);
    setFlash('success', 'Template deleted.');
    header('Location: templates.php');
    exit;
}

// Toggle active
if ($action === 'toggle' && isset($_GET['id'])) {
    $pdo->prepare("UPDATE mail_templates SET is_active = 1 - is_active WHERE id = ?")->execute([(int)$_GET['id']]);
    header('Location: templates.php');
    exit;
}

// Save (create or update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['create', 'update'])) {
    $id      = (int)($_POST['id']            ?? 0);
    $name    = trim($_POST['name']           ?? '');
    $type    = $_POST['template_type']       ?? 'custom';
    $subject = trim($_POST['subject']        ?? '');
    $body    = trim($_POST['body_html']      ?? '');

    $validTypes = ['award_notification','rejection_notification','document_request','interview_invitation','custom'];
    if (!in_array($type, $validTypes)) $type = 'custom';

    if ($name && $subject && $body) {
        if ($action === 'create') {
            $pdo->prepare("INSERT INTO mail_templates (name,template_type,subject,body_html,created_by) VALUES(?,?,?,?,?)")
                ->execute([$name, $type, $subject, $body, $adminId]);
            setFlash('success', "Template \"{$name}\" created.");
        } else {
            $pdo->prepare("UPDATE mail_templates SET name=?,template_type=?,subject=?,body_html=?,updated_at=NOW() WHERE id=?")
                ->execute([$name, $type, $subject, $body, $id]);
            setFlash('success', "Template \"{$name}\" updated.");
        }
    } else {
        setFlash('error', 'Name, subject, and body are required.');
    }
    header('Location: templates.php');
    exit;
}

// Load for editing
$editTpl = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM mail_templates WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editTpl = $stmt->fetch();
}

$templates = $pdo->query("SELECT mt.*, u.full_name AS creator FROM mail_templates mt LEFT JOIN users u ON mt.created_by = u.id ORDER BY mt.template_type ASC, mt.id ASC")->fetchAll();

$typeLabels = [
    'award_notification'    => 'Award Notification',
    'rejection_notification'=> 'Rejection Notification',
    'document_request'      => 'Document Request',
    'interview_invitation'  => 'Interview Invitation',
    'custom'                => 'Custom',
];
$typeColors = [
    'award_notification'    => 'badge-eligible',
    'rejection_notification'=> 'badge-ineligible',
    'document_request'      => 'badge-warning',
    'interview_invitation'  => 'badge-info',
    'custom'                => 'badge-secondary',
];

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title"><i class="bi bi-layout-text-window me-2" style="color:var(--primary);"></i>Email Templates</h1>
    <p class="page-subtitle">Manage reusable email templates with dynamic {{variable}} support.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="index.php" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Back to Mail
    </a>
    <button class="btn btn-primary" id="btn-new-tpl"
            onclick="document.getElementById('tpl-form-card').scrollIntoView({behavior:'smooth'})">
      <i class="bi bi-plus-lg me-1"></i>New Template
    </button>
  </div>
</div>

<?php showFlash(); ?>

<div class="row g-4">

  <!-- ── LEFT: Template Form ──────────────────────────────── -->
  <div class="col-lg-5">
    <div class="card" id="tpl-form-card" style="position:sticky;top:80px;">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-<?= $editTpl ? 'pencil' : 'plus-circle' ?> me-2" style="color:var(--primary);"></i>
          <?= $editTpl ? 'Edit Template' : 'Create Template' ?>
        </div>
        <form method="POST">
          <input type="hidden" name="action" value="<?= $editTpl ? 'update' : 'create' ?>">
          <?php if ($editTpl): ?>
            <input type="hidden" name="id" value="<?= $editTpl['id'] ?>">
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label fw-semibold">Template Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control form-control-sm"
                   value="<?= $editTpl ? e($editTpl['name']) : '' ?>"
                   placeholder="e.g. Award Notification 2024" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Template Type <span class="text-danger">*</span></label>
            <select name="template_type" class="form-select form-select-sm">
              <?php foreach ($typeLabels as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($editTpl && $editTpl['template_type'] === $val) ? 'selected' : '' ?>>
                  <?= $label ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
            <input type="text" name="subject" class="form-control form-control-sm"
                   value="<?= $editTpl ? e($editTpl['subject']) : '' ?>"
                   placeholder="Email subject with {{variables}}" required>
          </div>

          <!-- Variable legend -->
          <div style="background:rgba(37,99,235,.04);border:1px solid rgba(37,99,235,.1);
                       border-radius:8px;padding:8px 12px;margin-bottom:10px;font-size:12px;">
            <div style="font-weight:700;margin-bottom:4px;color:var(--gray-700);">
              <i class="bi bi-braces me-1" style="color:var(--primary);"></i>Available Variables
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:4px;">
              <?php foreach (['{{student_name}}','{{student_id}}','{{program_name}}','{{rank}}','{{score}}'] as $v): ?>
                <code style="background:rgba(37,99,235,.08);border-radius:4px;padding:1px 6px;font-size:11px;
                             cursor:pointer;user-select:all;"
                      onclick="insertBodyVar('<?= $v ?>')" title="Click to insert"><?= $v ?></code>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Body (HTML) <span class="text-danger">*</span></label>
            <textarea name="body_html" id="tpl-body" class="form-control" rows="10"
                      placeholder="<p>Dear {{student_name}},</p>…" required><?= $editTpl ? e($editTpl['body_html']) : '' ?></textarea>
          </div>

          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="previewTpl()">
              <i class="bi bi-eye me-1"></i>Preview
            </button>
            <button type="submit" class="btn btn-primary flex-fill">
              <i class="bi bi-save me-1"></i><?= $editTpl ? 'Update Template' : 'Save Template' ?>
            </button>
            <?php if ($editTpl): ?>
              <a href="templates.php" class="btn btn-secondary btn-sm">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div><!-- /col-lg-5 -->

  <!-- ── RIGHT: Template List ─────────────────────────────── -->
  <div class="col-lg-7">
    <div class="table-card">
      <div class="table-card-header">
        <span class="table-card-title">All Templates</span>
        <span style="font-size:12px;color:var(--gray-400);"><?= count($templates) ?> templates</span>
      </div>
      <div class="table-responsive">
        <table class="table" style="font-size:12.5px;">
          <thead>
            <tr>
              <th>Name</th>
              <th>Type</th>
              <th>Subject</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($templates)): ?>
              <tr>
                <td colspan="5">
                  <div class="empty-state" style="padding:40px 16px;">
                    <span class="empty-state-icon"><i class="bi bi-layout-text-window"></i></span>
                    <div class="empty-state-title">No templates yet</div>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($templates as $t):
                $tClass = $typeColors[$t['template_type']] ?? 'badge-info';
                $tLabel = $typeLabels[$t['template_type']] ?? $t['template_type'];
              ?>
                <tr style="<?= !$t['is_active'] ? 'opacity:.5;' : '' ?>">
                  <td>
                    <strong><?= e($t['name']) ?></strong>
                    <?php if ($t['creator']): ?>
                      <div style="font-size:10.5px;color:var(--gray-400);">By <?= e($t['creator']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge <?= $tClass ?>" style="font-size:10px;"><?= $tLabel ?></span></td>
                  <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;"
                      title="<?= e($t['subject']) ?>"><?= e($t['subject']) ?></td>
                  <td>
                    <span class="badge <?= $t['is_active'] ? 'badge-eligible' : 'badge-warning' ?>" style="font-size:10px;">
                      <?= $t['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                  </td>
                  <td>
                    <div class="d-flex gap-1 flex-wrap">
                      <a href="templates.php?edit=<?= $t['id'] ?>"
                         class="btn btn-xs btn-outline-primary" style="padding:3px 8px;font-size:11px;"
                         id="edit-tpl-<?= $t['id'] ?>">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <a href="templates.php?action=toggle&id=<?= $t['id'] ?>"
                         class="btn btn-xs btn-outline-secondary" style="padding:3px 8px;font-size:11px;"
                         title="<?= $t['is_active'] ? 'Deactivate' : 'Activate' ?>">
                        <i class="bi bi-toggle-<?= $t['is_active'] ? 'on' : 'off' ?>"></i>
                      </a>
                      <a href="templates.php?action=delete&id=<?= $t['id'] ?>"
                         class="btn btn-xs btn-outline-danger" style="padding:3px 8px;font-size:11px;"
                         id="del-tpl-<?= $t['id'] ?>"
                         onclick="return confirm('Delete this template permanently?')">
                        <i class="bi bi-trash"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div><!-- /col-lg-7 -->

</div><!-- /row -->

<!-- ── Preview Modal ─────────────────────────────────────────── -->
<div id="tpl-preview-modal" style="display:none;position:fixed;inset:0;z-index:9999;
     background:rgba(0,0,0,.55);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:14px;width:min(680px,96vw);max-height:88vh;
               overflow-y:auto;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.3);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <div style="font-weight:800;font-size:15px;"><i class="bi bi-eye me-2" style="color:var(--primary);"></i>Template Preview</div>
      <button onclick="document.getElementById('tpl-preview-modal').style.display='none'"
              class="btn btn-sm btn-secondary"><i class="bi bi-x-lg"></i></button>
    </div>
    <div style="background:var(--gray-50);border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;color:var(--gray-500);">
      Showing with sample values · <strong style="color:var(--primary);" id="tpl-preview-subj"></strong>
    </div>
    <div style="border:1px solid var(--gray-200);border-radius:8px;padding:20px;" id="tpl-preview-body"></div>
  </div>
</div>

<script>
const sampleVars = {
    '{{student_name}}': 'Nguyễn Văn An',
    '{{student_id}}':   'SV2024001',
    '{{program_name}}': 'Excellence Scholarship 2024',
    '{{rank}}': '3',
    '{{score}}': '87.50',
};
function applyVars(t) {
    Object.entries(sampleVars).forEach(([k,v]) => { t = t.replaceAll(k, v); });
    return t;
}
function insertBodyVar(v) {
    const ta = document.getElementById('tpl-body');
    const s = ta.selectionStart, e2 = ta.selectionEnd;
    ta.value = ta.value.slice(0,s) + v + ta.value.slice(e2);
    ta.selectionStart = ta.selectionEnd = s + v.length;
    ta.focus();
}
function previewTpl() {
    const subj = document.querySelector('[name="subject"]').value;
    const body = document.getElementById('tpl-body').value;
    document.getElementById('tpl-preview-subj').textContent = applyVars(subj);
    document.getElementById('tpl-preview-body').innerHTML   = applyVars(body);
    document.getElementById('tpl-preview-modal').style.display = 'flex';
}
document.getElementById('tpl-preview-modal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php require_once '../../includes/footer.php'; ?>
