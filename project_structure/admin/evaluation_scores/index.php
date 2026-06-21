<?php
// ============================================================
// admin/evaluation_scores/index.php
// Admin view-only dashboard for evaluation scores.
// Admin cannot modify reviewer scores.
// ============================================================
$pageTitle = 'Evaluation Scores';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// ── Auto-migration ────────────────────────────────────────────
try {
    $pdo->exec("ALTER TABLE evaluation_scores
        ADD COLUMN IF NOT EXISTS verification_status
            ENUM('verified','need_clarification','rejected_evidence') NOT NULL DEFAULT 'verified' AFTER note,
        ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP AFTER verification_status
    ");
} catch (Exception $e) {}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS evaluation_score_history (
        id              INT(11)      AUTO_INCREMENT PRIMARY KEY,
        score_id        INT(11)      NOT NULL,
        application_id  INT(11)      NOT NULL,
        criteria_id     INT(11)      NOT NULL,
        reviewer_id     INT(11)      NOT NULL,
        old_score       DECIMAL(6,2) DEFAULT NULL,
        new_score       DECIMAL(6,2) NOT NULL,
        old_note        TEXT         DEFAULT NULL,
        new_note        TEXT         DEFAULT NULL,
        old_verification_status VARCHAR(50) DEFAULT NULL,
        new_verification_status VARCHAR(50) DEFAULT NULL,
        changed_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (score_id)       REFERENCES evaluation_scores(id) ON DELETE CASCADE,
        FOREIGN KEY (application_id) REFERENCES applications(id)      ON DELETE CASCADE,
        FOREIGN KEY (criteria_id)    REFERENCES scoring_criteria(id)  ON DELETE CASCADE,
        FOREIGN KEY (reviewer_id)    REFERENCES users(id)             ON DELETE CASCADE
    )
");

// ── Search & Filter params ────────────────────────────────────
$search          = trim($_GET['search']        ?? '');
$filterProgram   = (int)($_GET['program_id']   ?? 0);
$filterReviewer  = (int)($_GET['reviewer_id']  ?? 0);
$filterVerif     = trim($_GET['verif_status']  ?? '');
$filterEligible  = trim($_GET['eligible']      ?? '');  // 'pass' | 'fail' | 'pending'

// ── Programs & reviewers for filter dropdowns ─────────────────
$programs  = $pdo->query("SELECT id, name FROM scholarship_programs ORDER BY name ASC")->fetchAll();
$reviewers = $pdo->query("SELECT id, full_name FROM users WHERE role = 'reviewer' ORDER BY full_name ASC")->fetchAll();

// ── Build WHERE ───────────────────────────────────────────────
$where  = [];
$params = [];

if ($search !== '') {
    $like     = "%$search%";
    $where[]  = "(u.full_name LIKE ? OR su.full_name LIKE ? OR sp.name LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($filterProgram > 0)  { $where[] = "a.program_id = ?";   $params[] = $filterProgram; }
if ($filterReviewer > 0) { $where[] = "es.council_id = ?";  $params[] = $filterReviewer; }
if ($filterVerif !== '') { $where[] = "es.verification_status = ?"; $params[] = $filterVerif; }
if ($filterEligible === 'pass')    $where[] = "a.eligible = 1";
if ($filterEligible === 'fail')    $where[] = "a.eligible = 0";
if ($filterEligible === 'pending') $where[] = "a.eligible IS NULL";

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ── Fetch scores grouped by application ──────────────────────
// One row per application × reviewer combination (aggregate)
$appScores = $pdo->prepare("
    SELECT
        a.id                   AS app_id,
        a.eligible             AS eligible,
        a.status               AS app_status,
        su.full_name           AS student_name,
        su.student_code        AS student_code,
        sp.id                  AS program_id,
        sp.name                AS program_name,
        u.full_name            AS reviewer_name,
        u.id                   AS reviewer_id,
        COUNT(es.id)           AS criteria_scored,
        ROUND(SUM(es.score * sc.weight / 100), 2) AS weighted_total,
        MAX(es.scored_at)      AS last_scored_at,
        MAX(es.verification_status)                      AS verif_status,
        GROUP_CONCAT(DISTINCT es.verification_status)    AS all_verif_statuses
    FROM evaluation_scores es
    JOIN applications a          ON es.application_id = a.id
    JOIN users su                ON a.student_id      = su.id
    JOIN scholarship_programs sp ON a.program_id      = sp.id
    JOIN users u                 ON es.council_id     = u.id
    JOIN scoring_criteria sc     ON es.criteria_id    = sc.id
    $whereSql
    GROUP BY a.id, es.council_id
    ORDER BY last_scored_at DESC
    LIMIT 300
");
$appScores->execute($params);
$appScores = $appScores->fetchAll();

// ── Global statistics (always full dataset) ───────────────────
$stats = $pdo->query("
    SELECT
        COUNT(DISTINCT es.application_id)                        AS total_reviewed,
        (SELECT COUNT(*) FROM applications
         WHERE eligible = 1 AND status NOT IN ('draft'))         AS eligible_apps,
        (SELECT COUNT(DISTINCT a2.id) FROM applications a2
         WHERE a2.eligible = 1
           AND NOT EXISTS (
               SELECT 1 FROM evaluation_scores es2
               WHERE es2.application_id = a2.id
           )) AS pending_review,
        ROUND(AVG(agg.total), 2) AS avg_score,
        MAX(agg.total)           AS high_score,
        MIN(agg.total)           AS low_score
    FROM evaluation_scores es
    JOIN (
        SELECT es_inner.application_id AS application_id,
               ROUND(SUM(es_inner.score * sc_inner.weight / 100), 2) AS total
        FROM evaluation_scores es_inner
        JOIN scoring_criteria sc_inner ON es_inner.criteria_id = sc_inner.id
        GROUP BY es_inner.application_id
    ) agg ON agg.application_id = es.application_id
")->fetch();

$statReviewed = (int)($stats['total_reviewed'] ?? 0);
$statPending  = (int)($stats['pending_review'] ?? 0);
$statAvg      = number_format((float)($stats['avg_score'] ?? 0), 2);
$statHigh     = number_format((float)($stats['high_score'] ?? 0), 2);
$statLow      = number_format((float)($stats['low_score']  ?? 0), 2);

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>
<div class="container py-4">
<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Evaluation Scores</h1>
    <p class="page-subtitle">
      Reviewer criterion scores per application. Admin view-only —
      scores are submitted by reviewers and feed into Ranking Results.
    </p>
  </div>
  <div class="d-flex gap-2">
    <a href="export.php?format=excel<?= $filterProgram ? '&program_id='.$filterProgram : '' ?><?= $search ? '&search='.urlencode($search) : '' ?>"
       class="btn btn-success" id="btn-export-excel">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel
    </a>
    <a href="export.php?format=pdf<?= $filterProgram ? '&program_id='.$filterProgram : '' ?><?= $search ? '&search='.urlencode($search) : '' ?>"
       class="btn btn-danger" id="btn-export-pdf">
      <i class="bi bi-file-earmark-pdf me-1"></i> PDF
    </a>
  </div>
</div>

<?php showFlash(); ?>

<!-- ── Admin View-Only Notice ────────────────────────────────── -->
<div class="alert alert-info mb-4" style="border-left:4px solid var(--info);font-size:13px;padding:10px 16px;">
  <i class="bi bi-shield-lock me-2"></i>
  <strong>View Only:</strong> Evaluation scores are submitted by reviewers. Administrators cannot modify scores.
  Scores are used as input for the Ranking Results module.
</div>

<!-- ── Workflow Banner ───────────────────────────────────────── -->
<div style="background:linear-gradient(135deg,rgba(37,99,235,.05),rgba(124,58,237,.05));
            border:1px solid rgba(37,99,235,.12);border-radius:var(--radius-md);
            padding:12px 20px;margin-bottom:24px;overflow-x:auto;">
  <div style="display:flex;align-items:center;gap:0;min-width:580px;">
    <?php
    $steps = [
        ['icon'=>'bi-person-check','label'=>'Application','active'=>false],
        ['icon'=>'bi-cpu','label'=>'Eligibility Engine','active'=>false],
        ['icon'=>'bi-patch-check','label'=>'PASS','active'=>false],
        ['icon'=>'bi-person-badge','label'=>'Reviewer Verif.','active'=>false],
        ['icon'=>'bi-star-half','label'=>'Evaluation Scores','active'=>true],
        ['icon'=>'bi-bar-chart-steps','label'=>'Ranking Results','active'=>false],
    ];
    foreach ($steps as $i => $step):
      $c  = $step['active'] ? 'var(--primary)' : 'var(--gray-300)';
      $bg = $step['active'] ? 'rgba(37,99,235,.12)' : 'transparent';
    ?>
      <?php if ($i > 0): ?>
        <div style="flex:1;height:2px;background:var(--gray-200);margin:0 2px;min-width:12px;"></div>
      <?php endif; ?>
      <div style="display:flex;flex-direction:column;align-items:center;gap:3px;min-width:68px;">
        <div style="width:32px;height:32px;border-radius:50%;background:<?= $bg ?>;
                    border:2px solid <?= $c ?>;display:flex;align-items:center;justify-content:center;">
          <i class="bi <?= $step['icon'] ?>" style="color:<?= $c ?>;font-size:13px;"></i>
        </div>
        <span style="font-size:9.5px;font-weight:<?= $step['active'] ? '700' : '500' ?>;
                     color:<?= $c ?>;text-align:center;line-height:1.3;"><?= $step['label'] ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── Statistics Dashboard ─────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl" style="min-width:140px;">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-clipboard2-check"></i></div>
      <div class="stat-body">
        <div class="stat-label">Total Reviewed</div>
        <div class="stat-value"><?= $statReviewed ?></div>
        <div class="stat-trend">Applications</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl" style="min-width:140px;">
    <div class="stat-card">
      <div class="stat-icon yellow"><i class="bi bi-hourglass-split"></i></div>
      <div class="stat-body">
        <div class="stat-label">Pending Review</div>
        <div class="stat-value"><?= $statPending ?></div>
        <div class="stat-trend">Eligible, unscored</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl" style="min-width:140px;">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(99,102,241,.1);color:#6366f1;"><i class="bi bi-calculator"></i></div>
      <div class="stat-body">
        <div class="stat-label">Average Score</div>
        <div class="stat-value"><?= $statAvg ?></div>
        <div class="stat-trend">Weighted avg.</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl" style="min-width:140px;">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-arrow-up-circle"></i></div>
      <div class="stat-body">
        <div class="stat-label">Highest Score</div>
        <div class="stat-value"><?= $statHigh ?></div>
        <div class="stat-trend">Top performer</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl" style="min-width:140px;">
    <div class="stat-card">
      <div class="stat-icon red"><i class="bi bi-arrow-down-circle"></i></div>
      <div class="stat-body">
        <div class="stat-label">Lowest Score</div>
        <div class="stat-value"><?= $statLow ?></div>
        <div class="stat-trend">Lowest reviewed</div>
      </div>
    </div>
  </div>
</div>

<!-- ── Search & Filter Bar ───────────────────────────────────── -->
<div class="table-card mb-3" style="padding:16px 20px;">
  <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
    <div style="flex:1;min-width:180px;">
      <label class="form-label" style="margin-bottom:4px;">Search</label>
      <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="search" class="form-control"
               placeholder="Student, reviewer, program…" value="<?= e($search) ?>">
      </div>
    </div>
    <div style="min-width:170px;">
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
    <div style="min-width:160px;">
      <label class="form-label" style="margin-bottom:4px;">Reviewer</label>
      <select name="reviewer_id" class="form-select form-select-sm">
        <option value="">All Reviewers</option>
        <?php foreach ($reviewers as $rv): ?>
          <option value="<?= $rv['id'] ?>" <?= $filterReviewer == $rv['id'] ? 'selected' : '' ?>>
            <?= e($rv['full_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:160px;">
      <label class="form-label" style="margin-bottom:4px;">Evidence Status</label>
      <select name="verif_status" class="form-select form-select-sm">
        <option value="">All Statuses</option>
        <option value="verified"          <?= $filterVerif === 'verified'          ? 'selected' : '' ?>>Verified</option>
        <option value="need_clarification"<?= $filterVerif === 'need_clarification'? 'selected' : '' ?>>Need Clarification</option>
        <option value="rejected_evidence" <?= $filterVerif === 'rejected_evidence' ? 'selected' : '' ?>>Rejected Evidence</option>
      </select>
    </div>
    <div style="min-width:140px;">
      <label class="form-label" style="margin-bottom:4px;">Eligibility</label>
      <select name="eligible" class="form-select form-select-sm">
        <option value="">All</option>
        <option value="pass"    <?= $filterEligible === 'pass'    ? 'selected' : '' ?>>PASS</option>
        <option value="fail"    <?= $filterEligible === 'fail'    ? 'selected' : '' ?>>FAIL</option>
        <option value="pending" <?= $filterEligible === 'pending' ? 'selected' : '' ?>>Pending</option>
      </select>
    </div>
    <div class="d-flex gap-1" style="padding-top:22px;">
      <button type="submit" class="btn btn-sm btn-primary" id="filter-btn">
        <i class="bi bi-funnel"></i> Filter
      </button>
      <?php if ($search || $filterProgram || $filterReviewer || $filterVerif || $filterEligible): ?>
        <a href="index.php" class="btn btn-sm btn-secondary"><i class="bi bi-x-lg"></i></a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- ── Application Score Summary Table ───────────────────────── -->
<div class="table-card">
  <div class="table-card-header">
    <span class="table-card-title">Application Score Summaries</span>
    <span style="font-size:12px;color:var(--gray-400);"><?= count($appScores) ?> records</span>
  </div>
  <div class="table-responsive">
    <table class="table" id="scores-table">
      <thead>
        <tr>
          <th>App #</th>
          <th>Student</th>
          <th>Program</th>
          <th>Eligibility</th>
          <th>Reviewer</th>
          <th>Criteria Scored</th>
          <th>Weighted Score</th>
          <th>Evidence Status</th>
          <th>Last Scored</th>
          <th>Detail</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($appScores)): ?>
          <tr>
            <td colspan="10">
              <div class="empty-state" style="padding:48px 24px;">
                <span class="empty-state-icon"><i class="bi bi-star-half"></i></span>
                <div class="empty-state-title">No evaluation scores found</div>
                <div class="empty-state-text">
                  <?= ($search || $filterProgram || $filterReviewer || $filterVerif || $filterEligible)
                      ? 'Try adjusting your filters.' : 'Scores are submitted by reviewers for eligible applications.' ?>
                </div>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($appScores as $row):
            $eligible    = $row['eligible'];
            $verifStatus = $row['verif_status'] ?? 'verified';

            // Verification badge
            $verifLabel = match($verifStatus) {
                'need_clarification' => 'Need Clarification',
                'rejected_evidence'  => 'Rejected Evidence',
                default              => 'Verified',
            };
            $verifClass = match($verifStatus) {
                'need_clarification' => 'badge-warning',
                'rejected_evidence'  => 'badge-ineligible',
                default              => 'badge-eligible',
            };

            // Check if all verif statuses have issues
            $allStatuses = explode(',', $row['all_verif_statuses'] ?? 'verified');
            $hasIssue = in_array('need_clarification', $allStatuses) || in_array('rejected_evidence', $allStatuses);
          ?>
            <tr style="<?= $hasIssue ? 'background:rgba(250,204,21,.04);' : '' ?>">
              <td>
                <a href="view.php?app_id=<?= $row['app_id'] ?>&reviewer_id=<?= $row['reviewer_id'] ?>"
                   class="fw-semibold text-primary">
                  #<?= e($row['app_id']) ?>
                </a>
              </td>
              <td>
                <strong><?= e($row['student_name']) ?></strong>
                <?php if ($row['student_code']): ?>
                  <div style="font-size:11px;color:var(--gray-400);"><?= e($row['student_code']) ?></div>
                <?php endif; ?>
              </td>
              <td style="font-size:12.5px;"><?= e($row['program_name']) ?></td>
              <td>
                <?php if ($eligible === null): ?>
                  <span class="badge badge-warning" style="font-size:10.5px;">Pending</span>
                <?php elseif ($eligible == 1): ?>
                  <span class="badge badge-eligible" style="font-size:10.5px;">
                    <i class="bi bi-patch-check-fill" style="font-size:9px;"></i> PASS
                  </span>
                <?php else: ?>
                  <span class="badge badge-ineligible" style="font-size:10.5px;">
                    <i class="bi bi-shield-exclamation" style="font-size:9px;"></i> FAIL
                  </span>
                <?php endif; ?>
              </td>
              <td style="font-size:12.5px;"><?= e($row['reviewer_name']) ?></td>
              <td style="text-align:center;">
                <span style="font-weight:700;font-size:14px;"><?= e($row['criteria_scored']) ?></span>
                <span style="font-size:11px;color:var(--gray-400);"> criteria</span>
              </td>
              <td>
                <strong style="font-size:16px;color:var(--primary);">
                  <?= number_format((float)$row['weighted_total'], 2) ?>
                </strong>
                <div style="font-size:10.5px;color:var(--gray-400);">weighted</div>
              </td>
              <td>
                <span class="badge <?= $verifClass ?>" style="font-size:10.5px;">
                  <?= e($verifLabel) ?>
                </span>
              </td>
              <td style="font-size:11.5px;color:var(--gray-400);white-space:nowrap;">
                <?= $row['last_scored_at'] ? e(date('d M Y, H:i', strtotime($row['last_scored_at']))) : '—' ?>
              </td>
              <td>
                <a href="view.php?app_id=<?= $row['app_id'] ?>&reviewer_id=<?= $row['reviewer_id'] ?>"
                   class="btn btn-sm btn-outline-primary btn-action"
                   id="view-score-<?= $row['app_id'] ?>-<?= $row['reviewer_id'] ?>"
                   title="View Score Detail">
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
                </div>
<?php require_once '../../includes/footer.php'; ?>
