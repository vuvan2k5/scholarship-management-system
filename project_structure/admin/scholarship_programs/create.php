<?php
// ============================================================
// admin/scholarship_programs/create.php
// Admin creates a new scholarship program with full fields.
// ============================================================
$pageTitle = 'Create Scholarship Program';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo   = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $name        = trim($_POST['name']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $budget      = (float)($_POST['budget']   ?? 0);
    $slots       = (int)($_POST['slots']      ?? 0);
    $start_date  = trim($_POST['start_date']  ?? '');
    $end_date    = trim($_POST['end_date']    ?? '');
    $status      = trim($_POST['status']      ?? 'open');

    $validStatuses = ['open','closed','draft','suspended'];
    if (!in_array($status, $validStatuses)) $status = 'open';

    if (empty($name)) {
        $error = 'Program name is required.';
    } elseif ($budget <= 0) {
        $error = 'Budget must be greater than 0.';
    } elseif ($slots <= 0) {
        $error = 'Quota (slots) must be at least 1.';
    } elseif ($start_date && $end_date && $end_date < $start_date) {
        $error = 'End date cannot be before start date.';
    } else {
        $pdo->prepare("
            INSERT INTO scholarship_programs (name, description, budget, slots, start_date, end_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $name, $description, $budget, $slots,
            $start_date ?: null, $end_date ?: null, $status
        ]);

        $newId = (int)$pdo->lastInsertId();
        setFlash('success', "Program \"$name\" created successfully.");
        header("Location: view.php?id=$newId");
        exit;
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Create Scholarship Program</h1>
    <p class="page-subtitle">Define a new scholarship program with budget, quota, and application period.</p>
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

      <div class="form-section-title"><i class="bi bi-award me-2"></i>Program Details</div>

      <form method="POST" novalidate>

        <div class="mb-4">
          <label class="form-label">Program Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control"
                 placeholder="e.g. Academic Excellence Scholarship 2026"
                 value="<?= e($_POST['name'] ?? '') ?>" required>
        </div>

        <div class="mb-4">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="4"
                    placeholder="Brief overview of the scholarship program, target recipients, and requirements."><?= e($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="row">
          <div class="col-md-6 mb-4">
            <label class="form-label">Budget (VND) <span class="text-danger">*</span></label>
            <input type="number" name="budget" class="form-control" min="1"
                   placeholder="e.g. 10000000"
                   value="<?= e($_POST['budget'] ?? '') ?>" required>
          </div>
          <div class="col-md-6 mb-4">
            <label class="form-label">Quota — Max Recipients <span class="text-danger">*</span></label>
            <input type="number" name="slots" class="form-control" min="1"
                   placeholder="e.g. 5"
                   value="<?= e($_POST['slots'] ?? '') ?>" required>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-4">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= e($_POST['start_date'] ?? '') ?>">
          </div>
          <div class="col-md-4 mb-4">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?= e($_POST['end_date'] ?? '') ?>">
          </div>
          <div class="col-md-4 mb-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="open"      <?= ($_POST['status'] ?? 'open') === 'open'      ? 'selected' : '' ?>>Active (Open)</option>
              <option value="draft"     <?= ($_POST['status'] ?? '')     === 'draft'     ? 'selected' : '' ?>>Draft</option>
              <option value="closed"    <?= ($_POST['status'] ?? '')     === 'closed'    ? 'selected' : '' ?>>Closed</option>
              <option value="suspended" <?= ($_POST['status'] ?? '')     === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            </select>
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" name="submit" class="btn btn-primary" id="btn-create-submit">
            <i class="bi bi-check-lg me-1"></i> Create Program
          </button>
          <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>

      </form>
    </div>

    <!-- Info tip -->
    <div class="alert alert-info mt-3" style="border-left:4px solid var(--info);font-size:13px;">
      <i class="bi bi-info-circle me-2"></i>
      After creating the program, you can add <strong>Eligibility Rules</strong> and
      <strong>Scoring Criteria</strong> from the program detail page.
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>