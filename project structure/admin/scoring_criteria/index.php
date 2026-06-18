<?php
// ============================================================
// admin/scoring_criteria/index.php
// Scoring Criteria: list with search, filter, weight totals,
// status badges, audit trail, reviewer request panel.
// ============================================================
$pageTitle = 'Scoring Criteria';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// ── Auto-migration: add columns if not present ───────────────
try {
    $pdo->exec("ALTER TABLE scoring_criteria
        ADD COLUMN IF NOT EXISTS description TEXT       DEFAULT NULL        AFTER criterion_name,
        ADD COLUMN IF NOT EXISTS is_active   TINYINT(1) NOT NULL DEFAULT 1  AFTER max_score,
        ADD COLUMN IF NOT EXISTS updated_by  INT(11)    DEFAULT NULL        AFTER is_active,
        ADD COLUMN IF NOT EXISTS updated_at  TIMESTAMP  DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP                                      AFTER updated_by
    ");
} catch (Exception $e) { /* already migrated */ }

// ── Auto-create reviewer requests table ──────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS scoring_criteria_requests (
        id                     INT(11)       AUTO_INCREMENT PRIMARY KEY,
        reviewer_id            INT(11)       NOT NULL,
        criterion_id           INT(11)       DEFAULT NULL,
        program_id             INT(11)       NOT NULL,
        current_criterion_name VARCHAR(100)  DEFAULT NULL,
        current_weight         DECIMAL(5,2)  DEFAULT NULL,
        current_max_score      DECIMAL(5,2)  DEFAULT NULL,
        proposed_criterion_name VARCHAR(100) DEFAULT NULL,
        proposed_weight        DECIMAL(5,2)  DEFAULT NULL,
        proposed_max_score     DECIMAL(5,2)  DEFAULT NULL,
        proposed_description   TEXT          DEFAULT NULL,
        reason                 TEXT          DEFAULT NULL,
        status                 ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        admin_id               INT(11)       DEFAULT NULL,
        admin_note             TEXT          DEFAULT NULL,
        responded_at           DATETIME      DEFAULT NULL,
        requested_at           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reviewer_id)  REFERENCES users(id)              ON DELETE CASCADE,
        FOREIGN KEY (criterion_id) REFERENCES scoring_criteria(id)   ON DELETE SET NULL,
        FOREIGN KEY (program_id)   REFERENCES scholarship_programs(id) ON DELETE CASCADE,
        FOREIGN KEY (admin_id)     REFERENCES users(id)              ON DELETE SET NULL
    )
");

// ── Handle reviewer request approve / reject ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['req_action'])) {
    $reqId     = (int)($_POST['req_id']    ?? 0);
    $reqAct    = $_POST['req_action'];
    $adminNote = trim($_POST['admin_note'] ?? '');
    $adminId   = currentUserId();
    $newStatus = ($reqAct === 'approve') ? 'approved' : 'rejected';

    if ($reqId > 0) {
        if ($reqAct === 'approve') {
            $rq = $pdo->prepare("SELECT * FROM scoring_criteria_requests WHERE id = ?");
            $rq->execute([$reqId]);
            $rqRow = $rq->fetch();

            if ($rqRow) {
                if ($rqRow['criterion_id']) {
                    // Update existing criterion
                    $sets = [];
                    $vals = [];
                    if ($rqRow['proposed_criterion_name']) { $sets[] = 'criterion_name = ?'; $vals[] = $rqRow['proposed_criterion_name']; }
                    if ($rqRow['proposed_weight']  !== null) { $sets[] = 'weight = ?';     $vals[] = $rqRow['proposed_weight']; }
                    if ($rqRow['proposed_max_score'] !== null) { $sets[] = 'max_score = ?'; $vals[] = $rqRow['proposed_max_score']; }
                    if ($rqRow['proposed_description'] !== null) { $sets[] = 'description = ?'; $vals[] = $rqRow['proposed_description']; }
                    if ($sets) {
                        $sets[] = 'updated_by = ?'; $vals[] = $adminId;
                        $sets[] = 'updated_at = NOW()';
                        $vals[] = $rqRow['criterion_id'];
                        $pdo->prepare("UPDATE scoring_criteria SET " . implode(', ', $sets) . " WHERE id = ?")
                            ->execute($vals);
                    }
                } else {
                    // Insert new criterion
                    $pdo->prepare("
                        INSERT INTO scoring_criteria
                            (program_id, criterion_name, weight, max_score, description, is_active, updated_by)
                        VALUES (?, ?, ?, ?, ?, 1, ?)
                    ")->execute([
                        $rqRow['program_id'],
                        $rqRow['proposed_criterion_name'] ?? 'New Criterion',
                        $rqRow['proposed_weight']   ?? 0,
                        $rqRow['proposed_max_score'] ?? 100,
                        $rqRow['proposed_description'],
                        $adminId
                    ]);
                }
            }
        }

        $pdo->prepare("
            UPDATE scoring_criteria_requests
            SET status = ?, admin_id = ?, admin_note = ?, responded_at = NOW()
            WHERE id = ?
        ")->execute([$newStatus, $adminId, $adminNote ?: null, $reqId]);

        setFlash('success', 'Reviewer request #' . $reqId . ' has been ' . $newStatus . '.');
    }
    header('Location: index.php');
    exit;
}

// ── Programs for filter dropdown ─────────────────────────────
$programs = $pdo->query("SELECT id, name FROM scholarship_programs ORDER BY name ASC")->fetchAll();

// ── Search & Filter params ───────────────────────────────────
$search        = trim($_GET['search']      ?? '');
$filterProgram = (int)($_GET['program_id'] ?? 0);
$filterStatus  = trim($_GET['is_active']   ?? '');

// ── Build WHERE ──────────────────────────────────────────────
$where  = [];
$params = [];

if ($search !== '') {
    $like     = "%$search%";
    $where[]  = "(sp.name LIKE ? OR sc.criterion_name LIKE ?)";
    $params[] = $like; $params[] = $like;
}
if ($filterProgram > 0) {
    $where[]  = "sc.program_id = ?";
    $params[] = $filterProgram;
}
if ($filterStatus !== '') {
    $where[]  = "sc.is_active = ?";
    $params[] = (int)$filterStatus;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ── Fetch all criteria ────────────────────────────────────────
$sql = "
    SELECT sc.*,
           sp.name AS program_name,
           u.full_name AS updated_by_name
    FROM scoring_criteria sc
    JOIN scholarship_programs sp ON sc.program_id = sp.id
    LEFT JOIN users u ON sc.updated_by = u.id
    $whereSql
    ORDER BY sp.name ASC, sc.weight DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$criteria = $stmt->fetchAll();

// ── Per-program weight totals (active only) ───────────────────
$weightTotals = [];
$wtStmt = $pdo->query("
    SELECT program_id, SUM(weight) AS total_weight
    FROM scoring_criteria
    WHERE is_active = 1
    GROUP BY program_id
");
foreach ($wtStmt->fetchAll() as $row) {
    $weightTotals[$row['program_id']] = (float)$row['total_weight'];
}

// ── Pending reviewer requests ────────────────────────────────
$pendingReqs = $pdo->query("
    SELECT scr.*,
           u.full_name AS reviewer_name,
           sp.name     AS program_name,
           sc.criterion_name AS current_criterion_name,
           sc.weight         AS current_weight,
           sc.max_score      AS current_max_score
    FROM scoring_criteria_requests scr
    JOIN users u ON scr.reviewer_id = u.id
    JOIN scholarship_programs sp ON scr.program_id = sp.id
    LEFT JOIN scoring_criteria sc ON scr.criterion_id = sc.id
    WHERE scr.status = 'pending'
    ORDER BY scr.requested_at DESC
")->fetchAll();

$pendingCount = count($pendingReqs);

// ── Global stats ─────────────────────────────────────────────
$totalCriteria    = (int)$pdo->query("SELECT COUNT(*)                FROM scoring_criteria")->fetchColumn();
$activeCriteria   = (int)$pdo->query("SELECT COUNT(*) FROM scoring_criteria WHERE is_active = 1")->fetchColumn();
$programsCount    = (int)$pdo->query("SELECT COUNT(DISTINCT program_id) FROM scoring_criteria")->fetchColumn();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Scoring Criteria</h1>
    <p class="page-subtitle">Configure reviewer grading metrics. Active criteria define how evaluation scores are calculated.</p>
  </div>
  <a href="create.php" class="btn btn-primary" id="btn-create-criterion">
    <i class="bi bi-plus-lg"></i> Add Criterion
  </a>
</div>

<?php showFlash(); ?>

<!-- ── Reviewer Request Banner ───────────────────────────────── -->
<?php if ($pendingCount > 0): ?>
  <div class="alert alert-warning mb-4" style="border-left:4px solid var(--warning);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <div>
      <i class="bi bi-bell-fill me-2"></i>
      <strong><?= $pendingCount ?> pending scoring request<?= $pendingCount > 1 ? 's' : '' ?></strong>
      from reviewers awaiting your decision.
    </div>
    <a href="#scoring-requests" class="btn btn-sm btn-warning">
      <i class="bi bi-arrow-down"></i> Review Now
    </a>
  </div>
<?php endif; ?>

<!-- ── Stat Cards ─────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-sm-4">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-star-half"></i></div>
      <div class="stat-body">
        <div class="stat-label">Total Criteria</div>
        <div class="stat-value"><?= $totalCriteria ?></div>
        <div class="stat-trend">Across all programs</div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
      <div class="stat-body">
        <div class="stat-label">Active Criteria</div>
        <div class="stat-value"><?= $activeCriteria ?></div>
        <div class="stat-trend">Used in scoring</div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(99,102,241,.12);color:#6366f1;"><i class="bi bi-award"></i></div>
      <div class="stat-body">
        <div class="stat-label">Programs</div>
        <div class="stat-value"><?= $programsCount ?></div>
        <div class="stat-trend">Have criteria defined</div>
      </div>
    </div>
  </div>
</div>

<!-- ── Search & Filter Bar ──────────────────────────────────── -->
<div class="table-card mb-3" style="padding:18px 24px;">
  <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
    <div style="flex:1;min-width:200px;">
      <label class="form-label" style="margin-bottom:5px;">Search</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="search" class="form-control"
               placeholder="Program name or criterion name…" value="<?= e($search) ?>">
      </div>
    </div>
    <div style="min-width:200px;">
      <label class="form-label" style="margin-bottom:5px;">Program</label>
      <select name="program_id" class="form-select">
        <option value="">All Programs</option>
        <?php foreach ($programs as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $filterProgram == $p['id'] ? 'selected' : '' ?>>
            <?= e($p['name']) ?>
            <?php
            $wt = $weightTotals[$p['id']] ?? 0;
            $wtColor = abs($wt - 100) < 0.01 ? '#16a34a' : '#dc2626';
            ?>
            — <span style="color:<?= $wtColor ?>;"><?= number_format($wt, 1) ?>%</span>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:140px;">
      <label class="form-label" style="margin-bottom:5px;">Status</label>
      <select name="is_active" class="form-select">
        <option value="">All Statuses</option>
        <option value="1" <?= $filterStatus === '1' ? 'selected' : '' ?>>Active</option>
        <option value="0" <?= $filterStatus === '0' ? 'selected' : '' ?>>Inactive</option>
      </select>
    </div>
    <div class="d-flex gap-2" style="padding-top:24px;">
      <button type="submit" class="btn btn-primary" id="filter-btn"><i class="bi bi-funnel"></i> Filter</button>
      <?php if ($search || $filterProgram || $filterStatus !== ''): ?>
        <a href="index.php" class="btn btn-secondary"><i class="bi bi-x-lg"></i> Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- ── Criteria Table ─────────────────────────────────────────── -->
<div class="table-card mb-4">
  <div class="table-card-header">
    <span class="table-card-title">
      <?= ($search || $filterProgram || $filterStatus !== '')
          ? count($criteria).' result'.(count($criteria)!==1?'s':'').' found'
          : 'All Scoring Criteria' ?>
    </span>
  </div>
  <div class="table-responsive">
    <table class="table" id="criteria-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Program</th>
          <th>Criterion</th>
          <th>Weight</th>
          <th>Max Score</th>
          <th>Status</th>
          <th>Updated By</th>
          <th>Updated At</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($criteria)): ?>
          <tr>
            <td colspan="9">
              <div class="empty-state" style="padding:48px 24px;">
                <span class="empty-state-icon"><i class="bi bi-star-half"></i></span>
                <div class="empty-state-title">No scoring criteria found</div>
                <div class="empty-state-text">
                  <?= ($search || $filterProgram || $filterStatus !== '')
                      ? 'Try adjusting your search or filters.'
                      : 'Click "Add Criterion" to define the first scoring criterion.' ?>
                </div>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php
          $currentProg = null;
          foreach ($criteria as $c):
            $isActive   = (bool)($c['is_active'] ?? 1);
            $progWeight = $weightTotals[$c['program_id']] ?? 0;
            $wtOk       = abs($progWeight - 100) < 0.01;
          ?>
            <!-- Program header row -->
            <?php if ($currentProg !== $c['program_id'] && !($filterProgram > 0)): ?>
              <?php $currentProg = $c['program_id']; ?>
              <tr style="background:var(--gray-50);">
                <td colspan="9" style="padding:10px 16px;">
                  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                    <span style="font-size:12px;font-weight:700;text-transform:uppercase;
                                 letter-spacing:.06em;color:var(--gray-500);">
                      <i class="bi bi-award me-1" style="color:var(--primary);"></i>
                      <?= e($c['program_name']) ?>
                    </span>
                    <!-- Weight total badge for this program -->
                    <span style="font-size:12px;font-weight:700;padding:3px 12px;border-radius:99px;
                                 background:<?= $wtOk ? 'rgba(22,163,74,.12)' : 'rgba(220,38,38,.1)' ?>;
                                 color:<?= $wtOk ? 'var(--success)' : 'var(--danger)' ?>;">
                      <i class="bi bi-<?= $wtOk ? 'check-circle' : 'exclamation-triangle' ?>-fill me-1"></i>
                      Total active weight: <?= number_format($progWeight, 1) ?>%
                      <?= !$wtOk ? '— should be 100%' : '' ?>
                    </span>
                  </div>
                </td>
              </tr>
            <?php endif; ?>

            <tr style="<?= !$isActive ? 'opacity:.55;' : '' ?>">
              <td><span class="text-muted">#<?= e($c['id']) ?></span></td>
              <td><?= ($filterProgram > 0) ? e($c['program_name']) : '<span style="display:none"></span>' ?></td>
              <td>
                <div>
                  <strong><?= e($c['criterion_name']) ?></strong>
                  <?php if (!empty($c['description'])): ?>
                    <div style="font-size:11.5px;color:var(--gray-400);margin-top:2px;
                                max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                         title="<?= e($c['description']) ?>">
                      <?= e($c['description']) ?>
                    </div>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <!-- Weight with mini bar -->
                <div style="display:flex;align-items:center;gap:8px;">
                  <strong style="font-size:15px;color:var(--primary);"><?= number_format((float)$c['weight'], 1) ?>%</strong>
                  <div style="width:56px;height:5px;background:var(--gray-200);border-radius:99px;overflow:hidden;">
                    <div style="width:<?= min(100, (float)$c['weight']) ?>%;height:100%;
                                background:var(--primary);border-radius:99px;"></div>
                  </div>
                </div>
              </td>
              <td><?= e($c['max_score']) ?></td>
              <td>
                <?php if ($isActive): ?>
                  <span class="badge badge-eligible">
                    <i class="bi bi-check-circle-fill" style="font-size:9px;"></i> Active
                  </span>
                <?php else: ?>
                  <span class="badge badge-inactive">
                    <i class="bi bi-pause-circle-fill" style="font-size:9px;"></i> Inactive
                  </span>
                <?php endif; ?>
              </td>
              <td style="font-size:12.5px;">
                <?= $c['updated_by_name'] ? e($c['updated_by_name']) : '<span class="text-muted">—</span>' ?>
              </td>
              <td style="font-size:12px;color:var(--gray-400);white-space:nowrap;">
                <?= isset($c['updated_at']) && $c['updated_at']
                    ? e(date('d M Y, H:i', strtotime($c['updated_at'])))
                    : '<span class="text-muted">—</span>' ?>
              </td>
              <td>
                <div class="d-flex gap-1">
                  <a href="edit.php?id=<?= $c['id'] ?>"
                     class="btn btn-sm btn-warning btn-action"
                     id="edit-criterion-<?= $c['id'] ?>">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <a href="toggle.php?id=<?= $c['id'] ?>"
                     class="btn btn-sm <?= $isActive ? 'btn-outline-secondary' : 'btn-success' ?> btn-action"
                     title="<?= $isActive ? 'Deactivate' : 'Activate' ?>"
                     id="toggle-criterion-<?= $c['id'] ?>">
                    <i class="bi bi-<?= $isActive ? 'pause' : 'play' ?>-fill"></i>
                  </a>
                  <a href="delete.php?id=<?= $c['id'] ?>"
                     class="btn btn-sm btn-danger btn-action"
                     onclick="return confirm('Delete criterion \'<?= addslashes(e($c['criterion_name'])) ?>\'?\n\nThis will affect future evaluations and rankings.')"
                     id="delete-criterion-<?= $c['id'] ?>">
                    <i class="bi bi-trash"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Per-Program Weight Summary ────────────────────────────── -->
<?php
// Build weight summary for ALL programs with criteria
$summaryRows = $pdo->query("
    SELECT sp.id, sp.name,
           SUM(CASE WHEN sc.is_active = 1 THEN sc.weight ELSE 0 END) AS active_weight,
           COUNT(*) AS total_count,
           SUM(CASE WHEN sc.is_active = 1 THEN 1 ELSE 0 END) AS active_count
    FROM scholarship_programs sp
    JOIN scoring_criteria sc ON sc.program_id = sp.id
    GROUP BY sp.id
    ORDER BY sp.name ASC
")->fetchAll();

if (count($summaryRows) > 0): ?>
<div class="card mb-4">
  <div class="card-body">
    <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
      <i class="bi bi-pie-chart me-2" style="color:var(--primary);"></i>Weight Summary per Program
    </div>
    <div class="row g-3">
      <?php foreach ($summaryRows as $sr):
        $wt   = (float)$sr['active_weight'];
        $ok   = abs($wt - 100) < 0.01;
        $pct  = min(100, $wt);
        $bar  = $ok ? 'var(--success)' : ($wt > 100 ? 'var(--danger)' : 'var(--warning)');
      ?>
        <div class="col-md-6 col-xl-4">
          <div style="border:1px solid <?= $ok ? 'rgba(22,163,74,.3)' : 'rgba(220,38,38,.25)' ?>;
                      border-radius:var(--radius-md);padding:14px 16px;">
            <div style="font-size:13px;font-weight:700;color:var(--gray-800);margin-bottom:6px;">
              <?= e($sr['name']) ?>
            </div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
              <div style="flex:1;height:7px;background:var(--gray-200);border-radius:99px;overflow:hidden;">
                <div style="width:<?= $pct ?>%;height:100%;background:<?= $bar ?>;border-radius:99px;"></div>
              </div>
              <strong style="font-size:14px;color:<?= $ok ? 'var(--success)' : 'var(--danger)' ?>;">
                <?= number_format($wt, 1) ?>%
              </strong>
            </div>
            <div style="font-size:11px;color:var(--gray-400);">
              <?= (int)$sr['active_count'] ?> active / <?= (int)$sr['total_count'] ?> total criteria
              <?php if (!$ok): ?>
                &nbsp;·&nbsp;<span style="color:var(--danger);font-weight:600;">
                  <?= $wt < 100 ? number_format(100 - $wt, 1).'% short' : number_format($wt - 100, 1).'% over' ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Reviewer Scoring Requests ─────────────────────────────── -->
<div class="table-card" id="scoring-requests">
  <div class="table-card-header">
    <span class="table-card-title">
      <i class="bi bi-inbox me-2" style="color:var(--warning);"></i>Reviewer Scoring Requests
    </span>
    <span class="badge badge-warning" style="font-size:12px;"><?= $pendingCount ?> pending</span>
  </div>

  <?php if (empty($pendingReqs)): ?>
    <div class="empty-state" style="padding:36px 24px;">
      <span class="empty-state-icon" style="font-size:36px;"><i class="bi bi-inbox"></i></span>
      <div class="empty-state-title" style="font-size:15px;">No pending scoring requests</div>
      <div class="empty-state-text">All reviewer proposals have been handled.</div>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Req #</th>
            <th>Reviewer</th>
            <th>Program</th>
            <th>Criterion</th>
            <th>Current Weight</th>
            <th>Proposed Weight</th>
            <th>Reason</th>
            <th>Requested</th>
            <th>Approve / Reject</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingReqs as $req): ?>
            <tr>
              <td><span class="text-muted">#<?= e($req['id']) ?></span></td>
              <td><?= e($req['reviewer_name']) ?></td>
              <td><?= e($req['program_name']) ?></td>
              <td>
                <?php if ($req['criterion_id'] && $req['current_criterion_name']): ?>
                  <strong><?= e($req['current_criterion_name']) ?></strong>
                <?php else: ?>
                  <span class="badge badge-info">New Criterion</span>
                  <?php if ($req['proposed_criterion_name']): ?>
                    <div style="font-size:11px;color:var(--gray-400);"><?= e($req['proposed_criterion_name']) ?></div>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($req['current_weight'] !== null): ?>
                  <strong><?= number_format((float)$req['current_weight'], 1) ?>%</strong>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($req['proposed_weight'] !== null): ?>
                  <strong style="color:var(--primary);"><?= number_format((float)$req['proposed_weight'], 1) ?>%</strong>
                  <?php if ($req['current_weight'] !== null):
                    $diff = (float)$req['proposed_weight'] - (float)$req['current_weight'];
                  ?>
                    <span style="font-size:11px;color:<?= $diff >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                      (<?= $diff >= 0 ? '+' : '' ?><?= number_format($diff, 1) ?>%)
                    </span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td style="max-width:180px;font-size:12.5px;color:var(--gray-600);">
                <?= e($req['reason'] ?: '—') ?>
              </td>
              <td class="text-muted" style="white-space:nowrap;font-size:12px;">
                <?= e(date('d M Y, H:i', strtotime($req['requested_at']))) ?>
              </td>
              <td>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="req_id"     value="<?= $req['id'] ?>">
                  <input type="hidden" name="req_action" value="approve">
                  <button type="submit" class="btn btn-sm btn-success btn-action"
                          id="approve-req-<?= $req['id'] ?>"
                          onclick="return confirm('Approve and apply this scoring change?')">
                    <i class="bi bi-check-lg"></i> Approve
                  </button>
                </form>
                <button type="button" class="btn btn-sm btn-danger btn-action"
                        id="reject-req-<?= $req['id'] ?>"
                        onclick="toggleRejectForm(<?= $req['id'] ?>)">
                  <i class="bi bi-x-lg"></i> Reject
                </button>
                <div id="reject-form-<?= $req['id'] ?>" style="display:none;margin-top:8px;">
                  <form method="POST">
                    <input type="hidden" name="req_id"     value="<?= $req['id'] ?>">
                    <input type="hidden" name="req_action" value="reject">
                    <div class="d-flex gap-2">
                      <input type="text" name="admin_note" class="form-control form-control-sm"
                             placeholder="Rejection reason (optional)">
                      <button type="submit" class="btn btn-sm btn-danger">Send</button>
                    </div>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ── Engine Notice ──────────────────────────────────────────── -->
<div class="alert alert-info mt-4" style="border-left:4px solid var(--info);font-size:13px;">
  <i class="bi bi-cpu me-2"></i>
  <strong>Scoring Engine:</strong> Only <strong>Active</strong> criteria are included when computing
  evaluation scores. Total active weight per program should sum to exactly <strong>100%</strong>.
  Approved reviewer changes take effect immediately on the <em>next</em> evaluation run.
</div>

<script>
function toggleRejectForm(id) {
    const f = document.getElementById('reject-form-' + id);
    if (f) f.style.display = f.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php require_once '../../includes/footer.php'; ?>