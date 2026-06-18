<?php
// ============================================================
// admin/eligibility_rules/create.php
// Create an eligibility rule with status + audit fields.
// ============================================================
$pageTitle = 'Create Eligibility Rule';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo   = getDB();
$error = '';

// Human-friendly labels shared with form
$ruleTypes = [
    'gpa'                  => 'GPA Requirement',
    'activities_count'     => 'Activity Requirement',
    'family_income'        => 'Income Requirement',
    'language_certificate' => 'Language Certificate',
    'research_count'       => 'Research Experience',
    'failed_subjects'      => 'Max Failed Subjects',
];

$programs = $pdo->query("SELECT id, name, slots FROM scholarship_programs ORDER BY name ASC")->fetchAll();

// Pre-select program from ?program_id= (from program view.php quick link)
$preProgram = (int)($_GET['program_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $program_id = (int)($_POST['program_id'] ?? 0);
    $rule_type  = trim($_POST['rule_type']  ?? '');
    $operator   = trim($_POST['operator']   ?? '');
    $value      = trim($_POST['value']      ?? '');
    $is_active  = isset($_POST['is_active']) ? 1 : 0;
    $adminId    = currentUserId();

    if (!$program_id || !$rule_type || !$operator || $value === '') {
        $error = 'Please fill in all required fields.';
    } else {
        $pdo->prepare("
            INSERT INTO eligibility_rules (program_id, rule_type, operator, value, is_active, updated_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$program_id, $rule_type, $operator, $value, $is_active, $adminId]);

        setFlash('success', 'Eligibility rule created. It will take effect on the next eligibility check run.');
        header('Location: index.php?program_id=' . $program_id);
        exit;
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Create Eligibility Rule</h1>
    <p class="page-subtitle">Define a new candidate filtering standard. Active rules are immediately enforced by the Eligibility Engine.</p>
  </div>
  <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger mb-4" style="border-left:4px solid var(--danger);">
    <i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?>
  </div>
<?php endif; ?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="form-card">

      <div class="form-section-title"><i class="bi bi-funnel me-2"></i>Rule Configuration</div>

      <form method="POST" novalidate>

        <!-- Program -->
        <div class="mb-4">
          <label class="form-label">Scholarship Program <span class="text-danger">*</span></label>
          <select name="program_id" class="form-select" required>
            <option value="">— Select Program —</option>
            <?php foreach ($programs as $p): ?>
              <option value="<?= $p['id'] ?>"
                      <?= ((int)($_POST['program_id'] ?? $preProgram)) === (int)$p['id'] ? 'selected' : '' ?>>
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
                      <?= ($_POST['rule_type'] ?? '') === $val ? 'selected' : '' ?>>
                <?= e($label) ?>
                <span style="color:#94a3b8;"> (<?= $val ?>)</span>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">This maps directly to a <code>student_profiles</code> column used by the eligibility engine.</div>
        </div>

        <!-- Operator + Value -->
        <div class="row">
          <div class="col-md-5 mb-4">
            <label class="form-label">Operator <span class="text-danger">*</span></label>
            <select name="operator" class="form-select" required>
              <option value=">=" <?= ($_POST['operator'] ?? '') === '>=' ? 'selected' : '' ?>>≥ — Greater than or equal</option>
              <option value="<=" <?= ($_POST['operator'] ?? '') === '<=' ? 'selected' : '' ?>>≤ — Less than or equal</option>
              <option value="="  <?= ($_POST['operator'] ?? '') === '='  ? 'selected' : '' ?>>=  — Exactly equal</option>
              <option value=">"  <?= ($_POST['operator'] ?? '') === '>'  ? 'selected' : '' ?>>›  — Strictly greater</option>
              <option value="<"  <?= ($_POST['operator'] ?? '') === '<'  ? 'selected' : '' ?>>‹  — Strictly less</option>
            </select>
          </div>
          <div class="col-md-7 mb-4">
            <label class="form-label">Threshold Value <span class="text-danger">*</span></label>
            <input type="text" name="value" class="form-control"
                   placeholder="e.g. 3.2  |  15000000  |  1"
                   value="<?= e($_POST['value'] ?? '') ?>" required>
            <div class="form-text">
              Numeric for GPA/income; <code>0</code> or <code>1</code> for boolean fields (language certificate).
            </div>
          </div>
        </div>

        <!-- Status -->
        <div class="mb-4">
          <div class="form-check form-switch" style="padding-left:2.5rem;">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                   value="1" <?= !isset($_POST['submit']) || isset($_POST['is_active']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active" style="font-weight:600;">
              Rule is Active
            </label>
          </div>
          <div class="form-text" style="margin-left:2.5rem;">
            Active rules are enforced by the Eligibility Engine immediately.
            Deactivate to pause a rule without deleting it.
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" name="submit" class="btn btn-primary" id="btn-create-rule">
            <i class="bi bi-check-lg me-1"></i> Create Rule
          </button>
          <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>

      </form>
    </div>

    <!-- Engine note -->
    <div class="alert alert-info mt-3" style="border-left:4px solid var(--info);font-size:13px;">
      <i class="bi bi-cpu me-2"></i>
      <strong>Eligibility Engine:</strong> New active rules apply to all <em>future</em> eligibility checks.
      Existing <code>eligibility_results</code> are not retroactively updated — re-run checks from the Applications module.
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>