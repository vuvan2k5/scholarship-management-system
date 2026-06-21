<?php
// ============================================================
// programs.php  –  Scholarship Programs List Page
// Accessible to logged-in users to view available programs.
// ============================================================

$pageTitle = 'Scholarship Programs';

require_once 'config/db.php';
require_once 'includes/auth.php';

// Ensure the user is logged in
requireLogin();

require_once 'includes/header.php';
require_once 'includes/navbar.php';

$pdo = getDB();

// Fetch programs from scholarship_programs table
try {
    $programs = $pdo->query("
        SELECT id, name, description, budget, slots, start_date, end_date, status
        FROM scholarship_programs
        ORDER BY status ASC, end_date ASC, id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $programs = [];
    $dbError = $e->getMessage();
}

$role = currentRole();
?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Scholarship Programs</h1>
    <p class="page-subtitle">Browse all available scholarship opportunities, eligibility requirements, and deadlines.</p>
  </div>
  <?php if ($role === 'student'): ?>
    <a href="<?= BASE_URL ?>/student/apply.php" class="btn btn-primary">
      <i class="bi bi-file-earmark-plus me-1"></i> Apply Scholarship
    </a>
  <?php endif; ?>
</div>

<?php if (isset($dbError)): ?>
  <div class="alert alert-danger" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> Error loading programs: <?= e($dbError) ?>
  </div>
<?php elseif (empty($programs)): ?>
  <div class="card">
    <div class="card-body">
      <div class="text-center py-5">
        <div class="fs-1 text-muted mb-3"><i class="bi bi-award"></i></div>
        <h4 class="fw-bold text-dark">No Programs Found</h4>
        <p class="text-muted">There are no scholarship programs currently registered in the system.</p>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="row g-4">
    <?php foreach ($programs as $program): ?>
      <div class="col-12 col-md-6 col-xxl-4">
        <div class="card h-100 d-flex flex-column">
          <div class="card-body d-flex flex-column p-4">
            
            <!-- Badge & ID -->
            <div class="d-flex justify-content-between align-items-center mb-3">
              <span class="text-muted fs-7 font-monospace">#<?= e($program['id']) ?></span>
              <?php
                if ($program['status'] === 'open') {
                    echo '<span class="badge badge-success"><i class="bi bi-check-circle me-1"></i>Open</span>';
                } else {
                    echo '<span class="badge badge-danger"><i class="bi bi-x-circle me-1"></i>Closed</span>';
                }
              ?>
            </div>

            <!-- Program Name -->
            <h3 class="h5 fw-bold text-dark mb-2" style="line-height: 1.4;"><?= e($program['name']) ?></h3>

            <!-- Description -->
            <p class="text-muted flex-grow-1 fs-6 mb-4" style="line-height: 1.6;">
              <?= $program['description'] ? nl2br(e($program['description'])) : '<em>No description available for this scholarship program.</em>' ?>
            </p>

            <div class="mt-auto border-top pt-3">
              <div class="row g-2 mb-3">
                <!-- Deadline -->
                <div class="col-6">
                  <div class="p-2 bg-light rounded-2 text-center h-100">
                    <div class="text-muted fs-7 mb-1"><i class="bi bi-calendar-event me-1"></i>Deadline</div>
                    <div class="fw-semibold text-dark fs-7">
                      <?= $program['end_date'] ? e(date('d/m/Y', strtotime($program['end_date']))) : 'N/A' ?>
                    </div>
                  </div>
                </div>
                <!-- Slots -->
                <div class="col-6">
                  <div class="p-2 bg-light rounded-2 text-center h-100">
                    <div class="text-muted fs-7 mb-1"><i class="bi bi-people me-1"></i>Slots</div>
                    <div class="fw-semibold text-dark fs-7"><?= e($program['slots']) ?> slots</div>
                  </div>
                </div>
              </div>

              <!-- Budget -->
              <div class="d-flex justify-content-between align-items-center mb-3 px-1">
                <span class="text-muted fs-7">Financial Support:</span>
                <span class="text-success fw-bold"><?= number_format($program['budget']) ?> VND</span>
              </div>

              <!-- Action button -->
              <?php if ($role === 'student'): ?>
                <?php if ($program['status'] === 'open'): ?>
                  <a href="<?= BASE_URL ?>/student/apply.php?program_id=<?= $program['id'] ?>" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-send-fill me-1"></i> Apply Now
                  </a>
                <?php else: ?>
                  <button class="btn btn-secondary w-100 py-2" disabled>
                    <i class="bi bi-lock-fill me-1"></i> Application Closed
                  </button>
                <?php endif; ?>
              <?php elseif ($role === 'admin'): ?>
                <a href="<?= BASE_URL ?>/admin/scholarship_programs/index.php" class="btn btn-outline-primary w-100 py-2">
                  <i class="bi bi-gear-fill me-1"></i> Manage Program
                </a>
              <?php endif; ?>

            </div>

          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>
