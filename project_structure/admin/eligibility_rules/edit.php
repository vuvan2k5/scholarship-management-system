<?php
// ============================================================
// admin/eligibility_rules/edit.php
// Edit an eligibility rule with status + audit fields.
// ============================================================
$pageTitle = 'Edit Eligibility Rule';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo   = getDB();
$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';

$ruleTypes = [
    'gpa'                  => 'GPA Requirement',
    'activities_count'     => 'Activity Requirement',
    'family_income'        => 'Income Requirement',
    'has_language_cert' => 'Language Certificate',
    'research_count'       => 'Research Experience',
    'failed_subjects'      => 'Max Failed Subjects',
];

// Fetch rule
$stmt = $pdo->prepare("SELECT * FROM eligibility_rules WHERE id = ?");
$stmt->execute([$id]);
$rule = $stmt->fetch();

if (!$rule) {
    require_once '../../includes/header.php';
    require_once '../../includes/navbar.php';
    echo '<div class="page-header"><div class="page-header-left">
          <h1 class="page-title">Not Found</h1></div>
          <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a></div>
          <div class="alert alert-danger">Eligibility rule not found.</div>';
    require_once '../../includes/footer.php';
    exit;
}

// Fetch updated_by name for audit display
$updatedByName = null;
if (!empty($rule['updated_by'])) {
    $us = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $us->execute([$rule['updated_by']]);
    $updatedByName = $us->fetchColumn();
}

$programs = $pdo->query("SELECT id, name, slots FROM scholarship_programs ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $program_id = (int)($_POST['program_id'] ?? 0);
    $rule_type  = trim($_POST['rule_type']   ?? '');
    $operator   = trim($_POST['operator']    ?? '');
    $value      = trim($_POST['value']       ?? '');
    $is_active  = isset($_POST['is_active']) ? 1 : 0;
    $adminId    = currentUserId();

    if (!$program_id || !$rule_type || !$operator || $value === '') {
        $error = 'Please fill in all required fields.';
    } else {
        $pdo->prepare("
            UPDATE eligibility_rules
            SET program_id = ?, rule_type = ?, operator = ?, value = ?,
                is_active = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$program_id, $rule_type, $operator, $value, $is_active, $adminId, $id]);

        setFlash('success', "Rule #$id updated. Changes take effect on the next eligibility check run.");
        header('Location: index.php');
        exit;
    }

    // Preserve POST on error
    $rule = array_merge($rule, $_POST, ['id' => $id]);
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

// Resolve label for current rule_type (may not be in standard list)
function ruleTypeLabel(string $type): string {
    $map = [
        'gpa'                  => 'GPA Requirement',
        'activities'           => 'Activity Requirement',
        'activities_count'     => 'Activity Requirement',
        'activity'             => 'Activity Requirement',
        'income'               => 'Income Requirement',
        'family_income'        => 'Income Requirement',
        'has_language_cert' => 'Language Certificate',
        'language_cert'        => 'Language Certificate',
        'research'             => 'Research Experience',
        'research_count'       => 'Research Experience',
        'research_projects'    => 'Research Experience',
        'failed_subjects'      => 'Max Failed Subjects',
    ];
    return $map[strtolower($type)] ?? ucwords(str_replace('_', ' ', $type));
}
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Edit Rule #<?= e($id) ?></h1>
    <p class="page-subtitle">Modify eligibility rule. Changes affect future eligibility evaluations immediately.</p>
  </div>
  <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger mb-4" style="border-left:4px solid var(--danger);">
    <i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?>
  </div>
<?php endif; ?>

<!-- Audit info banner -->
<?php if ($updatedByName || !empty($rule['updated_at'])): ?>
  <div class="alert alert-info mb-4" style="border-left:4px solid var(--info);font-size:13px;">
    <i class="bi bi-clock-history me-2"></i>
    <strong>Last modified</strong>
    <?php if ($updatedByName): ?>by <strong><?= e($updatedByName) ?></strong><?php endif; ?>
    <?php if (!empty($rule['updated_at'])): ?>
      on <?= e(date('d M Y, H:i', strtotime($rule['updated_at']))) ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="form-card">

      <div class="form-section-title"><i class="bi bi-pencil me-2"></i>Rule Configuration</div>

      <form method="POST" novalidate>

        <!-- Program -->
        <div class="mb-4">
          <label class="form-label">Scholarship Program <span class="text-danger">*</span></label>
          <select name="program_id" class="form-select" required>
            <option value="">— Select Program —</option>
            <?php foreach ($programs as $p): ?>
              <option value="<?= $p['id'] ?>"
                      <?= (int)$rule['program_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                <?= e($p['name']) ?> (<?= e($p['slots']) ?> slots)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Rule Type -->
        <div class="mb-4">
          <label class="form-label">Rule Type <span class="text-danger">*</span></label>
          <select name="rule_type" class="form-select" required>
            <option value="">— Select Rule Type —</option>
            <?php foreach ($ruleTypes as $val => $label): ?>
              <option value="<?= $val ?>"
                      <?= $rule['rule_type'] === $val ? 'selected' : '' ?>>
                <?= e($label) ?>
              </option>
            <?php endforeach; ?>
            <?php
            // If current rule_type is not in standard list, add it as option
            if (!array_key_exists($rule['rule_type'], $ruleTypes)): ?>
              <option value="<?= e($rule['rule_type']) ?>" selected>
                <?= e(ruleTypeLabel($rule['rule_type'])) ?> (custom)
              </option>
            <?php endif; ?>
          </select>
          <div class="form-text">Current stored key: <code><?= e($rule['rule_type']) ?></code></div>
        </div>

        <!-- Operator + Value -->
        <div class="row">
          <div class="col-md-5 mb-4">
            <label class="form-label">Operator <span class="text-danger">*</span></label>
            <select name="operator" class="form-select" required>
              <option value=">=" <?= $rule['operator'] === '>=' ? 'selected' : '' ?>>≥ — Greater than or equal</option>
              <option value="<=" <?= $rule['operator'] === '<=' ? 'selected' : '' ?>>≤ — Less than or equal</option>
              <option value="="  <?= $rule['operator'] === '='  ? 'selected' : '' ?>>=  — Exactly equal</option>
              <option value=">"  <?= $rule['operator'] === '>'  ? 'selected' : '' ?>>›  — Strictly greater</option>
              <option value="<"  <?= $rule['operator'] === '<'  ? 'selected' : '' ?>>‹  — Strictly less</option>
            </select>
          </div>
          <div class="col-md-7 mb-4">
            <label class="form-label">Threshold Value <span class="text-danger">*</span></label>
            <input type="text" name="value" class="form-control"
                   placeholder="e.g. 3.2  |  15000000  |  1"
                   value="<?= e($rule['value']) ?>" required>
          </div>
        </div>

        <!-- Status -->
        <div class="mb-4">
          <div class="form-check form-switch" style="padding-left:2.5rem;">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                   value="1" <?= ($rule['is_active'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active" style="font-weight:600;">
              Rule is Active
            </label>
          </div>
          <div class="form-text" style="margin-left:2.5rem;">
            Deactivating pauses this rule without deleting it. Future eligibility checks will skip it.
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" name="submit" class="btn btn-primary" id="btn-update-rule">
            <i class="bi bi-check-lg me-1"></i> Update Rule
          </button>
          <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>

      </form>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
