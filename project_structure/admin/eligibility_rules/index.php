<?php
// ============================================================
// admin/eligibility_rules/index.php
// Eligibility Rules: list with search, filter, status,
// audit trail and reviewer request panel.
// ============================================================
$pageTitle = 'Eligibility Rules';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// ── Auto-migration: add columns if not present ───────────────
try {
    $pdo->exec("ALTER TABLE eligibility_rules
        ADD COLUMN IF NOT EXISTS is_active  TINYINT(1) NOT NULL DEFAULT 1 AFTER value,
        ADD COLUMN IF NOT EXISTS updated_by INT(11) DEFAULT NULL AFTER is_active,
        ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP AFTER updated_by
    ");
} catch (Exception $e) { /* already migrated */ }

// ── Auto-create reviewer requests table ──────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS eligibility_rule_requests (
        id                   INT(11)      AUTO_INCREMENT PRIMARY KEY,
        reviewer_id          INT(11)      NOT NULL,
        rule_id              INT(11)      DEFAULT NULL,
        program_id           INT(11)      NOT NULL,
        current_data         JSON         DEFAULT NULL,
        proposed_rule_type   VARCHAR(100) DEFAULT NULL,
        proposed_operator    VARCHAR(10)  DEFAULT NULL,
        proposed_value       VARCHAR(100) DEFAULT NULL,
        reason               TEXT         DEFAULT NULL,
        status               ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        admin_id             INT(11)      DEFAULT NULL,
        admin_note           TEXT         DEFAULT NULL,
        responded_at         DATETIME     DEFAULT NULL,
        requested_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (rule_id)     REFERENCES eligibility_rules(id) ON DELETE SET NULL,
        FOREIGN KEY (program_id)  REFERENCES scholarship_programs(id) ON DELETE CASCADE,
        FOREIGN KEY (admin_id)    REFERENCES users(id) ON DELETE SET NULL
    )
");

// ── Human-friendly rule type labels ─────────────────────────
function ruleTypeLabel(string $type): string {
    $map = [
        'gpa'                  => 'GPA Requirement',
        'activities'           => 'Activity Requirement',
        'activities_count'     => 'Activity Requirement',
        'activity'             => 'Activity Requirement',
        'income'               => 'Income Requirement',
        'family_income'        => 'Income Requirement',
        'has_language_cert' => 'Language Certificate',
        'language_cert'        => 'Language Certificate',
        'research'             => 'Research Experience',
        'research_count'       => 'Research Experience',
        'research_projects'    => 'Research Experience',
        'failed_subjects'      => 'Max Failed Subjects',
    ];
    return $map[strtolower($type)] ?? ucwords(str_replace('_', ' ', $type));
}

// ── Handle reviewer request approve / reject ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['req_action'])) {
    $reqId     = (int)($_POST['req_id']    ?? 0);
    $reqAct    = $_POST['req_action'];
    $adminNote = trim($_POST['admin_note'] ?? '');
    $adminId   = currentUserId();
    $newStatus = ($reqAct === 'approve') ? 'approved' : 'rejected';

    if ($reqId > 0) {
        if ($reqAct === 'approve') {
            // Fetch request details to apply the change
            $rq = $pdo->prepare("SELECT * FROM eligibility_rule_requests WHERE id = ?");
            $rq->execute([$reqId]);
            $rqRow = $rq->fetch();

            if ($rqRow && $rqRow['proposed_rule_type'] && $rqRow['proposed_operator'] && $rqRow['proposed_value']) {
                if ($rqRow['rule_id']) {
                    // Update existing rule
                    $pdo->prepare("
                        UPDATE eligibility_rules
                        SET rule_type = ?, operator = ?, value = ?, updated_by = ?, updated_at = NOW()
                        WHERE id = ?
                    ")->execute([
                        $rqRow['proposed_rule_type'],
                        $rqRow['proposed_operator'],
                        $rqRow['proposed_value'],
                        $adminId,
                        $rqRow['rule_id']
                    ]);
                } else {
                    // Insert new rule
                    $pdo->prepare("
                        INSERT INTO eligibility_rules (program_id, rule_type, operator, value, is_active, updated_by)
                        VALUES (?, ?, ?, ?, 1, ?)
                    ")->execute([
                        $rqRow['program_id'],
                        $rqRow['proposed_rule_type'],
                        $rqRow['proposed_operator'],
                        $rqRow['proposed_value'],
                        $adminId
                    ]);
                }
            }
        }

        $pdo->prepare("
            UPDATE eligibility_rule_requests
            SET status = ?, admin_id = ?, admin_note = ?, responded_at = NOW()
            WHERE id = ?
        ")->execute([$newStatus, $adminId, $adminNote ?: null, $reqId]);

        setFlash('success', 'Reviewer request #' . $reqId . ' has been ' . $newStatus . '.');
    }
    header('Location: index.php');
    exit;
}

// ── Programs list for filter dropdown ────────────────────────
$programs = $pdo->query("SELECT id, name FROM scholarship_programs ORDER BY name ASC")->fetchAll();

// ── Search & Filter params ───────────────────────────────────
$search        = trim($_GET['search']     ?? '');
$filterProgram = (int)($_GET['program_id'] ?? 0);
$filterType    = trim($_GET['rule_type']   ?? '');
$filterStatus  = trim($_GET['is_active']   ?? '');  // '' | '1' | '0'

// ── Build WHERE ──────────────────────────────────────────────
$where  = [];
$params = [];

if ($search !== '') {
    $like     = "%$search%";
    $where[]  = "(sp.name LIKE ? OR er.rule_type LIKE ?)";
    $params[] = $like; $params[] = $like;
}
if ($filterProgram > 0) {
    $where[]  = "er.program_id = ?";
    $params[] = $filterProgram;
}
if ($filterType !== '') {
    $where[]  = "er.rule_type = ?";
    $params[] = $filterType;
}
if ($filterStatus !== '') {
    $where[]  = "er.is_active = ?";
    $params[] = (int)$filterStatus;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ── Fetch rules ──────────────────────────────────────────────
$sql = "
    SELECT er.*,
           sp.name AS program_name,
           u.full_name AS updated_by_name
    FROM eligibility_rules er
    JOIN scholarship_programs sp ON er.program_id = sp.id
    LEFT JOIN users u ON er.updated_by = u.id
    $whereSql
    ORDER BY sp.name ASC, er.id ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rules = $stmt->fetchAll();

// ── Pending reviewer requests ────────────────────────────────
$pendingReqs = $pdo->query("
    SELECT rr.*, u.full_name AS reviewer_name,
           sp.name AS program_name,
           er.rule_type AS current_rule_type,
           er.operator  AS current_operator,
           er.value     AS current_value
    FROM eligibility_rule_requests rr
    JOIN users u ON rr.reviewer_id = u.id
    JOIN scholarship_programs sp ON rr.program_id = sp.id
    LEFT JOIN eligibility_rules er ON rr.rule_id = er.id
    WHERE rr.status = 'pending'
    ORDER BY rr.requested_at DESC
")->fetchAll();

$pendingCount = count($pendingReqs);

// ── Distinct rule types for filter ───────────────────────────
$ruleTypes = $pdo->query("SELECT DISTINCT rule_type FROM eligibility_rules ORDER BY rule_type ASC")->fetchAll(PDO::FETCH_COLUMN);

// ── Stats ─────────────────────────────────────────────────────
$totalRules   = (int)$pdo->query("SELECT COUNT(*) FROM eligibility_rules")->fetchColumn();
$activeRules  = (int)$pdo->query("SELECT COUNT(*) FROM eligibility_rules WHERE is_active = 1")->fetchColumn();
$inactiveRules = $totalRules - $activeRules;

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="container py-4">
<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Eligibility Rules</h1>
    <p class="page-subtitle">Configure candidate filtering standards. Active rules are enforced by the Eligibility Engine.</p>
  </div>
  <a href="create.php" class="btn btn-primary" id="btn-create-rule">
    <i class="bi bi-plus-lg"></i> Add Rule
  </a>
</div>

<?php showFlash(); ?>

<!-- ── Reviewer Request Banner ───────────────────────────────── -->
<?php if ($pendingCount > 0): ?>
  <div class="alert alert-warning mb-4" style="border-left:4px solid var(--warning);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <div>
      <i class="bi bi-bell-fill me-2"></i>
      <strong><?= $pendingCount ?> pending rule request<?= $pendingCount > 1 ? 's' : '' ?></strong>
      from reviewers awaiting your decision.
    </div>
    <a href="#rule-requests" class="btn btn-sm btn-warning">
      <i class="bi bi-arrow-down"></i> Review Now
    </a>
  </div>
<?php endif; ?>

<!-- ── Stat Cards ─────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-sm-4">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-funnel"></i></div>
      <div class="stat-body">
        <div class="stat-label">Total Rules</div>
        <div class="stat-value"><?= $totalRules ?></div>
        <div class="stat-trend">All programs</div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
      <div class="stat-body">
        <div class="stat-label">Active</div>
        <div class="stat-value"><?= $activeRules ?></div>
        <div class="stat-trend">Enforced by engine</div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card">
      <div class="stat-icon" style="background:#f1f5f9;color:var(--gray-400);"><i class="bi bi-pause-circle"></i></div>
      <div class="stat-body">
        <div class="stat-label">Inactive</div>
        <div class="stat-value"><?= $inactiveRules ?></div>
        <div class="stat-trend">Not evaluated</div>
      </div>
    </div>
  </div>
</div>

<!-- ── Search & Filter Bar ──────────────────────────────────── -->
<div class="table-card mb-3" style="padding:18px 24px;">
  <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">

    <!-- Search -->
    <div style="flex:1;min-width:200px;">
      <label class="form-label" style="margin-bottom:5px;">Search</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="search" class="form-control"
               placeholder="Program name or rule type…" value="<?= e($search) ?>">
      </div>
    </div>

    <!-- Program -->
    <div style="min-width:200px;">
      <label class="form-label" style="margin-bottom:5px;">Program</label>
      <select name="program_id" class="form-select">
        <option value="">All Programs</option>
        <?php foreach ($programs as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $filterProgram == $p['id'] ? 'selected' : '' ?>>
            <?= e($p['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Rule Type -->
    <div style="min-width:190px;">
      <label class="form-label" style="margin-bottom:5px;">Rule Type</label>
      <select name="rule_type" class="form-select">
        <option value="">All Types</option>
        <?php foreach ($ruleTypes as $rt): ?>
          <option value="<?= e($rt) ?>" <?= $filterType === $rt ? 'selected' : '' ?>>
            <?= e(ruleTypeLabel($rt)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Status -->
    <div style="min-width:140px;">
      <label class="form-label" style="margin-bottom:5px;">Status</label>
      <select name="is_active" class="form-select">
        <option value="">All Statuses</option>
        <option value="1" <?= $filterStatus === '1' ? 'selected' : '' ?>>Active</option>
        <option value="0" <?= $filterStatus === '0' ? 'selected' : '' ?>>Inactive</option>
      </select>
    </div>

    <div class="d-flex gap-2" style="padding-top:24px;">
      <button type="submit" class="btn btn-primary" id="filter-btn">
        <i class="bi bi-funnel"></i> Filter
      </button>
      <?php if ($search || $filterProgram || $filterType || $filterStatus !== ''): ?>
        <a href="index.php" class="btn btn-secondary"><i class="bi bi-x-lg"></i> Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- ── Rules Table ────────────────────────────────────────────── -->
<div class="table-card mb-4">
  <div class="table-card-header">
    <span class="table-card-title">
      <?= ($search || $filterProgram || $filterType || $filterStatus !== '')
          ? count($rules).' result'.(count($rules)!==1?'s':'').' found'
          : 'All Eligibility Rules' ?>
    </span>
  </div>
  <div class="table-responsive">
    <table class="table" id="rules-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Program</th>
          <th>Rule Type</th>
          <th>Operator</th>
          <th>Value</th>
          <th>Status</th>
          <th>Updated By</th>
          <th>Updated At</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rules)): ?>
          <tr>
            <td colspan="9">
              <div class="empty-state" style="padding:48px 24px;">
                <span class="empty-state-icon"><i class="bi bi-funnel"></i></span>
                <div class="empty-state-title">No eligibility rules found</div>
                <div class="empty-state-text">
                  <?= ($search || $filterProgram || $filterType || $filterStatus !== '')
                      ? 'Try adjusting your search or filters.'
                      : 'Click "Add Rule" to define the first eligibility rule.' ?>
                </div>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php
          // Group by program for visual separation
          $currentProg = null;
          foreach ($rules as $rule):
            $isActive = (bool)($rule['is_active'] ?? 1);
            $label    = ruleTypeLabel($rule['rule_type']);
            $opFull   = match($rule['operator']) {
                '>='  => '≥ (greater or equal)',
                '<='  => '≤ (less or equal)',
                '>'   => '> (strictly greater)',
                '<'   => '< (strictly less)',
                '='   => '= (exactly equal)',
                default => e($rule['operator'])
            };
          ?>
            <?php if ($currentProg !== $rule['program_id'] && !($filterProgram > 0)): ?>
              <?php $currentProg = $rule['program_id']; ?>
              <tr style="background:var(--gray-50);">
                <td colspan="9" style="padding:8px 16px;font-size:12px;font-weight:700;
                                        text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500);
                                        border-bottom:2px solid var(--gray-200);">
                  <i class="bi bi-award me-1" style="color:var(--primary);"></i>
                  <?= e($rule['program_name']) ?>
                </td>
              </tr>
            <?php endif; ?>
            <tr style="<?= !$isActive ? 'opacity:.55;' : '' ?>">
              <td><span class="text-muted">#<?= e($rule['id']) ?></span></td>
              <td style="<?= $filterProgram > 0 ? '' : 'display:none;' ?>"><?= e($rule['program_name']) ?></td>
              <td>
                <div>
                  <span class="badge badge-info"><?= e($label) ?></span>
                  <div style="font-size:10px;color:var(--gray-400);margin-top:2px;font-family:monospace;">
                    <?= e($rule['rule_type']) ?>
                  </div>
                </div>
              </td>
              <td>
                <code style="font-size:14px;font-weight:800;color:var(--primary);">
                  <?= e($rule['operator']) ?>
                </code>
                <div style="font-size:10px;color:var(--gray-400);"><?= $opFull ?></div>
              </td>
              <td>
                <strong style="font-size:15px;"><?= e($rule['value']) ?></strong>
              </td>
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
                <?= $rule['updated_by_name'] ? e($rule['updated_by_name']) : '<span class="text-muted">—</span>' ?>
              </td>
              <td style="font-size:12px;color:var(--gray-400);white-space:nowrap;">
                <?= isset($rule['updated_at']) && $rule['updated_at']
                    ? e(date('d M Y, H:i', strtotime($rule['updated_at'])))
                    : '<span class="text-muted">—</span>' ?>
              </td>
              <td>
                <div class="d-flex gap-1">
                  <a href="edit.php?id=<?= $rule['id'] ?>"
                     class="btn btn-sm btn-warning btn-action"
                     id="edit-rule-<?= $rule['id'] ?>">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <!-- Toggle active/inactive -->
                  <a href="toggle.php?id=<?= $rule['id'] ?>"
                     class="btn btn-sm <?= $isActive ? 'btn-outline-secondary' : 'btn-success' ?> btn-action"
                     title="<?= $isActive ? 'Deactivate' : 'Activate' ?>"
                     id="toggle-rule-<?= $rule['id'] ?>">
                    <i class="bi bi-<?= $isActive ? 'pause' : 'play' ?>-fill"></i>
                  </a>
                  <a href="delete.php?id=<?= $rule['id'] ?>"
                     class="btn btn-sm btn-danger btn-action"
                     onclick="return confirm('Delete rule #<?= $rule['id'] ?>?\n\nThis will affect future eligibility evaluations.')"
                     id="delete-rule-<?= $rule['id'] ?>">
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

<!-- ── Reviewer Rule Requests ─────────────────────────────────── -->
<div class="table-card" id="rule-requests">
  <div class="table-card-header">
    <span class="table-card-title">
      <i class="bi bi-inbox me-2" style="color:var(--warning);"></i>Reviewer Rule Requests
    </span>
    <span class="badge badge-warning" style="font-size:12px;"><?= $pendingCount ?> pending</span>
  </div>

  <?php if (empty($pendingReqs)): ?>
    <div class="empty-state" style="padding:36px 24px;">
      <span class="empty-state-icon" style="font-size:36px;"><i class="bi bi-inbox"></i></span>
      <div class="empty-state-title" style="font-size:15px;">No pending rule requests</div>
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
            <th>Current Rule</th>
            <th>Proposed Change</th>
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

              <!-- Current rule -->
              <td style="font-size:12.5px;">
                <?php if ($req['rule_id'] && $req['current_rule_type']): ?>
                  <div>
                    <span class="badge badge-info" style="margin-bottom:3px;">
                      <?= e(ruleTypeLabel($req['current_rule_type'])) ?>
                    </span>
                    <div style="font-family:monospace;color:var(--gray-500);">
                      <?= e($req['current_operator']) ?> <?= e($req['current_value']) ?>
                    </div>
                  </div>
                <?php else: ?>
                  <span class="badge badge-warning">New Rule</span>
                <?php endif; ?>
              </td>

              <!-- Proposed change -->
              <td style="font-size:12.5px;">
                <?php if ($req['proposed_rule_type']): ?>
                  <div>
                    <span class="badge" style="background:#dbeafe;color:#1d4ed8;margin-bottom:3px;">
                      <?= e(ruleTypeLabel($req['proposed_rule_type'])) ?>
                    </span>
                    <div style="font-family:monospace;color:var(--primary);font-weight:700;">
                      <?= e($req['proposed_operator']) ?> <?= e($req['proposed_value']) ?>
                    </div>
                  </div>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>

              <td style="max-width:200px;font-size:12.5px;color:var(--gray-600);">
                <?= e($req['reason'] ?: '—') ?>
              </td>
              <td class="text-muted" style="white-space:nowrap;font-size:12px;">
                <?= e(date('d M Y, H:i', strtotime($req['requested_at']))) ?>
              </td>

              <!-- Actions -->
              <td>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="req_id"     value="<?= $req['id'] ?>">
                  <input type="hidden" name="req_action" value="approve">
                  <button type="submit" class="btn btn-sm btn-success btn-action"
                          id="approve-req-<?= $req['id'] ?>"
                          onclick="return confirm('Approve and apply this rule change?')">
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
  <strong>Eligibility Engine:</strong> Only <strong>Active</strong> rules are evaluated when checking candidate eligibility.
  Deactivating a rule pauses it without deleting it — future eligibility checks will skip it.
  Approved reviewer changes take effect immediately on the <em>next</em> eligibility check run.
</div>

<script>
function toggleRejectForm(id) {
    const f = document.getElementById('reject-form-' + id);
    if (f) f.style.display = f.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php require_once '../../includes/footer.php'; ?>