<?php
// ============================================================
// admin/scoring_criteria/create.php
// Create a scoring criterion with description, status & audit.
// Shows live weight-balance warning for the chosen program.
// ============================================================
$pageTitle = 'Create Scoring Criterion';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo   = getDB();
$error = '';

$programs = $pdo->query("SELECT id, name FROM scholarship_programs ORDER BY name ASC")->fetchAll();

// Pre-select program from ?program_id= (quick-link from program view)
$preProgram = (int)($_GET['program_id'] ?? 0);

// Per-program active weight totals for live warning (JSON for JS)
$weightData = [];
$wtRows = $pdo->query("
    SELECT program_id, SUM(weight) AS total_weight
    FROM scoring_criteria WHERE is_active = 1
    GROUP BY program_id
")->fetchAll();
foreach ($wtRows as $r) {
    $weightData[(int)$r['program_id']] = (float)$r['total_weight'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $program_id     = (int)($_POST['program_id']     ?? 0);
    $criterion_name = trim($_POST['criterion_name']  ?? '');
    $description    = trim($_POST['description']     ?? '');
    $weight         = (float)($_POST['weight']       ?? 0);
    $max_score      = (float)($_POST['max_score']    ?? 100);
    $is_active      = isset($_POST['is_active']) ? 1 : 0;
    $adminId        = currentUserId();

    if (!$program_id || !$criterion_name || $weight <= 0) {
        $error = 'Program, criterion name, and weight are required.';
    } elseif ($weight > 100) {
        $error = 'Weight cannot exceed 100%.';
    } else {
        $pdo->prepare("
            INSERT INTO scoring_criteria
                (program_id, criterion_name, description, weight, max_score, is_active, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $program_id, $criterion_name,
            $description ?: null, $weight, $max_score,
            $is_active, $adminId
        ]);

        // Calculate new total weight for this program
        $newTotal = $pdo->prepare("
            SELECT SUM(weight) FROM scoring_criteria
            WHERE program_id = ? AND is_active = 1
        ");
        $newTotal->execute([$program_id]);
        $total = (float)$newTotal->fetchColumn();

        if (abs($total - 100) < 0.01) {
            setFlash('success', "Criterion created. Total active weight for this program is exactly 100% ✓");
        } else {
            setFlash('warning', "Criterion created. Note: total active weight for this program is now " . number_format($total, 1) . "% — adjust other criteria to reach 100%.");
        }

        header('Location: index.php?program_id=' . $program_id);
        exit;
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Create Scoring Criterion</h1>
    <p class="page-subtitle">Define a reviewer grading metric. Criteria weights must total 100% per program.</p>
  </div>
  <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger mb-4" style="border-left:4px solid var(--danger);">
    <i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?>
  </div>
<?php endif; ?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="form-card">

      <div class="form-section-title"><i class="bi bi-star-half me-2"></i>Criterion Configuration</div>

      <form method="POST" novalidate>

        <!-- Program -->
        <div class="mb-4">
          <label class="form-label">Scholarship Program <span class="text-danger">*</span></label>
          <select name="program_id" id="prog-select" class="form-select" required
                  onchange="updateWeightWarning(this.value)">
            <option value="">— Select Program —</option>
            <?php foreach ($programs as $p): ?>
              <option value="<?= $p['id'] ?>"
                      <?= ((int)($_POST['program_id'] ?? $preProgram)) === (int)$p['id'] ? 'selected' : '' ?>>
                <?= e($p['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <!-- Live weight indicator -->
          <div id="weight-info" style="margin-top:6px;font-size:12.5px;display:none;">
            Current active criteria total: <strong id="weight-total">0%</strong>
            <span id="weight-remaining"></span>
          </div>
        </div>

        <!-- Criterion Name -->
        <div class="mb-4">
          <label class="form-label">Criterion Name <span class="text-danger">*</span></label>
          <input type="text" name="criterion_name" class="form-control"
                 placeholder="e.g. GPA, Research Output, Interview Score"
                 value="<?= e($_POST['criterion_name'] ?? '') ?>" required>
        </div>

        <!-- Description -->
        <div class="mb-4">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3"
                    placeholder="Briefly explain what this criterion measures and how reviewers should score it."><?= e($_POST['description'] ?? '') ?></textarea>
        </div>

        <!-- Weight + Max Score -->
        <div class="row">
          <div class="col-md-6 mb-4">
            <label class="form-label">Weight (%) <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="number" step="0.01" min="0.01" max="100" name="weight" id="weight-input"
                     class="form-control" placeholder="e.g. 40"
                     value="<?= e($_POST['weight'] ?? '') ?>"
                     oninput="updateWeightWarning(document.getElementById('prog-select').value)"
                     required>
              <span class="input-group-text">%</span>
            </div>
            <div class="form-text">Total active weights per program must equal 100%.</div>
          </div>
          <div class="col-md-6 mb-4">
            <label class="form-label">Maximum Score</label>
            <input type="number" step="0.1" min="1" name="max_score" class="form-control"
                   value="<?= e($_POST['max_score'] ?? '100') ?>">
            <div class="form-text">The highest score a reviewer can assign for this criterion.</div>
          </div>
        </div>

        <!-- Status -->
        <div class="mb-4">
          <div class="form-check form-switch" style="padding-left:2.5rem;">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                   <?= !isset($_POST['submit']) || isset($_POST['is_active']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active" style="font-weight:600;">
              Criterion is Active
            </label>
          </div>
          <div class="form-text" style="margin-left:2.5rem;">
            Only active criteria are counted toward the total weight and used in reviewer evaluations.
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" name="submit" class="btn btn-primary" id="btn-create-criterion">
            <i class="bi bi-check-lg me-1"></i> Create Criterion
          </button>
          <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>

      </form>
    </div>

    <div class="alert alert-info mt-3" style="border-left:4px solid var(--info);font-size:13px;">
      <i class="bi bi-cpu me-2"></i>
      <strong>Scoring Engine:</strong> Criteria weights directly affect how reviewer scores are aggregated
      into total evaluation scores, which drive the Ranking Engine.
    </div>
  </div>
</div>

<script>
const weightData = <?= json_encode($weightData, JSON_NUMERIC_CHECK) ?>;

function updateWeightWarning(progId) {
    const info      = document.getElementById('weight-info');
    const totalEl   = document.getElementById('weight-total');
    const remainEl  = document.getElementById('weight-remaining');
    const newWeight = parseFloat(document.getElementById('weight-input').value) || 0;

    if (!progId) { info.style.display = 'none'; return; }

    const current  = weightData[parseInt(progId)] || 0;
    const newTotal = current + newWeight;
    const remain   = 100 - newTotal;

    info.style.display = 'block';
    totalEl.textContent = newTotal.toFixed(1) + '%';
    totalEl.style.color = Math.abs(newTotal - 100) < 0.01 ? 'var(--success)' : (newTotal > 100 ? 'var(--danger)' : 'var(--gray-700)');

    if (Math.abs(newTotal - 100) < 0.01) {
        remainEl.innerHTML = ' <span style="color:var(--success);font-weight:700;">✓ Exactly 100%</span>';
    } else if (remain > 0) {
        remainEl.innerHTML = ` <span style="color:var(--warning);font-weight:600;">(${remain.toFixed(1)}% still needed)</span>`;
    } else {
        remainEl.innerHTML = ` <span style="color:var(--danger);font-weight:600;">(${Math.abs(remain).toFixed(1)}% over budget!)</span>`;
    }
}

// Trigger on page load if program is pre-selected
document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('prog-select');
    if (sel && sel.value) updateWeightWarning(sel.value);
});
</script>

<?php require_once '../../includes/footer.php'; ?>