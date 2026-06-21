<?php
// ============================================================
// admin/scholarship_programs/view.php
// Program Detail: info, eligibility rules, scoring criteria,
// statistics, budget utilization, recent applications.
// ============================================================
$pageTitle = 'Program Detail';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ── Core program ────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM scholarship_programs WHERE id = ?");
$stmt->execute([$id]);
$prog = $stmt->fetch();

if (!$prog) {
    require_once '../../includes/header.php';
    require_once '../../includes/navbar.php';
    echo '<div class="page-header"><div class="page-header-left">
          <h1 class="page-title">Not Found</h1></div>
          <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a></div>
          <div class="alert alert-danger">Program #'.(int)$id.' does not exist.</div>';
    require_once '../../includes/footer.php';
    exit;
}

// ── Statistics ───────────────────────────────────────────────
$totalApps = (int) $pdo->prepare("SELECT COUNT(*) FROM applications WHERE program_id = ?")
    ->execute([$id]) ? $pdo->query("SELECT COUNT(*) FROM applications WHERE program_id = $id")->fetchColumn() : 0;

$statsStmt = $pdo->prepare("
    SELECT
        COUNT(DISTINCT a.id)                                     AS total_apps,
        COUNT(DISTINCT CASE WHEN a.eligible = 1 THEN a.id END)  AS eligible_apps,
        COUNT(DISTINCT CASE WHEN er.is_passed = 1 THEN er.application_id END) AS verified_apps,
        COUNT(DISTINCT CASE WHEN rr.recommended = 1 THEN rr.id END)           AS awarded_students,
        COALESCE(SUM(CASE WHEN d.status IN ('approved','paid') THEN d.amount END), 0) AS disbursed_amount
    FROM applications a
    LEFT JOIN (
        SELECT application_id, is_passed
        FROM eligibility_results
        WHERE id IN (SELECT MAX(id) FROM eligibility_results GROUP BY application_id)
    ) er ON er.application_id = a.id
    LEFT JOIN ranking_results rr ON rr.application_id = a.id
    LEFT JOIN disbursements d    ON d.application_id  = a.id
    WHERE a.program_id = ?
");
$statsStmt->execute([$id]);
$stats = $statsStmt->fetch();

// ── Eligibility rules ────────────────────────────────────────
$rules = $pdo->prepare("SELECT * FROM eligibility_rules WHERE program_id = ? ORDER BY id ASC");
$rules->execute([$id]);
$rules = $rules->fetchAll();

// ── Scoring criteria ─────────────────────────────────────────
$criteria = $pdo->prepare("SELECT * FROM scoring_criteria WHERE program_id = ? ORDER BY weight DESC");
$criteria->execute([$id]);
$criteria = $criteria->fetchAll();

// ── Recent applications ──────────────────────────────────────
$recentApps = $pdo->prepare("
    SELECT a.id, a.status, a.eligible, a.submitted_at,
           u.full_name, u.student_code,
           er.is_passed AS verified,
           rr.recommended AS awarded, rr.rank AS ranking_rank
    FROM applications a
    JOIN users u ON a.student_id = u.id
    LEFT JOIN (
        SELECT application_id, is_passed
        FROM eligibility_results
        WHERE id IN (SELECT MAX(id) FROM eligibility_results GROUP BY application_id)
    ) er ON er.application_id = a.id
    LEFT JOIN ranking_results rr ON rr.application_id = a.id
    WHERE a.program_id = ?
    ORDER BY a.id DESC
    LIMIT 10
");
$recentApps->execute([$id]);
$recentApps = $recentApps->fetchAll();

// ── Budget utilization ───────────────────────────────────────
$budgetUsedPct = ($prog['budget'] > 0 && $stats['disbursed_amount'] > 0)
    ? min(100, round(($stats['disbursed_amount'] / $prog['budget']) * 100))
    : 0;
$quotaFilledPct = ($prog['slots'] > 0 && $stats['awarded_students'] > 0)
    ? min(100, round(($stats['awarded_students'] / $prog['slots']) * 100))
    : 0;

// Status helpers
$statusMap = [
    'open'      => ['badge-eligible',  'Active (Open)'],
    'closed'    => ['badge-inactive',  'Closed'],
    'suspended' => ['badge-ineligible','Suspended'],
    'draft'     => ['badge-warning',   'Draft'],
];
[$statusBadge, $statusLabel] = $statusMap[$prog['status']] ?? ['badge-inactive', ucfirst($prog['status'])];

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title"><?= e($prog['name']) ?></h1>
    <p class="page-subtitle">
      Program #<?= e($prog['id']) ?> — Full details, statistics, eligibility rules, and scoring criteria.
    </p>
  </div>
  <div class="d-flex gap-2">
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
    <a href="edit.php?id=<?= $id ?>" class="btn btn-warning" id="edit-prog-btn">
      <i class="bi bi-pencil"></i> Edit
    </a>
    <?php if ($prog['status'] !== 'suspended'): ?>
      <a href="suspend.php?id=<?= $id ?>" class="btn btn-outline-secondary"
         onclick="return confirm('Suspend this program?')">
        <i class="bi bi-pause-circle"></i> Suspend
      </a>
    <?php else: ?>
      <a href="suspend.php?id=<?= $id ?>&reopen=1" class="btn btn-success"
         onclick="return confirm('Re-open this program?')">
        <i class="bi bi-play-circle"></i> Re-open
      </a>
    <?php endif; ?>
    <a href="delete.php?id=<?= $id ?>" class="btn btn-danger"
       onclick="return confirm('Delete this program?\n\nThis cannot be undone.')">
      <i class="bi bi-trash"></i> Delete
    </a>
  </div>
</div>

<?php showFlash(); ?>

<!-- ── Statistics Row ────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-folder2-open"></i></div>
      <div class="stat-body">
        <div class="stat-label">Total Applications</div>
        <div class="stat-value"><?= (int)$stats['total_apps'] ?></div>
        <div class="stat-trend">All statuses</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon cyan"><i class="bi bi-check-circle"></i></div>
      <div class="stat-body">
        <div class="stat-label">Eligible</div>
        <div class="stat-value"><?= (int)$stats['eligible_apps'] ?></div>
        <div class="stat-trend">Passed filter</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-patch-check"></i></div>
      <div class="stat-body">
        <div class="stat-label">Verified</div>
        <div class="stat-value"><?= (int)$stats['verified_apps'] ?></div>
        <div class="stat-trend">Passed eligibility</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(245,158,11,.12);color:#d97706;"><i class="bi bi-trophy"></i></div>
      <div class="stat-body">
        <div class="stat-label">Awarded</div>
        <div class="stat-value"><?= (int)$stats['awarded_students'] ?></div>
        <div class="stat-trend">Recommended</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(22,163,74,.12);color:var(--success);"><i class="bi bi-cash-stack"></i></div>
      <div class="stat-body">
        <div class="stat-label">Disbursed</div>
        <div class="stat-value" style="font-size:17px;"><?= number_format((float)$stats['disbursed_amount'],0,',','.') ?>đ</div>
        <div class="stat-trend"><?= $budgetUsedPct ?>% of budget</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">

  <!-- ── Left Column ─────────────────────────────────────────── -->
  <div class="col-lg-4 d-flex flex-column gap-3">

    <!-- Program Info -->
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-award me-2" style="color:var(--primary);"></i>Program Information
        </div>
        <table class="table detail-table mb-0" style="font-size:13px;">
          <tr><th>Program ID</th><td>#<?= e($prog['id']) ?></td></tr>
          <tr>
            <th>Status</th>
            <td><span class="badge <?= $statusBadge ?>"><?= $statusLabel ?></span></td>
          </tr>
          <tr>
            <th>Budget</th>
            <td><strong style="color:var(--success);"><?= number_format((float)$prog['budget'],0,',','.') ?>đ</strong></td>
          </tr>
          <tr>
            <th>Quota (Slots)</th>
            <td><span class="badge badge-info"><?= e($prog['slots']) ?> slots</span></td>
          </tr>
          <tr><th>Start Date</th><td><?= $prog['start_date'] ? e(date('d M Y',strtotime($prog['start_date']))) : '—' ?></td></tr>
          <tr><th>End Date</th>  <td><?= $prog['end_date']   ? e(date('d M Y',strtotime($prog['end_date'])))   : '—' ?></td></tr>
        </table>
        <?php if ($prog['description']): ?>
          <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--gray-100);
                      font-size:13px;color:var(--gray-600);line-height:1.7;">
            <?= nl2br(e($prog['description'])) ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Budget & Quota Utilization -->
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-bar-chart me-2" style="color:var(--info);"></i>Budget Utilization
        </div>
        <!-- Budget bar -->
        <div style="margin-bottom:14px;">
          <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--gray-500);margin-bottom:5px;">
            <span>Disbursed</span>
            <span><?= $budgetUsedPct ?>%</span>
          </div>
          <div style="height:8px;background:var(--gray-200);border-radius:99px;overflow:hidden;">
            <div style="width:<?= $budgetUsedPct ?>%;height:100%;
                        background:<?= $budgetUsedPct >= 90 ? 'var(--danger)' : ($budgetUsedPct >= 50 ? 'var(--primary)' : 'var(--success)') ?>;
                        border-radius:99px;transition:width .4s;"></div>
          </div>
          <div style="font-size:11px;color:var(--gray-400);margin-top:3px;">
            <?= number_format((float)$stats['disbursed_amount'],0,',','.') ?>đ
            / <?= number_format((float)$prog['budget'],0,',','.') ?>đ
          </div>
        </div>
        <!-- Quota bar -->
        <div>
          <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--gray-500);margin-bottom:5px;">
            <span>Quota Filled</span>
            <span><?= $quotaFilledPct ?>%</span>
          </div>
          <div style="height:8px;background:var(--gray-200);border-radius:99px;overflow:hidden;">
            <div style="width:<?= $quotaFilledPct ?>%;height:100%;
                        background:<?= $quotaFilledPct >= 100 ? 'var(--success)' : 'var(--warning)' ?>;
                        border-radius:99px;transition:width .4s;"></div>
          </div>
          <div style="font-size:11px;color:var(--gray-400);margin-top:3px;">
            <?= (int)$stats['awarded_students'] ?> awarded / <?= e($prog['slots']) ?> slots
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Links -->
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-link-45deg me-2" style="color:var(--gray-500);"></i>Manage Rules & Criteria
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;">
          <a href="../eligibility_rules/create.php?program_id=<?= $id ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Eligibility Rule
          </a>
          <a href="../eligibility_rules/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-funnel me-1"></i> All Eligibility Rules
          </a>
          <a href="../scoring_criteria/create.php?program_id=<?= $id ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Scoring Criterion
          </a>
          <a href="../scoring_criteria/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-star-half me-1"></i> All Scoring Criteria
          </a>
          <a href="../applications/index.php?program_id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-folder2-open me-1"></i> View Applications
          </a>
          <a href="../ranking_results/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-bar-chart-steps me-1"></i> Ranking Results
          </a>
        </div>
      </div>
    </div>

  </div><!-- /col-lg-4 -->

  <!-- ── Right Column ─────────────────────────────────────────── -->
  <div class="col-lg-8 d-flex flex-column gap-3">

    <!-- Eligibility Rules -->
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3"
             style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <div class="card-title mb-0">
            <i class="bi bi-funnel me-2" style="color:var(--info);"></i>Eligibility Rules
            <span style="font-size:12px;font-weight:400;color:var(--gray-400);margin-left:6px;"><?= count($rules) ?> rule<?= count($rules)!==1?'s':'' ?></span>
          </div>
          <a href="../eligibility_rules/create.php?program_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-plus-lg"></i> Add Rule
          </a>
        </div>
        <?php if (empty($rules)): ?>
          <p class="text-muted" style="font-size:13px;margin:0;">No eligibility rules defined for this program.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table" style="font-size:13px;margin:0;">
              <thead>
                <tr><th>Rule Type</th><th>Operator</th><th>Threshold Value</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($rules as $r): ?>
                  <tr>
                    <td><span class="badge badge-info" style="text-transform:uppercase;"><?= e($r['rule_type']) ?></span></td>
                    <td><code style="font-size:13px;font-weight:700;"><?= e($r['operator']) ?></code></td>
                    <td><?= e($r['value']) ?></td>
                    <td>
                      <div class="d-flex gap-1">
                        <a href="../eligibility_rules/edit.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-warning btn-action">
                          <i class="bi bi-pencil"></i>
                        </a>
                        <a href="../eligibility_rules/delete.php?id=<?= $r['id'] ?>"
                           class="btn btn-sm btn-danger btn-action"
                           onclick="return confirm('Delete this rule?')">
                          <i class="bi bi-trash"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Scoring Criteria -->
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3"
             style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <div class="card-title mb-0">
            <i class="bi bi-star-half me-2" style="color:var(--warning);"></i>Scoring Criteria
            <span style="font-size:12px;font-weight:400;color:var(--gray-400);margin-left:6px;"><?= count($criteria) ?> criterion<?= count($criteria)!==1?'a':'' ?></span>
          </div>
          <a href="../scoring_criteria/create.php?program_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-plus-lg"></i> Add Criterion
          </a>
        </div>
        <?php if (empty($criteria)): ?>
          <p class="text-muted" style="font-size:13px;margin:0;">No scoring criteria defined for this program.</p>
        <?php else: ?>
          <?php $totalWeight = array_sum(array_column($criteria,'weight')); ?>
          <div class="table-responsive">
            <table class="table" style="font-size:13px;margin:0;">
              <thead>
                <tr><th>Criterion</th><th>Weight (%)</th><th>Max Score</th><th>Weight Bar</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($criteria as $c):
                  $barW = $totalWeight > 0 ? round(($c['weight']/$totalWeight)*100) : 0;
                ?>
                  <tr>
                    <td><strong><?= e($c['criterion_name']) ?></strong></td>
                    <td>
                      <span class="badge badge-info"><?= number_format((float)$c['weight'],1) ?>%</span>
                    </td>
                    <td><?= e($c['max_score']) ?></td>
                    <td style="min-width:100px;">
                      <div style="height:6px;background:var(--gray-200);border-radius:99px;overflow:hidden;">
                        <div style="width:<?= $barW ?>%;height:100%;background:var(--primary);border-radius:99px;"></div>
                      </div>
                    </td>
                    <td>
                      <div class="d-flex gap-1">
                        <a href="../scoring_criteria/edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-warning btn-action">
                          <i class="bi bi-pencil"></i>
                        </a>
                        <a href="../scoring_criteria/delete.php?id=<?= $c['id'] ?>"
                           class="btn btn-sm btn-danger btn-action"
                           onclick="return confirm('Delete this scoring criterion?')">
                          <i class="bi bi-trash"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if (abs($totalWeight - 100) > 0.01): ?>
            <div class="alert alert-warning mt-2 mb-0" style="font-size:12.5px;padding:8px 14px;">
              <i class="bi bi-exclamation-triangle me-1"></i>
              Total weight is <?= number_format($totalWeight,1) ?>% — should sum to 100% for accurate scoring.
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent Applications -->
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3"
             style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <div class="card-title mb-0">
            <i class="bi bi-folder2-open me-2" style="color:var(--primary);"></i>Recent Applications
          </div>
          <a href="../applications/index.php?program_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <?php if (empty($recentApps)): ?>
          <p class="text-muted" style="font-size:13px;margin:0;">No applications submitted for this program yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table" style="font-size:13px;margin:0;">
              <thead>
                <tr><th>App #</th><th>Student</th><th>Status</th><th>Verified</th><th>Result</th><th>Submitted</th></tr>
              </thead>
              <tbody>
                <?php foreach ($recentApps as $a): ?>
                  <tr>
                    <td>
                      <a href="../applications/view.php?id=<?= $a['id'] ?>" class="text-primary fw-semibold">
                        #<?= e($a['id']) ?>
                      </a>
                    </td>
                    <td>
                      <strong><?= e($a['full_name']) ?></strong>
                      <?php if ($a['student_code']): ?>
                        <div style="font-size:11px;color:var(--gray-400);"><?= e($a['student_code']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge badge-status-<?= e($a['status']) ?>"><?= ucfirst(e($a['status'])) ?></span>
                    </td>
                    <td>
                      <?php if ($a['verified'] === null): ?>
                        <span class="badge badge-inactive" style="font-size:10px;">Pending</span>
                      <?php elseif ($a['verified']): ?>
                        <span class="badge badge-eligible" style="font-size:10px;"><i class="bi bi-check2"></i> Yes</span>
                      <?php else: ?>
                        <span class="badge badge-ineligible" style="font-size:10px;"><i class="bi bi-x"></i> Failed</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($a['awarded'] === null): ?>
                        <span class="badge badge-inactive" style="font-size:10px;">—</span>
                      <?php elseif ($a['awarded']): ?>
                        <span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px;">
                          <i class="bi bi-trophy-fill"></i> #<?= e($a['ranking_rank']) ?>
                        </span>
                      <?php else: ?>
                        <span class="badge badge-inactive" style="font-size:10px;">Rank #<?= e($a['ranking_rank']) ?></span>
                      <?php endif; ?>
                    </td>
                    <td class="text-muted">
                      <?= $a['submitted_at'] ? e(date('d M Y',strtotime($a['submitted_at']))) : '—' ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /col-lg-8 -->
</div>

<?php require_once '../../includes/footer.php'; ?>
