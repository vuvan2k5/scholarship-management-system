<?php
// ============================================================
// admin/eligibility_results/index.php
// View-only list of all eligibility results.
// Admin can search, filter, view details, and export.
// Admin must NOT manually change PASS/FAIL — engine only.
// ============================================================
$pageTitle = 'Eligibility Results';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// ── Auto-migration ────────────────────────────────────────────
try {
    $pdo->exec("ALTER TABLE eligibility_results
        ADD COLUMN IF NOT EXISTS rule_trace                  JSON    DEFAULT NULL         AFTER reason,
        ADD COLUMN IF NOT EXISTS checked_by                  INT(11) DEFAULT NULL         AFTER rule_trace,
        ADD COLUMN IF NOT EXISTS reviewer_verification_status
            ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending'              AFTER checked_by
    ");
} catch (Exception $e) {}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS reviewer_verifications (
        id              INT(11)      AUTO_INCREMENT PRIMARY KEY,
        eligibility_id  INT(11)      NOT NULL,
        reviewer_id     INT(11)      NOT NULL,
        status          ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
        notes           TEXT         DEFAULT NULL,
        verified_at     DATETIME     DEFAULT NULL,
        created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (eligibility_id) REFERENCES eligibility_results(id) ON DELETE CASCADE,
        FOREIGN KEY (reviewer_id)    REFERENCES users(id)               ON DELETE CASCADE
    )
");

// ── Search & Filter params ─────────────────────────────────
$search         = trim($_GET['search']       ?? '');
$filterResult   = trim($_GET['result']       ?? '');  // '' | 'pass' | 'fail'
$filterVerif    = trim($_GET['verif_status'] ?? '');  // '' | 'pending' | 'verified' | 'rejected'
$filterProgram  = (int)($_GET['program_id']  ?? 0);
$filterDate     = trim($_GET['eval_date']    ?? '');

// ── Programs for filter dropdown ─────────────────────────────
$programs = $pdo->query("SELECT id, name FROM scholarship_programs ORDER BY name ASC")->fetchAll();

// ── Build WHERE ───────────────────────────────────────────────
$where  = [];
$params = [];

if ($search !== '') {
    $like     = "%$search%";
    $where[]  = "(u.full_name LIKE ? OR u.student_code LIKE ? OR sp.name LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($filterResult === 'pass')         { $where[] = "er.is_passed = 1"; }
if ($filterResult === 'fail')         { $where[] = "er.is_passed = 0"; }
if ($filterVerif !== '')              { $where[] = "er.reviewer_verification_status = ?"; $params[] = $filterVerif; }
if ($filterProgram > 0)              { $where[] = "a.program_id = ?"; $params[] = $filterProgram; }
if ($filterDate !== '')              { $where[] = "DATE(er.checked_at) = ?"; $params[] = $filterDate; }

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ── Fetch results ─────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT
        er.id,
        er.application_id,
        er.is_passed,
        er.reason,
        er.rule_trace,
        er.checked_at,
        er.reviewer_verification_status,
        a.id          AS app_id,
        u.full_name   AS student_name,
        u.student_code,
        sp.id         AS program_id,
        sp.name       AS program_name,
        cb.full_name  AS checked_by_name
    FROM eligibility_results er
    JOIN applications a ON er.application_id = a.id
    JOIN users u        ON a.student_id = u.id
    JOIN scholarship_programs sp ON a.program_id = sp.id
    LEFT JOIN users cb ON er.checked_by = cb.id
    $whereSql
    ORDER BY er.checked_at DESC, er.id DESC
    LIMIT 500
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ── Statistics (always full dataset) ─────────────────────────
$stats = $pdo->query("
    SELECT
        COUNT(*)                                                  AS total,
        SUM(is_passed = 1)                                        AS passed,
        SUM(is_passed = 0)                                        AS failed,
        SUM(reviewer_verification_status = 'pending')             AS verif_pending,
        SUM(reviewer_verification_status = 'verified')            AS verif_verified,
        SUM(reviewer_verification_status = 'rejected')            AS verif_rejected
    FROM eligibility_results
")->fetch();

$statTotal        = (int)($stats['total']         ?? 0);
$statPassed       = (int)($stats['passed']        ?? 0);
$statFailed       = (int)($stats['failed']        ?? 0);
$statVerifPending = (int)($stats['verif_pending'] ?? 0);
$passRate         = $statTotal > 0 ? round(($statPassed / $statTotal) * 100, 1) : 0;

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Eligibility Results</h1>
    <p class="page-subtitle">
      Read-only view of Eligibility Engine outputs. PASS / FAIL status is set exclusively by the engine.
    </p>
  </div>
  <div class="d-flex gap-2">
    <a href="export.php?format=excel<?= $search ? '&search='.urlencode($search) : '' ?><?= $filterResult ? '&result='.urlencode($filterResult) : '' ?><?= $filterProgram ? '&program_id='.$filterProgram : '' ?><?= $filterVerif ? '&verif_status='.urlencode($filterVerif) : '' ?><?= $filterDate ? '&eval_date='.urlencode($filterDate) : '' ?>"
       class="btn btn-success" id="btn-export-excel">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel
    </a>
    <a href="export.php?format=pdf<?= $search ? '&search='.urlencode($search) : '' ?><?= $filterResult ? '&result='.urlencode($filterResult) : '' ?><?= $filterProgram ? '&program_id='.$filterProgram : '' ?><?= $filterVerif ? '&verif_status='.urlencode($filterVerif) : '' ?><?= $filterDate ? '&eval_date='.urlencode($filterDate) : '' ?>"
       class="btn btn-danger" id="btn-export-pdf">
      <i class="bi bi-file-earmark-pdf me-1"></i> PDF
    </a>
    <a href="../eligibility_engine/index.php" class="btn btn-primary" id="btn-go-engine">
      <i class="bi bi-cpu me-1"></i> Engine
    </a>
  </div>
</div>

<?php showFlash(); ?>

<!-- ── Read-only notice ───────────────────────────────────────── -->
<div class="alert alert-info mb-4" style="border-left:4px solid var(--info);font-size:13px;padding:10px 16px;">
  <i class="bi bi-shield-lock me-2"></i>
  <strong>View Only:</strong> PASS / FAIL outcomes are generated exclusively by the Eligibility Engine.
  Administrators cannot manually change eligibility status.
</div>

<!-- ── Statistics Dashboard ─────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-clipboard2-check"></i></div>
      <div class="stat-body">
        <div class="stat-label">Total Results</div>
        <div class="stat-value"><?= $statTotal ?></div>
        <div class="stat-trend">All programs</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-patch-check-fill"></i></div>
      <div class="stat-body">
        <div class="stat-label">Passed</div>
        <div class="stat-value"><?= $statPassed ?></div>
        <div class="stat-trend"><?= $passRate ?>% pass rate</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon red"><i class="bi bi-shield-exclamation"></i></div>
      <div class="stat-body">
        <div class="stat-label">Failed</div>
        <div class="stat-value"><?= $statFailed ?></div>
        <div class="stat-trend">Ineligible</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon yellow"><i class="bi bi-hourglass-split"></i></div>
      <div class="stat-body">
        <div class="stat-label">Pending Verification</div>
        <div class="stat-value"><?= $statVerifPending ?></div>
        <div class="stat-trend">Awaiting reviewer</div>
      </div>
    </div>
  </div>
</div>

<!-- ── Search & Filter Bar ───────────────────────────────────── -->
<div class="table-card mb-3" style="padding:16px 20px;">
  <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">

    <!-- Search -->
    <div style="flex:1;min-width:200px;">
      <label class="form-label" style="margin-bottom:4px;">Search</label>
      <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="search" class="form-control"
               placeholder="Student name, ID, program…" value="<?= e($search) ?>">
      </div>
    </div>

    <!-- Program -->
    <div style="min-width:180px;">
      <label class="form-label" style="margin-bottom:4px;">Program</label>
      <select name="program_id" class="form-select form-select-sm">
        <option value="">All Programs</option>
        <?php foreach ($programs as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $filterProgram == $p['id'] ? 'selected' : '' ?>>
            <?= e($p['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Eligibility result -->
    <div style="min-width:140px;">
      <label class="form-label" style="margin-bottom:4px;">Eligibility</label>
      <select name="result" class="form-select form-select-sm">
        <option value="">All Results</option>
        <option value="pass" <?= $filterResult === 'pass' ? 'selected' : '' ?>>PASS</option>
        <option value="fail" <?= $filterResult === 'fail' ? 'selected' : '' ?>>FAIL</option>
      </select>
    </div>

    <!-- Reviewer verification -->
    <div style="min-width:160px;">
      <label class="form-label" style="margin-bottom:4px;">Verification</label>
      <select name="verif_status" class="form-select form-select-sm">
        <option value="">All Statuses</option>
        <option value="pending"  <?= $filterVerif === 'pending'  ? 'selected' : '' ?>>Pending</option>
        <option value="verified" <?= $filterVerif === 'verified' ? 'selected' : '' ?>>Verified</option>
        <option value="rejected" <?= $filterVerif === 'rejected' ? 'selected' : '' ?>>Rejected</option>
      </select>
    </div>

    <!-- Evaluation date -->
    <div style="min-width:140px;">
      <label class="form-label" style="margin-bottom:4px;">Eval Date</label>
      <input type="date" name="eval_date" class="form-control form-control-sm"
             value="<?= e($filterDate) ?>">
    </div>

    <div class="d-flex gap-1" style="padding-top:22px;">
      <button type="submit" class="btn btn-sm btn-primary" id="filter-btn">
        <i class="bi bi-funnel"></i> Filter
      </button>
      <?php if ($search || $filterResult || $filterVerif || $filterProgram || $filterDate): ?>
        <a href="index.php" class="btn btn-sm btn-secondary"><i class="bi bi-x-lg"></i> Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- ── Results Table ─────────────────────────────────────────── -->
<div class="table-card">
  <div class="table-card-header">
    <span class="table-card-title">
      <?php if ($search || $filterResult || $filterVerif || $filterProgram || $filterDate): ?>
        <?= count($rows) ?> result<?= count($rows) !== 1 ? 's' : '' ?> found
      <?php else: ?>
        All Eligibility Results
      <?php endif; ?>
    </span>
    <span style="font-size:12px;color:var(--gray-400);">Showing up to 500 most recent</span>
  </div>
  <div class="table-responsive">
    <table class="table" id="results-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Student</th>
          <th>Program</th>
          <th>Eligibility</th>
          <th>Fail Reasons</th>
          <th>Verification</th>
          <th>Evaluated</th>
          <th>Detail</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="8">
              <div class="empty-state" style="padding:48px 24px;">
                <span class="empty-state-icon"><i class="bi bi-clipboard2-x"></i></span>
                <div class="empty-state-title">No eligibility results found</div>
                <div class="empty-state-text">
                  <?= ($search || $filterResult || $filterVerif || $filterProgram || $filterDate)
                      ? 'Try adjusting your search or filters.'
                      : 'Run the Eligibility Engine to generate evaluation records.' ?>
                </div>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $r):
            $passed = (int)$r['is_passed'];
            $verifStatus = $r['reviewer_verification_status'] ?? 'pending';

            // Parse fail reasons
            $failParts = [];
            $reason = $r['reason'] ?? '';
            if (!$passed && str_starts_with($reason, 'Failed criteria:')) {
                $txt = trim(substr($reason, strlen('Failed criteria:')));
                $failParts = array_values(array_filter(array_map('trim', explode(';', $txt))));
            }
          ?>
            <tr>
              <td><span class="text-muted">#<?= e($r['id']) ?></span></td>
              <td>
                <strong><?= e($r['student_name']) ?></strong>
                <?php if ($r['student_code']): ?>
                  <div style="font-size:11px;color:var(--gray-400);"><?= e($r['student_code']) ?></div>
                <?php endif; ?>
              </td>
              <td style="font-size:12.5px;"><?= e($r['program_name']) ?></td>
              <td>
                <?php if ($passed): ?>
                  <span class="badge badge-eligible">
                    <i class="bi bi-patch-check-fill me-1" style="font-size:9px;"></i>PASS
                  </span>
                <?php else: ?>
                  <span class="badge badge-ineligible">
                    <i class="bi bi-shield-exclamation me-1" style="font-size:9px;"></i>FAIL
                  </span>
                <?php endif; ?>
              </td>
              <td style="max-width:240px;">
                <?php if ($passed): ?>
                  <span style="font-size:12px;color:var(--success);">
                    <i class="bi bi-check-circle me-1"></i>Meets all criteria.
                  </span>
                <?php elseif ($failParts): ?>
                  <ul style="margin:0;padding-left:14px;font-size:12px;color:var(--danger);">
                    <?php foreach ($failParts as $fp): ?>
                      <li><?= e($fp) ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php else: ?>
                  <span style="font-size:12px;color:var(--gray-500);"><?= e($reason ?: '—') ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php
                $verifIcon  = match($verifStatus) {
                    'verified' => 'bi-person-check-fill',
                    'rejected' => 'bi-person-x-fill',
                    default    => 'bi-hourglass-split',
                };
                $verifClass = match($verifStatus) {
                    'verified' => 'badge-eligible',
                    'rejected' => 'badge-ineligible',
                    default    => 'badge-warning',
                };
                $verifLabel = match($verifStatus) {
                    'verified' => 'Verified',
                    'rejected' => 'Rejected',
                    default    => 'Pending',
                };
                ?>
                <span class="badge <?= $verifClass ?>" style="font-size:10.5px;">
                  <i class="bi <?= $verifIcon ?> me-1" style="font-size:9px;"></i><?= $verifLabel ?>
                </span>
              </td>
              <td style="font-size:11.5px;color:var(--gray-400);white-space:nowrap;">
                <?php if ($r['checked_at']): ?>
                  <?= e(date('d M Y', strtotime($r['checked_at']))) ?><br>
                  <span style="font-size:10.5px;"><?= e(date('H:i', strtotime($r['checked_at']))) ?></span>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td>
                <a href="view.php?id=<?= $r['id'] ?>"
                   class="btn btn-sm btn-outline-primary btn-action"
                   id="view-result-<?= $r['id'] ?>"
                   title="View Details">
                  <i class="bi bi-eye"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>