<?php
// ============================================================
// admin/eligibility_engine/index.php
// Eligibility Engine: run panel, program selection, summary,
// detailed results with rule trace, evaluation history, search.
// Scope: eligibility ONLY — no scoring, ranking, or awards.
// ============================================================
$pageTitle = 'Eligibility Engine';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// ── Auto-migration ───────────────────────────────────────────
try {
    $pdo->exec("ALTER TABLE eligibility_results
        ADD COLUMN IF NOT EXISTS rule_trace JSON DEFAULT NULL        AFTER reason,
        ADD COLUMN IF NOT EXISTS checked_by INT(11) DEFAULT NULL     AFTER rule_trace
    ");
} catch (Exception $e) {}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS engine_run_history (
        id            INT(11)      AUTO_INCREMENT PRIMARY KEY,
        program_id    INT(11)      DEFAULT NULL,
        total_checked INT(11)      NOT NULL DEFAULT 0,
        total_passed  INT(11)      NOT NULL DEFAULT 0,
        total_failed  INT(11)      NOT NULL DEFAULT 0,
        executed_by   INT(11)      NOT NULL,
        run_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        notes         VARCHAR(255) DEFAULT NULL,
        FOREIGN KEY (program_id)  REFERENCES scholarship_programs(id) ON DELETE SET NULL,
        FOREIGN KEY (executed_by) REFERENCES users(id) ON DELETE CASCADE
    )
");

require_once '../../includes/eligibility.php';

// ── Handle engine run (POST) ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_engine'])) {
    $programId  = (int)($_POST['program_id'] ?? 0) ?: null;
    $mode       = in_array($_POST['mode'] ?? '', ['all','pending']) ? $_POST['mode'] : 'all';
    $notes      = trim($_POST['notes'] ?? '');
    $adminId    = currentUserId();

    $summary = runEligibilityEngine($pdo, $programId, $adminId, $mode);

    // Update history notes if provided
    if ($notes) {
        $lastId = (int)$pdo->lastInsertId();
        if ($lastId) {
            $pdo->prepare("UPDATE engine_run_history SET notes = ? WHERE id = ?")
                ->execute([$notes, $lastId]);
        }
    }

    $prog = $programId
        ? $pdo->prepare("SELECT name FROM scholarship_programs WHERE id = ?")->execute([$programId])
        : null;

    $progName = 'All Programs';
    if ($programId) {
        $ps = $pdo->prepare("SELECT name FROM scholarship_programs WHERE id = ?");
        $ps->execute([$programId]);
        $progName = $ps->fetchColumn() ?: "Program #$programId";
    }

    setFlash('success', "Engine run complete for {$progName} ({$mode} mode). "
        . "Checked: {$summary['total']} · Passed: {$summary['passed']} · Failed: {$summary['failed']}.");
    header('Location: index.php');
    exit;
}

// ── Handle single re-run (GET) ───────────────────────────────
if (isset($_GET['check_id'])) {
    $checkId = (int)$_GET['check_id'];
    $adminId = currentUserId();
    $res = checkEligibility($pdo, $checkId, $adminId);
    $badge = $res['passed'] ? 'PASS ✓' : 'FAIL ✗';
    setFlash('success', "Re-evaluated Application #{$checkId}: {$badge}");
    header('Location: index.php');
    exit;
}

// ── Programs for run panel ────────────────────────────────────
$programs = $pdo->query("SELECT id, name FROM scholarship_programs ORDER BY name ASC")->fetchAll();

// ── Overview stats ────────────────────────────────────────────
$ov = $pdo->query("
    SELECT
        COUNT(*)              AS total,
        SUM(eligible IS NULL) AS pending,
        SUM(eligible = 1)     AS passed,
        SUM(eligible = 0)     AS failed
    FROM applications
")->fetch();

$statTotal   = (int)($ov['total']   ?? 0);
$statPending = (int)($ov['pending'] ?? 0);
$statPassed  = (int)($ov['passed']  ?? 0);
$statFailed  = (int)($ov['failed']  ?? 0);
$passRate    = $statTotal > 0 ? round(($statPassed / $statTotal) * 100, 1) : 0;

// ── Search & Filter params ────────────────────────────────────
$search        = trim($_GET['search']      ?? '');
$filterProgram = (int)($_GET['program_id'] ?? 0);
$filterResult  = trim($_GET['result']      ?? '');   // '' | 'pass' | 'fail' | 'pending'
$filterDate    = trim($_GET['eval_date']   ?? '');

// ── Build results query ───────────────────────────────────────
$where  = [];
$params = [];

if ($search !== '') {
    $like     = "%$search%";
    $where[]  = "(u.full_name LIKE ? OR u.student_code LIKE ? OR sp.name LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($filterProgram > 0) {
    $where[]  = "a.program_id = ?";
    $params[] = $filterProgram;
}
if ($filterResult === 'pass') {
    $where[] = "a.eligible = 1";
} elseif ($filterResult === 'fail') {
    $where[] = "a.eligible = 0";
} elseif ($filterResult === 'pending') {
    $where[] = "a.eligible IS NULL";
}
if ($filterDate !== '') {
    $where[]  = "DATE(er.checked_at) = ?";
    $params[] = $filterDate;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$results = $pdo->prepare("
    SELECT
        a.id           AS app_id,
        a.eligible,
        a.submitted_at,
        u.full_name    AS student_name,
        u.student_code,
        sp.name        AS program_name,
        sp.id          AS program_id,
        er.id          AS result_id,
        er.is_passed,
        er.reason,
        er.rule_trace,
        er.checked_at,
        cb.full_name   AS checked_by_name
    FROM applications a
    JOIN users u ON a.student_id = u.id
    JOIN scholarship_programs sp ON a.program_id = sp.id
    LEFT JOIN (
        SELECT application_id, id, is_passed, reason, rule_trace, checked_at, checked_by
        FROM eligibility_results
        WHERE id IN (SELECT MAX(id) FROM eligibility_results GROUP BY application_id)
    ) er ON er.application_id = a.id
    LEFT JOIN users cb ON er.checked_by = cb.id
    $whereSql
    ORDER BY er.checked_at DESC, a.id DESC
    LIMIT 200
");
$results->execute($params);
$results = $results->fetchAll();

// ── Evaluation history ────────────────────────────────────────
$history = $pdo->query("
    SELECT erh.*, sp.name AS program_name, u.full_name AS executed_by_name
    FROM engine_run_history erh
    LEFT JOIN scholarship_programs sp ON erh.program_id = sp.id
    JOIN users u ON erh.executed_by = u.id
    ORDER BY erh.run_at DESC
    LIMIT 20
")->fetchAll();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">
      <i class="bi bi-cpu me-2" style="color:var(--primary);"></i>Eligibility Engine
    </h1>
    <p class="page-subtitle">
      Evaluates applications against program rules → generates <strong>PASS</strong> / <strong>FAIL</strong> with detailed rule traces.
      Does <em>not</em> calculate scores, rankings, or awards.
    </p>
  </div>
</div>

<?php showFlash(); ?>

<!-- ── Workflow Banner ───────────────────────────────────────── -->
<div style="background:linear-gradient(135deg,rgba(37,99,235,.06),rgba(124,58,237,.06));
            border:1px solid rgba(37,99,235,.15);border-radius:var(--radius-md);
            padding:14px 20px;margin-bottom:24px;overflow-x:auto;">
  <div style="display:flex;align-items:center;gap:0;min-width:600px;flex-wrap:nowrap;">
    <?php
    $steps = [
        ['icon'=>'bi-person-check','label'=>'Application','active'=>false],
        ['icon'=>'bi-funnel','label'=>'Eligibility Rules','active'=>false],
        ['icon'=>'bi-cpu','label'=>'Eligibility Engine','active'=>true],
        ['icon'=>'bi-patch-check','label'=>'PASS / FAIL','active'=>false],
        ['icon'=>'bi-person-badge','label'=>'Reviewer Verification','active'=>false],
        ['icon'=>'bi-star-half','label'=>'Evaluation Scores','active'=>false],
        ['icon'=>'bi-bar-chart-steps','label'=>'Ranking Results','active'=>false],
    ];
    foreach ($steps as $i => $step):
      $c = $step['active'] ? 'var(--primary)' : 'var(--gray-400)';
      $bg = $step['active'] ? 'rgba(37,99,235,.12)' : 'transparent';
    ?>
      <?php if ($i > 0): ?>
        <div style="flex:1;height:2px;background:var(--gray-200);margin:0 4px;min-width:16px;"></div>
      <?php endif; ?>
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px;min-width:72px;">
        <div style="width:36px;height:36px;border-radius:50%;background:<?= $bg ?>;
                    border:2px solid <?= $c ?>;display:flex;align-items:center;justify-content:center;">
          <i class="bi <?= $step['icon'] ?>" style="color:<?= $c ?>;font-size:15px;"></i>
        </div>
        <span style="font-size:10px;font-weight:<?= $step['active'] ? '700' : '500' ?>;
                     color:<?= $c ?>;text-align:center;line-height:1.3;">
          <?= $step['label'] ?>
        </span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="row g-4">

  <!-- ── LEFT: Run Panel ──────────────────────────────────────── -->
  <div class="col-lg-4">
    <div class="card" style="position:sticky;top:80px;">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-play-circle me-2" style="color:var(--primary);"></i>Run Eligibility Engine
        </div>

        <form method="POST" id="run-engine-form">
          <input type="hidden" name="run_engine" value="1">

          <!-- Program selection -->
          <div class="mb-3">
            <label class="form-label" style="font-weight:600;">Program Scope</label>
            <select name="program_id" class="form-select" id="run-program-select">
              <option value="">🌐 All Programs</option>
              <?php foreach ($programs as $p): ?>
                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Select a specific program or run for all programs at once.</div>
          </div>

          <!-- Mode -->
          <div class="mb-3">
            <label class="form-label" style="font-weight:600;">Evaluation Mode</label>
            <div style="display:flex;flex-direction:column;gap:6px;">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;
                            background:var(--gray-50);border:1px solid var(--gray-200);
                            border-radius:8px;padding:10px 14px;">
                <input type="radio" name="mode" value="pending" checked>
                <div>
                  <div style="font-weight:600;font-size:13px;">Pending Only</div>
                  <div style="font-size:11.5px;color:var(--gray-400);">
                    Evaluate only applications not yet checked.
                    <span style="color:var(--warning);font-weight:700;"><?= $statPending ?> pending</span>
                  </div>
                </div>
              </label>
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;
                            background:var(--gray-50);border:1px solid var(--gray-200);
                            border-radius:8px;padding:10px 14px;">
                <input type="radio" name="mode" value="all">
                <div>
                  <div style="font-weight:600;font-size:13px;">Re-evaluate All</div>
                  <div style="font-size:11.5px;color:var(--gray-400);">
                    Re-run engine on all applications. Use after rule updates.
                  </div>
                </div>
              </label>
            </div>
          </div>

          <!-- Optional notes -->
          <div class="mb-3">
            <label class="form-label" style="font-weight:600;">Run Notes <span class="text-muted" style="font-weight:400;">(optional)</span></label>
            <input type="text" name="notes" class="form-control form-control-sm"
                   placeholder="e.g. Re-run after GPA rule update">
          </div>

          <button type="submit" class="btn btn-primary w-100" id="btn-run-engine"
                  onclick="return confirm('Run the Eligibility Engine?\n\nThis will evaluate applications and update their PASS/FAIL status.')">
            <i class="bi bi-play-circle-fill me-2"></i>Run Engine
          </button>
        </form>

        <!-- Per-program pending count -->
        <?php if ($statPending > 0): ?>
          <div class="alert alert-warning mt-3 mb-0" style="font-size:12.5px;padding:10px 14px;">
            <i class="bi bi-clock-history me-1"></i>
            <strong><?= $statPending ?></strong> application<?= $statPending > 1 ? 's' : '' ?> pending evaluation.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick single re-run -->
    <div class="card mt-3">
      <div class="card-body">
        <div class="card-title mb-2" style="font-size:13px;font-weight:700;">
          <i class="bi bi-arrow-repeat me-1" style="color:var(--info);"></i>Re-evaluate Single Application
        </div>
        <form method="GET" class="d-flex gap-2">
          <input type="number" name="check_id" class="form-control form-control-sm"
                 placeholder="Application ID" min="1" required>
          <button type="submit" class="btn btn-sm btn-info text-white flex-shrink-0"
                  id="btn-rerun-single">Re-run</button>
        </form>
        <div class="form-text" style="margin-top:4px;">Use after updating rules for one specific student.</div>
      </div>
    </div>
  </div>

  <!-- ── RIGHT: Stats + Results ───────────────────────────────── -->
  <div class="col-lg-8">

    <!-- ── Summary Stat Cards ─────────────────────────────────── -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon blue"><i class="bi bi-file-text"></i></div>
          <div class="stat-body">
            <div class="stat-label">Total Applications</div>
            <div class="stat-value"><?= $statTotal ?></div>
            <div class="stat-trend">All programs</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon green"><i class="bi bi-patch-check-fill"></i></div>
          <div class="stat-body">
            <div class="stat-label">Passed</div>
            <div class="stat-value"><?= $statPassed ?></div>
            <div class="stat-trend">Eligible</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon red"><i class="bi bi-shield-exclamation"></i></div>
          <div class="stat-body">
            <div class="stat-label">Failed</div>
            <div class="stat-value"><?= $statFailed ?></div>
            <div class="stat-trend">Ineligible</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon yellow"><i class="bi bi-percent"></i></div>
          <div class="stat-body">
            <div class="stat-label">Pass Rate</div>
            <div class="stat-value"><?= $passRate ?>%</div>
            <div class="stat-trend"><?= $statPending ?> pending</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Search & Filter ─────────────────────────────────────── -->
    <div class="table-card mb-3" style="padding:16px 20px;">
      <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
        <div style="flex:1;min-width:170px;">
          <label class="form-label" style="margin-bottom:4px;">Search</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control"
                   placeholder="Student name, ID, program…" value="<?= e($search) ?>">
          </div>
        </div>
        <div style="min-width:160px;">
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
        <div style="min-width:140px;">
          <label class="form-label" style="margin-bottom:4px;">Result</label>
          <select name="result" class="form-select form-select-sm">
            <option value="">All Results</option>
            <option value="pass"    <?= $filterResult === 'pass'    ? 'selected' : '' ?>>PASS</option>
            <option value="fail"    <?= $filterResult === 'fail'    ? 'selected' : '' ?>>FAIL</option>
            <option value="pending" <?= $filterResult === 'pending' ? 'selected' : '' ?>>Pending</option>
          </select>
        </div>
        <div style="min-width:140px;">
          <label class="form-label" style="margin-bottom:4px;">Eval Date</label>
          <input type="date" name="eval_date" class="form-control form-control-sm"
                 value="<?= e($filterDate) ?>">
        </div>
        <div class="d-flex gap-1" style="padding-top:22px;">
          <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filter</button>
          <?php if ($search || $filterProgram || $filterResult || $filterDate): ?>
            <a href="index.php" class="btn btn-sm btn-secondary"><i class="bi bi-x-lg"></i></a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- ── Results Table ────────────────────────────────────────── -->
    <div class="table-card mb-4">
      <div class="table-card-header">
        <span class="table-card-title">Evaluation Results</span>
        <span style="font-size:12px;color:var(--gray-400);"><?= count($results) ?> records</span>
      </div>
      <div class="table-responsive">
        <table class="table" id="results-table">
          <thead>
            <tr>
              <th>App #</th>
              <th>Student</th>
              <th>Program</th>
              <th>Result</th>
              <th>Fail Reasons / Details</th>
              <th>Checked At</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($results)): ?>
              <tr>
                <td colspan="7">
                  <div class="empty-state" style="padding:40px 24px;">
                    <span class="empty-state-icon"><i class="bi bi-cpu"></i></span>
                    <div class="empty-state-title">No results found</div>
                    <div class="empty-state-text">
                      <?= ($search || $filterProgram || $filterResult || $filterDate)
                          ? 'Try adjusting your search or filters.'
                          : 'Run the Eligibility Engine to evaluate applications.' ?>
                    </div>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($results as $r):
                $isPassed = $r['is_passed'] ?? null;
                $isChecked = $isPassed !== null;

                // Parse rule trace
                $trace = [];
                if (!empty($r['rule_trace'])) {
                    $decoded = json_decode($r['rule_trace'], true);
                    if (is_array($decoded)) $trace = $decoded;
                }

                // Parse fail reasons from reason field
                $failReasons = [];
                if (!$isPassed && !empty($r['reason']) && str_starts_with($r['reason'], 'Failed criteria:')) {
                    $failText = substr($r['reason'], strlen('Failed criteria: '));
                    $failReasons = array_filter(array_map('trim', explode(';', $failText)));
                }
              ?>
                <tr>
                  <td>
                    <a href="../applications/view.php?id=<?= $r['app_id'] ?>" class="fw-semibold text-primary">
                      #<?= e($r['app_id']) ?>
                    </a>
                  </td>
                  <td>
                    <strong><?= e($r['student_name']) ?></strong>
                    <?php if ($r['student_code']): ?>
                      <div style="font-size:11px;color:var(--gray-400);"><?= e($r['student_code']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td style="font-size:12.5px;"><?= e($r['program_name']) ?></td>
                  <td>
                    <?php if (!$isChecked): ?>
                      <span class="badge badge-warning">
                        <i class="bi bi-clock-history me-1"></i>Pending
                      </span>
                    <?php elseif ($isPassed): ?>
                      <span class="badge badge-eligible">
                        <i class="bi bi-patch-check-fill me-1"></i>PASS
                      </span>
                    <?php else: ?>
                      <span class="badge badge-ineligible">
                        <i class="bi bi-shield-exclamation me-1"></i>FAIL
                      </span>
                    <?php endif; ?>
                  </td>
                  <td style="max-width:300px;">
                    <?php if (!$isChecked): ?>
                      <span class="text-muted" style="font-size:12px;">Not yet evaluated.</span>
                    <?php elseif ($isPassed): ?>
                      <span style="font-size:12px;color:var(--success);">
                        <i class="bi bi-check-circle me-1"></i>All <?= count($trace) ?> rule<?= count($trace) !== 1 ? 's' : '' ?> passed.
                      </span>
                    <?php else: ?>
                      <!-- Fail reasons -->
                      <?php if ($failReasons): ?>
                        <ul style="margin:0;padding-left:14px;font-size:12px;color:var(--danger);">
                          <?php foreach ($failReasons as $fr): ?>
                            <li><?= e($fr) ?></li>
                          <?php endforeach; ?>
                        </ul>
                      <?php else: ?>
                        <span style="font-size:12px;color:var(--danger);"><?= e($r['reason']) ?></span>
                      <?php endif; ?>
                    <?php endif; ?>

                    <!-- Rule trace toggle -->
                    <?php if ($isChecked && count($trace) > 0): ?>
                      <button class="btn btn-outline-secondary btn-sm mt-1"
                              style="font-size:11px;padding:2px 8px;"
                              onclick="toggleTrace(<?= $r['app_id'] ?>)"
                              id="trace-btn-<?= $r['app_id'] ?>">
                        <i class="bi bi-list-nested me-1"></i>Rule Trace (<?= count($trace) ?>)
                      </button>
                      <!-- Rule Trace Detail -->
                      <div id="trace-<?= $r['app_id'] ?>" style="display:none;margin-top:8px;">
                        <table style="width:100%;font-size:11px;border-collapse:collapse;">
                          <thead>
                            <tr style="background:var(--gray-50);color:var(--gray-500);font-weight:700;">
                              <th style="padding:4px 6px;border-bottom:1px solid var(--gray-200);">Rule</th>
                              <th style="padding:4px 6px;border-bottom:1px solid var(--gray-200);">Op</th>
                              <th style="padding:4px 6px;border-bottom:1px solid var(--gray-200);">Expected</th>
                              <th style="padding:4px 6px;border-bottom:1px solid var(--gray-200);">Actual</th>
                              <th style="padding:4px 6px;border-bottom:1px solid var(--gray-200);">Result</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($trace as $tr): ?>
                              <tr style="border-bottom:1px solid var(--gray-100);">
                                <td style="padding:4px 6px;">
                                  <span style="color:var(--gray-500);">#<?= e($tr['rule_id'] ?? '?') ?></span>
                                  <br><strong><?= e($tr['label'] ?? $tr['rule_type'] ?? '') ?></strong>
                                </td>
                                <td style="padding:4px 6px;font-family:monospace;font-weight:700;color:var(--primary);">
                                  <?= e($tr['operator'] ?? '') ?>
                                </td>
                                <td style="padding:4px 6px;font-weight:600;">
                                  <?= e($tr['expected'] ?? '') ?>
                                </td>
                                <td style="padding:4px 6px;font-weight:700;
                                    color:<?= ($tr['passed'] ?? true) ? 'var(--success)' : 'var(--danger)' ?>;">
                                  <?= e($tr['actual'] ?? 'N/A') ?>
                                </td>
                                <td style="padding:4px 6px;">
                                  <?php if ($tr['passed'] === null): ?>
                                    <span style="color:var(--gray-400);">Skip</span>
                                  <?php elseif ($tr['passed']): ?>
                                    <i class="bi bi-check-circle-fill" style="color:var(--success);"></i>
                                  <?php else: ?>
                                    <i class="bi bi-x-circle-fill" style="color:var(--danger);"></i>
                                  <?php endif; ?>
                                </td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td style="font-size:11.5px;color:var(--gray-400);white-space:nowrap;">
                    <?php if ($r['checked_at']): ?>
                      <?= e(date('d M Y', strtotime($r['checked_at']))) ?><br>
                      <span style="font-size:10.5px;"><?= e(date('H:i', strtotime($r['checked_at']))) ?></span>
                      <?php if ($r['checked_by_name']): ?>
                        <br><span style="color:var(--gray-300);">by <?= e($r['checked_by_name']) ?></span>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a href="index.php?check_id=<?= $r['app_id'] ?>"
                       class="btn btn-sm btn-outline-primary btn-action"
                       title="Re-evaluate"
                       id="rerun-app-<?= $r['app_id'] ?>"
                       onclick="return confirm('Re-evaluate Application #<?= $r['app_id'] ?>?')">
                      <i class="bi bi-arrow-repeat"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Evaluation History ──────────────────────────────────── -->
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3"
             style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <div class="card-title mb-0">
            <i class="bi bi-clock-history me-2" style="color:var(--info);"></i>Evaluation History
          </div>
          <span style="font-size:12px;color:var(--gray-400);">Last 20 runs</span>
        </div>
        <?php if (empty($history)): ?>
          <p class="text-muted" style="font-size:13px;margin:0;">No engine runs recorded yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table" style="font-size:13px;margin:0;">
              <thead>
                <tr>
                  <th>Run #</th>
                  <th>Program</th>
                  <th>Checked</th>
                  <th>Passed</th>
                  <th>Failed</th>
                  <th>Pass Rate</th>
                  <th>Executed By</th>
                  <th>Run Date</th>
                  <th>Notes</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($history as $h):
                  $hRate = $h['total_checked'] > 0
                      ? round(($h['total_passed'] / $h['total_checked']) * 100, 1)
                      : 0;
                ?>
                  <tr>
                    <td><span class="text-muted">#<?= e($h['id']) ?></span></td>
                    <td><?= $h['program_name'] ? e($h['program_name']) : '<span class="badge badge-info">All Programs</span>' ?></td>
                    <td><strong><?= e($h['total_checked']) ?></strong></td>
                    <td style="color:var(--success);font-weight:700;"><?= e($h['total_passed']) ?></td>
                    <td style="color:var(--danger);font-weight:700;"><?= e($h['total_failed']) ?></td>
                    <td>
                      <span style="font-size:12px;padding:2px 8px;border-radius:99px;
                                   background:<?= $hRate >= 80 ? 'rgba(22,163,74,.12)' : ($hRate >= 50 ? 'rgba(234,179,8,.12)' : 'rgba(220,38,38,.1)') ?>;
                                   color:<?= $hRate >= 80 ? 'var(--success)' : ($hRate >= 50 ? '#92400e' : 'var(--danger)') ?>;
                                   font-weight:700;">
                        <?= $hRate ?>%
                      </span>
                    </td>
                    <td><?= e($h['executed_by_name']) ?></td>
                    <td class="text-muted" style="white-space:nowrap;font-size:12px;">
                      <?= e(date('d M Y, H:i', strtotime($h['run_at']))) ?>
                    </td>
                    <td style="max-width:160px;font-size:12px;color:var(--gray-400);">
                      <?= $h['notes'] ? e($h['notes']) : '—' ?>
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
</div><!-- /row -->

<!-- ── Engine scope notice ──────────────────────────────────── -->
<div class="alert alert-info mt-4" style="border-left:4px solid var(--info);font-size:13px;">
  <i class="bi bi-cpu me-2"></i>
  <strong>Engine Scope:</strong> This engine only evaluates candidate <strong>eligibility</strong>
  (PASS / FAIL) against program rules. It does <em>not</em> calculate reviewer scores,
  generate rankings, select scholarship winners, or issue certificates.
  Those steps are handled by the Reviewer Evaluation, Ranking Engine, and Award modules.
</div>

<script>
function toggleTrace(appId) {
    const el  = document.getElementById('trace-' + appId);
    const btn = document.getElementById('trace-btn-' + appId);
    if (!el) return;
    const open = el.style.display !== 'none';
    el.style.display  = open ? 'none' : 'block';
    if (btn) btn.innerHTML = open
        ? '<i class="bi bi-list-nested me-1"></i>Rule Trace'
        : '<i class="bi bi-chevron-up me-1"></i>Hide Trace';
}
</script>

<?php require_once '../../includes/footer.php'; ?>
