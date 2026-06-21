<?php
// ============================================================
// admin/applications/index.php  –  Application Monitor (read-only)
// Admin monitors scholarship workflow. CRUD is disabled by policy.
// ============================================================
$pageTitle = 'Applications';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// ── Dashboard summary stats ──────────────────────────────────
$statTotal    = (int) $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$statPending  = (int) $pdo->query("SELECT COUNT(*) FROM applications WHERE status IN ('submitted','reviewing')")->fetchColumn();
$statVerified = (int) $pdo->query("
    SELECT COUNT(DISTINCT application_id) FROM eligibility_results WHERE is_passed = 1
")->fetchColumn();
$statAwarded  = (int) $pdo->query("SELECT COUNT(*) FROM ranking_results WHERE recommended = 1")->fetchColumn();

// ── Scholarship programs for filter dropdown ─────────────────
$programs = $pdo->query("SELECT id, name FROM scholarship_programs ORDER BY name ASC")->fetchAll();

// ── Search & Filter params ───────────────────────────────────
$search        = trim($_GET['search']         ?? '');
$filterProgram = (int)($_GET['program_id']    ?? 0);
$filterStatus  = trim($_GET['status']         ?? '');
$filterVerify  = trim($_GET['verified']       ?? '');   // 'yes' | 'no' | 'pending'

$validStatuses = ['draft','submitted','reviewing','eligible','ineligible','approved','rejected','disbursed'];

// ── Pagination ───────────────────────────────────────────────
$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));

// ── Build WHERE ──────────────────────────────────────────────
$where  = [];
$params = [];

if ($search !== '') {
    $like     = "%$search%";
    $where[]  = "(u.full_name LIKE ? OR u.student_code LIKE ? OR CAST(a.id AS CHAR) = ?)";
    $params[] = $like; $params[] = $like; $params[] = trim($search);
}
if ($filterProgram > 0) {
    $where[]  = "a.program_id = ?";
    $params[] = $filterProgram;
}
if ($filterStatus !== '' && in_array($filterStatus, $validStatuses)) {
    $where[]  = "a.status = ?";
    $params[] = $filterStatus;
}
if ($filterVerify === 'yes') {
    $where[] = "er.is_passed = 1";
} elseif ($filterVerify === 'no') {
    $where[] = "er.is_passed = 0";
} elseif ($filterVerify === 'pending') {
    $where[] = "er.id IS NULL";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count
$countSql = "
    SELECT COUNT(*)
    FROM applications a
    JOIN users u               ON a.student_id  = u.id
    JOIN scholarship_programs sp ON a.program_id = sp.id
    LEFT JOIN (
        SELECT application_id, is_passed
        FROM eligibility_results
        WHERE id IN (SELECT MAX(id) FROM eligibility_results GROUP BY application_id)
    ) er ON er.application_id = a.id
    LEFT JOIN ranking_results rr ON rr.application_id = a.id
    $whereSql
";
$cntStmt = $pdo->prepare($countSql);
$cntStmt->execute($params);
$totalFiltered = (int) $cntStmt->fetchColumn();
$totalPages    = max(1, (int) ceil($totalFiltered / $perPage));
$page          = min($page, $totalPages);
$offset        = ($page - 1) * $perPage;

// Fetch data
$dataSql = "
    SELECT
        a.id,
        a.status,
        a.eligible,
        a.submitted_at,
        u.full_name,
        u.student_code,
        sp.name         AS program_name,
        sp.id           AS program_id,
        er.is_passed    AS verified,
        rr.recommended  AS awarded,
        rr.rank         AS ranking_rank,
        rr.total_score  AS total_score
    FROM applications a
    JOIN users u               ON a.student_id  = u.id
    JOIN scholarship_programs sp ON a.program_id = sp.id
    LEFT JOIN (
        SELECT application_id, is_passed
        FROM eligibility_results
        WHERE id IN (SELECT MAX(id) FROM eligibility_results GROUP BY application_id)
    ) er ON er.application_id = a.id
    LEFT JOIN ranking_results rr ON rr.application_id = a.id
    $whereSql
    ORDER BY a.id DESC
    LIMIT $perPage OFFSET $offset
";
$dataStmt = $pdo->prepare($dataSql);
$dataStmt->execute($params);
$applications = $dataStmt->fetchAll();

// Policy flash
$policyMsg = match($_GET['policy'] ?? '') {
    'no_create' => 'Creating applications is disabled. Applications are submitted by students.',
    'no_edit'   => 'Editing applications is not permitted. Admin monitors only.',
    'no_delete' => 'Deleting applications is not permitted from this module.',
    default     => '',
};

// ── CSV Export ───────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Re-run query without pagination for export
    $exportSql = str_replace("LIMIT $perPage OFFSET $offset", '', $dataSql);
    $exportStmt = $pdo->prepare($exportSql);
    $exportStmt->execute($params);
    $rows = $exportStmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="applications_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Student Name','Student ID','Program','Status','Verified','Awarded','Rank','Score','Submitted At']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['full_name'],
            $r['student_code'] ?: '—',
            $r['program_name'],
            ucfirst($r['status']),
            $r['verified'] === null ? 'Pending' : ($r['verified'] ? 'Verified' : 'Failed'),
            $r['awarded'] === null ? '—' : ($r['awarded'] ? 'Awarded' : 'Not Awarded'),
            $r['ranking_rank'] ?: '—',
            $r['total_score'] ?: '—',
            $r['submitted_at'] ?: '—',
        ]);
    }
    fclose($out);
    exit;
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

?>

<div class="container py-4">
<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Applications</h1>
    <p class="page-subtitle">Monitor scholarship applications across all stages of the evaluation workflow. Read-only view.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>"
       class="btn btn-outline-primary" id="export-csv-btn">
      <i class="bi bi-download"></i> Export CSV
    </a>
  </div>
</div>

<!-- ── Policy notice ──────────────────────────────────────────── -->
<?php if ($policyMsg): ?>
  <div class="alert alert-warning mb-4" style="border-left:4px solid var(--warning);">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Action Not Allowed:</strong> <?= e($policyMsg) ?>
  </div>
<?php endif; ?>

<?php showFlash(); ?>

<!-- ── Dashboard Summary ──────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-folder2-open"></i></div>
      <div class="stat-body">
        <div class="stat-label">Total Applications</div>
        <div class="stat-value"><?= $statTotal ?></div>
        <div class="stat-trend">All statuses</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon yellow"><i class="bi bi-hourglass-split"></i></div>
      <div class="stat-body">
        <div class="stat-label">Pending Review</div>
        <div class="stat-value"><?= $statPending ?></div>
        <div class="stat-trend">Submitted + Reviewing</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-patch-check"></i></div>
      <div class="stat-body">
        <div class="stat-label">Verified</div>
        <div class="stat-value"><?= $statVerified ?></div>
        <div class="stat-trend">Passed eligibility</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(245,158,11,.12);color:#d97706;"><i class="bi bi-trophy"></i></div>
      <div class="stat-body">
        <div class="stat-label">Awarded</div>
        <div class="stat-value"><?= $statAwarded ?></div>
        <div class="stat-trend">Recommended by ranking</div>
      </div>
    </div>
  </div>
</div>

<!-- ── Search & Filter Bar ───────────────────────────────────── -->
<div class="table-card mb-3" style="padding:18px 24px;">
  <form method="GET" id="app-filter-form" class="d-flex flex-wrap gap-2 align-items-end">

    <!-- Search -->
    <div style="flex:1;min-width:200px;">
      <label class="form-label" for="app-search" style="margin-bottom:5px;">Search</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input id="app-search" type="text" name="search" class="form-control"
               placeholder="Student name, ID, or App #…" value="<?= e($search) ?>">
      </div>
    </div>

    <!-- Program -->
    <div style="min-width:190px;">
      <label class="form-label" for="filter-program" style="margin-bottom:5px;">Program</label>
      <select id="filter-program" name="program_id" class="form-select">
        <option value="">All Programs</option>
        <?php foreach ($programs as $pg): ?>
          <option value="<?= $pg['id'] ?>" <?= $filterProgram == $pg['id'] ? 'selected' : '' ?>>
            <?= e($pg['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Status -->
    <div style="min-width:160px;">
      <label class="form-label" for="filter-status" style="margin-bottom:5px;">Status</label>
      <select id="filter-status" name="status" class="form-select">
        <option value="">All Statuses</option>
        <?php foreach (['submitted','reviewing','eligible','ineligible','approved','rejected','disbursed'] as $s): ?>
          <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>>
            <?= ucfirst($s) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Verification -->
    <div style="min-width:160px;">
      <label class="form-label" for="filter-verify" style="margin-bottom:5px;">Verification</label>
      <select id="filter-verify" name="verified" class="form-select">
        <option value="">All</option>
        <option value="yes"     <?= $filterVerify === 'yes'     ? 'selected' : '' ?>>Verified</option>
        <option value="no"      <?= $filterVerify === 'no'      ? 'selected' : '' ?>>Failed</option>
        <option value="pending" <?= $filterVerify === 'pending' ? 'selected' : '' ?>>Pending</option>
      </select>
    </div>

    <div class="d-flex gap-2" style="padding-top:24px;">
      <button type="submit" class="btn btn-primary" id="filter-btn">
        <i class="bi bi-funnel"></i> Filter
      </button>
      <?php if ($search || $filterProgram || $filterStatus || $filterVerify): ?>
        <a href="index.php" class="btn btn-secondary"><i class="bi bi-x-lg"></i> Clear</a>
      <?php endif; ?>
    </div>

  </form>
</div>

<!-- ── Application Table ─────────────────────────────────────── -->
<div class="table-card">
  <div class="table-card-header">
    <span class="table-card-title">
      <?= ($search || $filterProgram || $filterStatus || $filterVerify)
          ? "$totalFiltered result" . ($totalFiltered !== 1 ? 's' : '') . " found"
          : "All Applications" ?>
    </span>
    <?php if ($totalPages > 1): ?>
      <span class="text-muted" style="font-size:12.5px;">Page <?= $page ?> of <?= $totalPages ?></span>
    <?php endif; ?>
  </div>

  <div class="table-responsive">
    <table class="table" id="applications-table">
      <thead>
        <tr>
          <th>App ID</th>
          <th>Student</th>
          <th>Student ID</th>
          <th>Program</th>
          <th>Submitted</th>
          <th>Status</th>
          <th>Verification</th>
          <th>Result</th>
          <th>Detail</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($applications)): ?>
          <tr>
            <td colspan="9">
              <div class="empty-state" style="padding:48px 24px;">
                <span class="empty-state-icon"><i class="bi bi-folder2-open"></i></span>
                <div class="empty-state-title">No applications found</div>
                <div class="empty-state-text">
                  <?= ($search || $filterProgram || $filterStatus || $filterVerify)
                      ? 'Try adjusting your search or filters.'
                      : 'No applications have been submitted yet.' ?>
                </div>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($applications as $app): ?>
            <tr>
              <!-- App ID -->
              <td><span class="text-muted">#<?= e($app['id']) ?></span></td>

              <!-- Student -->
              <td><strong><?= e($app['full_name']) ?></strong></td>

              <!-- Student ID -->
              <td>
                <?php if ($app['student_code']): ?>
                  <code style="font-size:12px;background:#f1f5f9;padding:2px 7px;border-radius:4px;">
                    <?= e($app['student_code']) ?>
                  </code>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>

              <!-- Program -->
              <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= e($app['program_name']) ?>">
                <?= e($app['program_name']) ?>
              </td>

              <!-- Submitted At -->
              <td class="text-muted" style="white-space:nowrap;">
                <?= $app['submitted_at'] ? e(date('d M Y', strtotime($app['submitted_at']))) : '—' ?>
              </td>

              <!-- Application Status -->
              <td>
                <span class="badge badge-status-<?= e($app['status']) ?>">
                  <?= ucfirst(e($app['status'])) ?>
                </span>
              </td>

              <!-- Verification Status -->
              <td>
                <?php if ($app['verified'] === null): ?>
                  <span class="badge badge-inactive"><i class="bi bi-clock" style="font-size:9px;"></i> Pending</span>
                <?php elseif ($app['verified'] == 1): ?>
                  <span class="badge" style="background:#d1fae5;color:#065f46;">
                    <i class="bi bi-check-circle-fill" style="font-size:9px;"></i> Verified
                  </span>
                <?php else: ?>
                  <span class="badge" style="background:#fee2e2;color:#991b1b;">
                    <i class="bi bi-x-circle-fill" style="font-size:9px;"></i> Failed
                  </span>
                <?php endif; ?>
              </td>

              <!-- Final Result (Ranking / Award) -->
              <td>
                <?php if ($app['awarded'] === null): ?>
                  <span class="badge badge-inactive">Not Ranked</span>
                <?php elseif ($app['awarded'] == 1): ?>
                  <span class="badge" style="background:#fef3c7;color:#92400e;">
                    <i class="bi bi-trophy-fill" style="font-size:9px;"></i>
                    Awarded #<?= e($app['ranking_rank']) ?>
                  </span>
                <?php else: ?>
                  <span class="badge" style="background:#f1f5f9;color:#475569;">
                    Ranked #<?= e($app['ranking_rank']) ?>
                  </span>
                <?php endif; ?>
              </td>

              <!-- View -->
              <td>
                <a href="view.php?id=<?= $app['id'] ?>"
                   class="btn btn-sm btn-outline-primary btn-action"
                   id="view-app-<?= $app['id'] ?>">
                  <i class="bi bi-eye"></i> View
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ── Pagination ──────────────────────────────────────────── -->
  <?php if ($totalPages > 1): ?>
    <div style="padding:16px 24px;border-top:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
      <span class="text-muted" style="font-size:12.5px;">
        Showing <?= min(($page-1)*$perPage+1, $totalFiltered) ?>–<?= min($page*$perPage, $totalFiltered) ?>
        of <?= $totalFiltered ?>
      </span>
      <nav>
        <ul class="pagination mb-0" style="gap:4px;display:flex;list-style:none;padding:0;">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>">
              <i class="bi bi-chevron-left"></i>
            </a>
          </li>
          <?php
          $s = max(1, $page-2); $e = min($totalPages, $page+2);
          if ($s > 1): ?><li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>1])) ?>">1</a></li><?php if($s>2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; endif;
          for ($p = $s; $p <= $e; $p++): ?>
            <li class="page-item <?= $p===$page?'active':'' ?>">
              <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$p])) ?>"><?= $p ?></a>
            </li>
          <?php endfor;
          if ($e < $totalPages): if($e<$totalPages-1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?><li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$totalPages])) ?>"><?= $totalPages ?></a></li><?php endif; ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>">
              <i class="bi bi-chevron-right"></i>
            </a>
          </li>
        </ul>
      </nav>
    </div>
  <?php endif; ?>
</div>
</div>

<?php require_once '../../includes/footer.php'; ?>
