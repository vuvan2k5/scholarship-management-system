<?php
// ============================================================
// admin/scoring_criteria/edit.php
// Edit a scoring criterion with description, status & audit.
// Shows live weight-balance warning excluding own current weight.
// ============================================================
$pageTitle = 'Edit Scoring Criterion';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo   = getDB();
$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';

// Fetch criterion
$stmt = $pdo->prepare("SELECT * FROM scoring_criteria WHERE id = ?");
$stmt->execute([$id]);
$c = $stmt->fetch();

if (!$c) {
    require_once '../../includes/header.php';
    require_once '../../includes/navbar.php';
    echo '<div class="page-header"><div class="page-header-left">
          <h1 class="page-title">Not Found</h1></div>
          <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a></div>
          <div class="alert alert-danger">Scoring criterion not found.</div>';
    require_once '../../includes/footer.php';
    exit;
}

// Fetch updated_by name
$updatedByName = null;
if (!empty($c['updated_by'])) {
    $us = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $us->execute([$c['updated_by']]);
    $updatedByName = $us->fetchColumn();
}

$programs = $pdo->query("SELECT id, name FROM scholarship_programs ORDER BY name ASC")->fetchAll();

// Per-program active weight totals (excluding this criterion to avoid double-count)
$weightData = [];
$wtRows = $pdo->query("
    SELECT program_id, SUM(weight) AS total_weight
    FROM scoring_criteria
    WHERE is_active = 1
    GROUP BY program_id
")->fetchAll();
foreach ($wtRows as $r) {
    $weightData[(int)$r['program_id']] = (float)$r['total_weight'];
}
// Subtract own weight from own program to allow correct remaining calculation
if (($c['is_active'] ?? 1) && isset($weightData[(int)$c['program_id']])) {
    $weightData[(int)$c['program_id']] -= (float)$c['weight'];
    if ($weightData[(int)$c['program_id']] < 0) $weightData[(int)$c['program_id']] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $program_id     = (int)($_POST['program_id']    ?? 0);
    $criterion_name = trim($_POST['criterion_name'] ?? '');
    $description    = trim($_POST['description']    ?? '');
    $weight         = (float)($_POST['weight']      ?? 0);
    $max_score      = (float)($_POST['max_score']   ?? 100);
    $is_active      = isset($_POST['is_active']) ? 1 : 0;
    $adminId        = currentUserId();

    if (!$program_id || !$criterion_name || $weight <= 0) {
        $error = 'Program, criterion name, and weight are required.';
    } elseif ($weight > 100) {
        $error = 'Weight cannot exceed 100%.';
    } else {
        $pdo->prepare("
            UPDATE scoring_criteria
            SET program_id = ?, criterion_name = ?, description = ?,
                weight = ?, max_score = ?, is_active = ?,
                updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([
            $program_id, $criterion_name, $description ?: null,
            $weight, $max_score, $is_active, $adminId, $id
        ]);

        $newTotal = $pdo->prepare("
            SELECT SUM(weight) FROM scoring_criteria
            WHERE program_id = ? AND is_active = 1
        ");
        $newTotal->execute([$program_id]);
        $total = (float)$newTotal->fetchColumn();

        if (abs($total - 100) < 0.01) {
            setFlash('success', "Criterion updated. Total active weight is exactly 100% ✓");
        } else {
            setFlash('warning', "Criterion updated. Total active weight is now " . number_format($total, 1) . "% — adjust other criteria to reach 100%.");
        }
        header('Location: index.php');
        exit;
    }

    // Preserve POST on error
    $c = array_merge($c, $_POST, ['id' => $id]);
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Edit Criterion #<?= e($id) ?></h1>
    <p class="page-subtitle">Modify scoring metric. Changes affect future evaluations and ranking calculations.</p>
  </div>
  <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger mb-4" style="border-left:4px solid var(--danger);">
    <i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?>
  </div>
<?php endif; ?>

<!-- Audit banner -->
<?php if ($updatedByName || !empty($c['updated_at'])): ?>
  <div class="alert alert-info mb-4" style="border-left:4px solid var(--info);font-size:13px;">
    <i class="bi bi-clock-history me-2"></i>
    <strong>Last modified</strong>
    <?php if ($updatedByName): ?>by <strong><?= e($updatedByName) ?></strong><?php endif; ?>
    <?php if (!empty($c['updated_at'])): ?>
      on <?= e(date('d M Y, H:i', strtotime($c['updated_at']))) ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="form-card">

      <div class="form-section-title"><i class="bi bi-pencil me-2"></i>Criterion Configuration</div>

      <form method="POST" novalidate>

        <!-- Program -->
        <div class="mb-4">
          <label class="form-label">Scholarship Program <span class="text-danger">*</span></label>
          <select name="program_id" id="prog-select" class="form-select" required
                  onchange="updateWeightWarning(this.value)">
            <option value="">— Select Program —</option>
            <?php foreach ($programs as $p): ?>
              <option value="<?= $p['id'] ?>"
                      <?= (int)$c['program_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                <?= e($p['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div id="weight-info" style="margin-top:6px;font-size:12.5px;display:none;">
            Other active criteria total: <strong id="weight-others">0%</strong> ·
            With this criterion: <strong id="weight-total">—</strong>
            <span id="weight-remaining"></span>
          </div>
        </div>

        <!-- Criterion Name -->
        <div class="mb-4">
          <label class="form-label">Criterion Name <span class="text-danger">*</span></label>
          <input type="text" name="criterion_name" class="form-control"
                 value="<?= e($c['criterion_name']) ?>" required>
        </div>

        <!-- Description -->
        <div class="mb-4">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3"
                    placeholder="Briefly explain what this criterion measures."><?= e($c['description'] ?? '') ?></textarea>
        </div>

        <!-- Weight + Max Score -->
        <div class="row">
          <div class="col-md-6 mb-4">
            <label class="form-label">Weight (%) <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="number" step="0.01" min="0.01" max="100" name="weight" id="weight-input"
                     class="form-control" value="<?= e($c['weight']) ?>"
                     oninput="updateWeightWarning(document.getElementById('prog-select').value)"
                     required>
              <span class="input-group-text">%</span>
            </div>
          </div>
          <div class="col-md-6 mb-4">
            <label class="form-label">Maximum Score</label>
            <input type="number" step="0.1" min="1" name="max_score" class="form-control"
                   value="<?= e($c['max_score']) ?>">
          </div>
        </div>

        <!-- Status -->
        <div class="mb-4">
          <div class="form-check form-switch" style="padding-left:2.5rem;">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                   <?= ($c['is_active'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active" style="font-weight:600;">
              Criterion is Active
            </label>
          </div>
          <div class="form-text" style="margin-left:2.5rem;">
            Deactivating removes this criterion from weight calculations and future evaluations.
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" name="submit" class="btn btn-primary" id="btn-update-criterion">
            <i class="bi bi-check-lg me-1"></i> Update Criterion
          </button>
          <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>

      </form>
    </div>
  </div>
</div>

<script>
// weightData excludes this criterion's own weight for the current program
const weightData = <?= json_encode($weightData, JSON_NUMERIC_CHECK) ?>;

function updateWeightWarning(progId) {
    const info       = document.getElementById('weight-info');
    const othersEl   = document.getElementById('weight-others');
    const totalEl    = document.getElementById('weight-total');
    const remainEl   = document.getElementById('weight-remaining');
    const newWeight  = parseFloat(document.getElementById('weight-input').value) || 0;

    if (!progId) { info.style.display = 'none'; return; }

    const others   = weightData[parseInt(progId)] || 0;
    const newTotal = others + newWeight;
    const remain   = 100 - newTotal;

    info.style.display  = 'block';
    othersEl.textContent = others.toFixed(1) + '%';
    totalEl.textContent  = newTotal.toFixed(1) + '%';
    totalEl.style.color  = Math.abs(newTotal - 100) < 0.01 ? 'var(--success)' : (newTotal > 100 ? 'var(--danger)' : 'var(--gray-700)');

    if (Math.abs(newTotal - 100) < 0.01) {
        remainEl.innerHTML = ' <span style="color:var(--success);font-weight:700;">✓ Exactly 100%</span>';
    } else if (remain > 0) {
        remainEl.innerHTML = ` <span style="color:var(--warning);font-weight:600;">(${remain.toFixed(1)}% remaining)</span>`;
    } else {
        remainEl.innerHTML = ` <span style="color:var(--danger);font-weight:600;">(${Math.abs(remain).toFixed(1)}% over!)</span>`;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('prog-select');
    if (sel && sel.value) updateWeightWarning(sel.value);
});
</script>

<?php require_once '../../includes/footer.php'; ?>