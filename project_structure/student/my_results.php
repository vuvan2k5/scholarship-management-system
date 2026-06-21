<?php
$pageTitle = 'My Results';

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requireRole('student');

$pdo       = getDB();
$studentId = currentUserId();

// Fetch all results with ranking & eligibility detail
$sql = "
    SELECT
        a.id, a.status, a.eligible, a.submitted_at,
        sp.name   AS program_name,
        sp.budget AS program_budget,
        sp.slots  AS program_slots,
        COALESCE(
            ROUND(SUM(es.score * sc.weight / 100), 2)
        , 0) AS weighted_score,
        COUNT(DISTINCT es.criteria_id) AS scored_criteria,
        rr.rank          AS my_rank,
        rr.total_score   AS rank_score,
        rr.recommended   AS is_recommended,
        er.is_passed     AS eligibility_passed,
        er.reason        AS eligibility_reason,
        d.status         AS disburse_status,
        d.amount         AS disburse_amount
    FROM applications a
    JOIN   scholarship_programs sp ON a.program_id = sp.id
    LEFT JOIN evaluation_scores es ON es.application_id = a.id
    LEFT JOIN scoring_criteria  sc ON sc.id = es.criteria_id AND sc.program_id = a.program_id
    LEFT JOIN ranking_results   rr ON rr.application_id = a.id
    LEFT JOIN eligibility_results er ON er.application_id = a.id
    LEFT JOIN disbursements     d  ON d.application_id = a.id
    WHERE a.student_id = ?
    GROUP BY a.id
    ORDER BY a.submitted_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$studentId]);
$results = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
//require_once __DIR__ . '/../includes/navbar.php';
?>
<?php require_once __DIR__ . '/../includes/student_header.php'; ?>
<style>
.student-page{
    max-width:1400px;
    margin:0 auto;
    padding:32px;
}

.page-header{
    margin-bottom:36px;
}

.page-title{
    font-size:48px;
    font-weight:800;
    color:#0F172A;
    margin-bottom:12px;
    line-height:1.1;
}

.page-subtitle{
    font-size:18px;
    color:#64748B;
    line-height:1.6;
}

.stats-grid{
    margin-bottom:32px;
}
</style>
<div class="student-page">
<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title"><i class="bi bi-trophy me-2 text-primary"></i>My Results</h1>
    <p class="page-subtitle">Scholarship evaluation results and ranking information.</p>
  </div>
</div>

<?php if (empty($results)): ?>
  <div class="card">
    <div class="card-body">
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bi bi-trophy"></i></div>
        <div class="empty-state-title">No Results Yet</div>
        <div class="empty-state-text">Your results will appear here after you submit applications and evaluations are complete.</div>
        <a href="apply.php" class="btn btn-primary">Apply Now</a>
      </div>
    </div>
  </div>
<?php else: ?>

  <!-- Summary stats -->
  <?php
    $approvedCount = count(array_filter($results, fn($r) => $r['status'] === 'approved'));
    $eligibleCount = count(array_filter($results, fn($r) => $r['eligible'] == 1));
    $avgScore = count($results) ? round(array_sum(array_column($results, 'weighted_score')) / count($results), 1) : 0;
  ?>
  <div class="row g-3 mb-4">
    <div class="col-6 col-sm-3">
      <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-folder2-open"></i></div>
        <div class="stat-body">
          <div class="stat-label">Total Applied</div>
          <div class="stat-value"><?= count($results) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-sm-3">
      <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-check2-circle"></i></div>
        <div class="stat-body">
          <div class="stat-label">Eligible</div>
          <div class="stat-value"><?= $eligibleCount ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-sm-3">
      <div class="stat-card">
        <div class="stat-icon yellow"><i class="bi bi-star-fill"></i></div>
        <div class="stat-body">
          <div class="stat-label">Avg Score</div>
          <div class="stat-value"><?= $avgScore ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-sm-3">
      <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-patch-check"></i></div>
        <div class="stat-body">
          <div class="stat-label">Approved</div>
          <div class="stat-value"><?= $approvedCount ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Result cards -->
  <?php foreach ($results as $row): ?>
  <?php
    $statusColors = [
      'approved'  => ['#f0fdf4','#16a34a'],
      'rejected'  => ['#fef2f2','#dc2626'],
      'eligible'  => ['#eff6ff','#2563eb'],
      'ineligible'=> ['#fef2f2','#dc2626'],
      'submitted' => ['#f8fafc','#64748b'],
      'reviewing' => ['#fffbeb','#d97706'],
    ];
    $sc = $statusColors[$row['status']] ?? ['#f8fafc','#64748b'];
  ?>
  <div class="card mb-4" style="border-left:5px solid <?= $sc[1] ?>;">
    <div class="card-body">
      <!-- Header row -->
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;margin-bottom:20px;">
        <div>
          <h5 style="font-weight:700;color:#0f172a;margin-bottom:4px;"><?= e($row['program_name']) ?></h5>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <span class="badge badge-status-<?= e($row['status']) ?>"><?= ucfirst(e($row['status'])) ?></span>
            <?php if ($row['eligible'] !== null): ?>
              <span class="badge <?= $row['eligible'] ? 'badge-eligible' : 'badge-ineligible' ?>">
                <i class="bi bi-<?= $row['eligible'] ? 'check2' : 'x' ?>"></i>
                <?= $row['eligible'] ? 'Eligible' : 'Not Eligible' ?>
              </span>
            <?php endif; ?>
            <?php if ($row['is_recommended']): ?>
              <span class="badge" style="background:#fef9c3;color:#854d0e;">
                <i class="bi bi-star-fill"></i> Recommended
              </span>
            <?php endif; ?>
          </div>
        </div>
        <a href="application_details.php?id=<?= $row['id'] ?>"
           class="btn btn-sm btn-outline-primary">
          <i class="bi bi-eye"></i> Full Details
        </a>
      </div>

      <div class="row g-3">
        <!-- Score & Rank -->
        <div class="col-sm-6 col-lg-3">
          <div style="background:#eff6ff;border-radius:12px;padding:16px;text-align:center;">
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin-bottom:4px;">Weighted Score</div>
            <div style="font-size:28px;font-weight:800;color:#2563eb;line-height:1;">
              <?= number_format($row['weighted_score'], 1) ?>
            </div>
            <div style="font-size:11px;color:#64748b;margin-top:4px;"><?= $row['scored_criteria'] ?> criteria evaluated</div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div style="background:<?= $row['my_rank'] ? '#f0fdf4' : '#f8fafc' ?>;border-radius:12px;padding:16px;text-align:center;">
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin-bottom:4px;">Ranking</div>
            <?php if ($row['my_rank']): ?>
              <div style="font-size:28px;font-weight:800;color:#16a34a;line-height:1;">#<?= $row['my_rank'] ?></div>
              <div style="font-size:11px;color:#64748b;margin-top:4px;">of <?= $row['program_slots'] ?> slots</div>
            <?php else: ?>
              <div style="font-size:22px;color:#94a3b8;line-height:1;">—</div>
              <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Not ranked yet</div>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div style="background:#f8fafc;border-radius:12px;padding:16px;text-align:center;">
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin-bottom:4px;">Disbursement</div>
            <?php if ($row['disburse_status']): ?>
              <span class="badge badge-status-<?= $row['disburse_status'] ?>" style="font-size:12px;padding:6px 12px;">
                <?= ucfirst($row['disburse_status']) ?>
              </span>
              <?php if ($row['disburse_amount']): ?>
                <div style="font-size:12px;font-weight:700;color:#16a34a;margin-top:6px;">
                  <?= number_format($row['disburse_amount']) ?> ₫
                </div>
              <?php endif; ?>
            <?php else: ?>
              <div style="font-size:13px;color:#94a3b8;">Not disbursed</div>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div style="background:#f8fafc;border-radius:12px;padding:16px;text-align:center;">
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin-bottom:4px;">Submitted</div>
            <div style="font-size:13px;font-weight:600;color:#334155;">
              <?= $row['submitted_at'] ? date('M d, Y', strtotime($row['submitted_at'])) : '—' ?>
            </div>
            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">App #<?= $row['id'] ?></div>
          </div>
        </div>
      </div>

      <!-- Eligibility reason (if failed) -->
      <?php if (!$row['eligible'] && $row['eligibility_reason']): ?>
      <div class="alert alert-danger mt-3 mb-0" style="font-size:12.5px;padding:10px 14px;">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Reason:</strong> <?= e($row['eligibility_reason']) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

<?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>