<?php
$pageTitle = 'Admin Dashboard';

require_once '../config/db.php';
require_once '../includes/auth.php';

requireLogin();
requireRole('admin');

require_once '../includes/header.php';
require_once '../includes/navbar.php';

$pdo = getDB();

// ── Stat card queries ────────────────────────────────────────────
$totalApplications  = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$pendingApps        = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'submitted'")->fetchColumn();

// Verified Applications: applications that have passed eligibility check (is_passed = 1)
$totalVerified      = $pdo->query("
    SELECT COUNT(DISTINCT application_id)
    FROM eligibility_results
    WHERE is_passed = 1
")->fetchColumn();

// Pending Verification: submitted applications not yet checked by eligibility engine
$pendingVerification = $pdo->query("
    SELECT COUNT(*) FROM applications a
    WHERE a.status = 'submitted'
      AND NOT EXISTS (
          SELECT 1 FROM eligibility_results er
          WHERE er.application_id = a.id
      )
")->fetchColumn();

$totalAwarded  = $pdo->query("SELECT COUNT(*) FROM ranking_results WHERE recommended = 1")->fetchColumn();
$totalBudget   = $pdo->query("SELECT SUM(budget) FROM scholarship_programs")->fetchColumn();

// ── Recent Applications (with verification status) ───────────────
$recentApps = $pdo->query("
    SELECT
        a.id,
        a.status,
        a.submitted_at,
        u.full_name,
        sp.name AS program_name,
        COALESCE(er.is_passed, -1) AS verified
    FROM applications a
    JOIN users u  ON a.student_id = u.id
    JOIN scholarship_programs sp ON a.program_id = sp.id
    LEFT JOIN (
        SELECT application_id, is_passed
        FROM eligibility_results
        WHERE id IN (
            SELECT MAX(id) FROM eligibility_results GROUP BY application_id
        )
    ) er ON er.application_id = a.id
    ORDER BY a.id DESC
    LIMIT 8
");

// ── Recent Notifications (system-wide, latest 6) ─────────────────
$recentNotifications = $pdo->query("
    SELECT n.id, n.title, n.message, n.type, n.is_read, n.created_at, u.full_name
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.id
    ORDER BY n.created_at DESC
    LIMIT 6
");

// ── Program Summary ───────────────────────────────────────────────
$programSummary = $pdo->query("
    SELECT
        sp.id,
        sp.name,
        sp.slots AS quota,
        COUNT(DISTINCT a.id) AS total_applications,
        COUNT(DISTINCT CASE WHEN rr.recommended = 1 THEN rr.id END) AS awarded_students
    FROM scholarship_programs sp
    LEFT JOIN applications a ON a.program_id = sp.id
    LEFT JOIN ranking_results rr ON rr.application_id = a.id
    GROUP BY sp.id, sp.name, sp.slots
    ORDER BY sp.id ASC
");
?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Welcome back, <?= e(currentUserName()) ?> 👋</h1>
    <p class="page-subtitle">Scholarship workflow overview — verification progress, rankings &amp; notifications.</p>
  </div>
  <div class="quick-actions">
    <a href="applications/index.php" class="btn btn-primary">
      <i class="bi bi-folder2-open"></i> View Applications
    </a>
  </div>
</div>

<!-- ── Stat Cards ───────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

  <!-- Total Applications -->
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-folder2-open"></i></div>
      <div class="stat-body">
        <div class="stat-label">Total Applications</div>
        <div class="stat-value"><?= e($totalApplications) ?></div>
        <div class="stat-trend"><?= e($pendingApps) ?> pending</div>
      </div>
    </div>
  </div>

  <!-- Verified Applications (replaces Eligible) -->
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-patch-check"></i></div>
      <div class="stat-body">
        <div class="stat-label">Verified Applications</div>
        <div class="stat-value"><?= e($totalVerified) ?></div>
        <div class="stat-trend">Passed eligibility check</div>
      </div>
    </div>
  </div>

  <!-- Pending Verification (replaces Rejected) -->
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon yellow"><i class="bi bi-hourglass-split"></i></div>
      <div class="stat-body">
        <div class="stat-label">Pending Verification</div>
        <div class="stat-value"><?= e($pendingVerification) ?></div>
        <div class="stat-trend">Awaiting eligibility check</div>
      </div>
    </div>
  </div>

  <!-- Awarded Students -->
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(245,158,11,.12);color:#d97706;"><i class="bi bi-trophy"></i></div>
      <div class="stat-body">
        <div class="stat-label">Awarded Students</div>
        <div class="stat-value"><?= e($totalAwarded) ?></div>
        <div class="stat-trend">Recommended by ranking</div>
      </div>
    </div>
  </div>

  <!-- Total Scholarship Budget -->
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(25,135,84,.12);color:#16a34a;"><i class="bi bi-cash-stack"></i></div>
      <div class="stat-body">
        <div class="stat-label">Total Budget</div>
        <div class="stat-value" style="font-size:18px;"><?= number_format($totalBudget, 0, ',', '.') ?>đ</div>
        <div class="stat-trend">Across all programs</div>
      </div>
    </div>
  </div>

</div>

<!-- ── Main Content Row ──────────────────────────────────────────── -->
<div class="row g-3 mb-3">

  <!-- Recent Applications Table -->
  <div class="col-lg-8">
    <div class="table-card">
      <div class="table-card-header">
        <span class="table-card-title">Recent Applications</span>
        <a href="applications/index.php" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Student</th>
              <th>Program</th>
              <th>Status</th>
              <th>Verification</th>
              <th>Submitted</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentApps as $app): ?>
              <tr>
                <td><span class="text-muted">#<?= e($app['id']) ?></span></td>
                <td><strong><?= e($app['full_name']) ?></strong></td>
                <td><?= e($app['program_name']) ?></td>
                <td>
                  <span class="badge badge-status-<?= e($app['status']) ?>">
                    <?= ucfirst(e($app['status'])) ?>
                  </span>
                </td>
                <td>
                  <?php if ($app['verified'] == 1): ?>
                    <span class="badge" style="background:#d1fae5;color:#065f46;">
                      <i class="bi bi-check-circle-fill" style="font-size:9px;"></i> Verified
                    </span>
                  <?php elseif ($app['verified'] == 0): ?>
                    <span class="badge" style="background:#fee2e2;color:#991b1b;">
                      <i class="bi bi-x-circle-fill" style="font-size:9px;"></i> Failed
                    </span>
                  <?php else: ?>
                    <span class="badge badge-inactive">
                      <i class="bi bi-clock" style="font-size:9px;"></i> Pending
                    </span>
                  <?php endif; ?>
                </td>
                <td class="text-muted"><?= e($app['submitted_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Right Column -->
  <div class="col-lg-4 d-flex flex-column gap-3">

    <!-- Quick Actions -->
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-3">Quick Actions</div>
        <div class="quick-action-grid">
          <a href="users/index.php" class="quick-action-item">
            <i class="bi bi-people"></i> Users
          </a>
          <a href="applications/index.php" class="quick-action-item">
            <i class="bi bi-folder2-open"></i> Applications
          </a>
          <a href="scholarship_programs/index.php" class="quick-action-item">
            <i class="bi bi-award"></i> Programs
          </a>
          <a href="evaluation_scores/index.php" class="quick-action-item">
            <i class="bi bi-star-half"></i> Scores
          </a>
          <a href="ranking_results/index.php" class="quick-action-item">
            <i class="bi bi-bar-chart-steps"></i> Rankings
          </a>
          <a href="notifications/index.php" class="quick-action-item">
            <i class="bi bi-bell"></i> Notifications
          </a>
          <a href="reports/index.php" class="quick-action-item">
            <i class="bi bi-graph-up"></i> Reports
          </a>
        </div>
      </div>
    </div>

    <!-- Recent Notifications -->
    <div class="card">
      <div class="card-body" style="padding-bottom:12px;">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div class="card-title mb-0">Recent Notifications</div>
          <a href="notifications/index.php" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <?php
        $notifs = $recentNotifications->fetchAll();
        if (empty($notifs)): ?>
          <p class="text-muted" style="font-size:13px;">No notifications yet.</p>
        <?php else: ?>
          <ul class="list-unstyled mb-0">
            <?php foreach ($notifs as $n):
              $iconMap  = ['info'=>'info-circle','success'=>'check-circle','warning'=>'exclamation-triangle','error'=>'x-octagon'];
              $colorMap = ['info'=>'var(--info)','success'=>'var(--success)','warning'=>'var(--warning)','error'=>'var(--danger)'];
              $nType    = $n['type'] ?? 'info';
              $icon     = $iconMap[$nType]  ?? 'bell';
              $color    = $colorMap[$nType] ?? 'var(--info)';
            ?>
              <li style="display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid var(--gray-100);">
                <span style="color:<?= $color ?>;font-size:15px;margin-top:2px;flex-shrink:0;">
                  <i class="bi bi-<?= $icon ?>"></i>
                </span>
                <div style="flex:1;min-width:0;">
                  <div style="font-weight:600;font-size:13px;color:var(--gray-800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <?= e($n['title']) ?>
                  </div>
                  <div style="font-size:12px;color:var(--gray-500);margin-top:1px;">
                    <?= e($n['full_name']) ?> &middot;
                    <?= e(date('d M, H:i', strtotime($n['created_at']))) ?>
                  </div>
                </div>
                <?php if (!$n['is_read']): ?>
                  <span style="width:7px;height:7px;border-radius:50%;background:var(--primary);flex-shrink:0;margin-top:5px;"></span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<!-- ── Program Summary ───────────────────────────────────────────── -->
<div class="row g-3">
  <div class="col-12">
    <div class="table-card">
      <div class="table-card-header">
        <span class="table-card-title">Program Summary</span>
        <a href="scholarship_programs/index.php" class="btn btn-sm btn-outline-primary">Manage Programs</a>
      </div>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>#</th>
              <th>Program Name</th>
              <th>Quota</th>
              <th>Total Applications</th>
              <th>Awarded Students</th>
              <th>Fill Rate</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($programSummary as $prog):
              $fillPct = $prog['quota'] > 0
                ? min(100, round(($prog['awarded_students'] / $prog['quota']) * 100))
                : 0;
              $barColor = $fillPct >= 100 ? 'var(--success)' : ($fillPct >= 50 ? 'var(--primary)' : 'var(--warning)');
            ?>
              <tr>
                <td><span class="text-muted"><?= e($prog['id']) ?></span></td>
                <td><strong><?= e($prog['name']) ?></strong></td>
                <td>
                  <span class="badge badge-info"><?= e($prog['quota']) ?> slots</span>
                </td>
                <td><?= e($prog['total_applications']) ?></td>
                <td>
                  <span class="badge" style="background:#d1fae5;color:#065f46;">
                    <?= e($prog['awarded_students']) ?>
                  </span>
                </td>
                <td style="min-width:120px;">
                  <div style="display:flex;align-items:center;gap:8px;">
                    <div style="flex:1;height:6px;background:var(--gray-200);border-radius:99px;overflow:hidden;">
                      <div style="width:<?= $fillPct ?>%;height:100%;background:<?= $barColor ?>;border-radius:99px;transition:width .4s;"></div>
                    </div>
                    <span style="font-size:12px;font-weight:600;color:var(--gray-600);white-space:nowrap;"><?= $fillPct ?>%</span>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
