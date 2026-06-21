<?php
// ============================================================
// admin/scholarship_programs/edit.php
// Admin edits an existing scholarship program.
// ============================================================
$pageTitle = 'Edit Scholarship Program';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM scholarship_programs WHERE id = ?");
$stmt->execute([$id]);
$prog = $stmt->fetch();

if (!$prog) {
    require_once '../../includes/header.php';
    require_once '../../includes/navbar.php';
    echo '<div class="page-header"><div class="page-header-left"><h1 class="page-title">Not Found</h1></div>
          <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a></div>
          <div class="alert alert-danger">Program not found.</div>';
    require_once '../../includes/footer.php';
    exit;
}

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
    if (!in_array($status, $validStatuses)) $status = $prog['status'];

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
            UPDATE scholarship_programs
            SET name = ?, description = ?, budget = ?, slots = ?,
                start_date = ?, end_date = ?, status = ?
            WHERE id = ?
        ")->execute([
            $name, $description, $budget, $slots,
            $start_date ?: null, $end_date ?: null, $status, $id
        ]);

        setFlash('success', "Program \"$name\" updated successfully.");
        header("Location: view.php?id=$id");
        exit;
    }

    // Preserve POST values on error
    $prog = array_merge($prog, $_POST, ['id' => $id]);
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Edit Program</h1>
    <p class="page-subtitle">Modifying <strong><?= e($prog['name']) ?></strong> (Program #<?= e($id) ?>)</p>
  </div>
  <div class="d-flex gap-2">
    <a href="view.php?id=<?= $id ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
  </div>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger mb-4" style="border-left:4px solid var(--danger);">
    <i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?>
  </div>
<?php endif; ?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="form-card">

      <div class="form-section-title"><i class="bi bi-pencil me-2"></i>Program Details</div>

      <form method="POST" novalidate>

        <div class="mb-4">
          <label class="form-label">Program Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control"
                 value="<?= e($prog['name']) ?>" required>
        </div>

        <div class="mb-4">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="4"><?= e($prog['description']) ?></textarea>
        </div>

        <div class="row">
          <div class="col-md-6 mb-4">
            <label class="form-label">Budget (VND) <span class="text-danger">*</span></label>
            <input type="number" name="budget" class="form-control" min="1"
                   value="<?= e($prog['budget']) ?>" required>
          </div>
          <div class="col-md-6 mb-4">
            <label class="form-label">Quota — Max Recipients <span class="text-danger">*</span></label>
            <input type="number" name="slots" class="form-control" min="1"
                   value="<?= e($prog['slots']) ?>" required>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-4">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= e($prog['start_date']) ?>">
          </div>
          <div class="col-md-4 mb-4">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?= e($prog['end_date']) ?>">
          </div>
          <div class="col-md-4 mb-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="open"      <?= $prog['status'] === 'open'      ? 'selected' : '' ?>>Active (Open)</option>
              <option value="draft"     <?= $prog['status'] === 'draft'     ? 'selected' : '' ?>>Draft</option>
              <option value="closed"    <?= $prog['status'] === 'closed'    ? 'selected' : '' ?>>Closed</option>
              <option value="suspended" <?= $prog['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            </select>
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" name="submit" class="btn btn-primary" id="btn-update-submit">
            <i class="bi bi-check-lg me-1"></i> Update Program
          </button>
          <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
        </div>

      </form>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>