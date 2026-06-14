$pageTitle = 'Ranking Results';

require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/ranking.php';

requireLogin();
requireRole('admin');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

$pdo = getDB();

// ── Generate ranking for ALL programs ────────────────────────
if (isset($_GET['generate_all'])) {
    $summaries = generateAllRankings($pdo);
    $totalRanked = array_sum(array_column($summaries, 'ranked'));
    setFlash('success', "Auto Ranking generated for all programs. Total ranked: {$totalRanked} applications.");
    header("Location: index.php");
    exit;
}

// ── Generate ranking for ONE specific program ─────────────────
if (isset($_GET['generate_program'])) {
    $pid = (int)$_GET['generate_program'];
    $result = generateRanking($pdo, $pid);
    if (!empty($result['errors'])) {
        setFlash('error', implode(' ', $result['errors']));
    } else {
        setFlash('success', "Ranking generated for \"{$result['program']}\": {$result['ranked']} ranked, {$result['recommended']} recommended.");
    }
    header("Location: index.php");
    exit;
}

$sql = "
    SELECT rr.*, a.id AS application_number, a.program_id, u.full_name AS student_name, sp.name AS program_name
    FROM ranking_results rr
    INNER JOIN applications a ON rr.application_id = a.id
    INNER JOIN users u ON a.student_id = u.id
    INNER JOIN scholarship_programs sp ON a.program_id = sp.id
    ORDER BY sp.id ASC, rr.rank ASC, rr.total_score DESC
";
$rankings = $pdo->query($sql)->fetchAll();
?>

<?php
// Fetch programs for per-program generate dropdown
$programs = $pdo->query("SELECT id, name FROM scholarship_programs ORDER BY id ASC")->fetchAll();
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Ranking Results</h1>
    <p class="page-subtitle">Ranked applicants based on weighted evaluation scores.</p>
  </div>
  <div class="d-flex gap-2 align-items-center">
    <!-- Per-program generate -->
    <form method="GET" action="index.php" class="d-flex gap-2">
      <select name="generate_program" class="form-select form-select-sm" style="width:220px;">
        <option value="">— Select Program —</option>
        <?php foreach ($programs as $pg): ?>
          <option value="<?= $pg['id'] ?>"><?= e($pg['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-sm btn-outline-primary"
              onclick="return confirm('Generate ranking for selected program?');">
        <i class="bi bi-play-fill me-1"></i> Run
      </button>
    </form>
    <!-- Generate ALL -->
    <a href="index.php?generate_all=1" class="btn btn-primary"
       onclick="return confirm('Recalculate rankings for ALL programs?');">
      <i class="bi bi-magic me-1"></i> Generate All Rankings
    </a>
  </div>
</div>

<div class="table-card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Rank</th><th>Application</th><th>Student</th><th>Program</th><th>Total Score</th><th>Recommended</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rankings as $row): ?>
          <tr>
            <td>
              <?php $rank = $row['rank']; ?>
              <span class="badge <?= $rank == 1 ? 'badge-warning' : 'badge-info' ?>" style="font-size:13px;">#<?= e($rank) ?></span>
            </td>
            <td><a href="../applications/index.php?search=<?= e($row['application_id']) ?>" class="fw-semibold text-primary">#<?= e($row['application_id']) ?></a></td>
            <td><strong><?= e($row['student_name']) ?></strong></td>
            <td><?= e($row['program_name']) ?></td>
            <td><strong><?= e($row['total_score']) ?> / 100</strong></td>
            <td>
              <?php if ($row['recommended']): ?>
                <span class="badge badge-eligible"><i class="bi bi-trophy me-1"></i>Recommended (Top <?= e($rank) ?>)</span>
              <?php else: ?>
                <span class="badge badge-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Waiting</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex gap-2">
                <a href="../reports/view.php?program_id=<?= $row['program_id'] ?? '' ?>&report_type=ranking" class="btn btn-sm btn-info text-white" target="_blank"><i class="bi bi-printer"></i> Print</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
