<?php
$pageTitle = 'Mock Eligibility & Ranking Results';
require_once __DIR__ . '/../includes/header.php';
//require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/mock_services.php';
?>
<?php require_once __DIR__ . '/../includes/student_header.php'; ?>

$slots = isset($_GET['slots']) ? max(1,intval($_GET['slots'])) : 3;

$data = rankAndSelect(require __DIR__ . '/mock_data.php', $slots);
$ranked = $data['ranked'];
$awardees = $data['awardees'];
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="page-title">Eligibility Check & Ranking (Mock)</h1>
    <form class="d-flex" method="get">
      <label class="me-2">Slots:</label>
      <input type="number" name="slots" value="<?= e($slots) ?>" min="1" class="form-control me-2" style="width:90px">
      <button class="btn btn-primary">Apply</button>
    </form>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <p class="mb-0">Rules: GPA &ge; 3.2; No F; Activities &ge; 2. Scoring factors: GPA, language certificate, activities, research topics.</p>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Rank</th>
          <th>Student</th>
          <th>GPA</th>
          <th>Failed</th>
          <th>Activities</th>
          <th>Research</th>
          <th>Lang Cert</th>
          <th>Eligibility</th>
          <th>Score</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ranked as $i => $r): ?>
          <tr>
            <td><?= e($i+1) ?></td>
            <td><?= e($r['full_name']) ?> <br><small class="text-muted"><?= e($r['email']) ?></small></td>
            <td><?= e($r['gpa']) ?></td>
            <td><?= e($r['failed_subjects']) ?></td>
            <td><?= e(count($r['activities'])) ?></td>
            <td><?= e(count($r['research_topics'])) ?></td>
            <td><?= $r['has_language_cert'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
            <td>
              <?php if ($r['eligibility']['passed']): ?>
                <span class="badge bg-success">Passed</span>
              <?php else: ?>
                <span class="badge bg-danger">Failed</span>
                <div class="small text-muted"><?= e(implode('; ', $r['eligibility']['reasons'])) ?></div>
              <?php endif; ?>
            </td>
            <td><strong><?= e($r['score']) ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card mt-4">
    <div class="card-body">
      <h5>Awardees (Top <?= e($slots) ?> eligible)</h5>
      <?php if (empty($awardees)): ?>
        <p class="text-muted">No eligible awardees found.</p>
      <?php else: ?>
        <ul>
          <?php foreach ($awardees as $a): ?>
            <li><?= e($a['full_name']) ?> — Score: <?= e($a['score']) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
