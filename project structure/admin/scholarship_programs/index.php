<?php
// ============================================================
// admin/scholarship_programs/index.php
// Program List + Dashboard Summary + Search & Filter
// Admin has full CRUD access. Reviewer requests panel included.
// ============================================================
$pageTitle = 'Scholarship Programs';

require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/notifications.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// ── Ensure program_requests table exists (auto-migration) ────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS program_requests (
        id           INT(11)      AUTO_INCREMENT PRIMARY KEY,
        reviewer_id  INT(11)      NOT NULL,
        request_type ENUM('add','update','suspend','delete') NOT NULL DEFAULT 'add',
        program_id   INT(11)      DEFAULT NULL,
        proposed_data JSON        DEFAULT NULL,
        reason       TEXT         DEFAULT NULL,
        status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        admin_id     INT(11)      DEFAULT NULL,
        admin_note   TEXT         DEFAULT NULL,
        responded_at DATETIME     DEFAULT NULL,
        requested_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (program_id)  REFERENCES scholarship_programs(id) ON DELETE SET NULL,
        FOREIGN KEY (admin_id)    REFERENCES users(id) ON DELETE SET NULL
    )
");

// ── Handle reviewer request approval / rejection ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['req_action'])) {
    $reqId    = (int)($_POST['req_id'] ?? 0);
    $reqAct   = $_POST['req_action'];        // 'approve' | 'reject'
    $adminNote = trim($_POST['admin_note'] ?? '');
    $adminId   = currentUserId();
    $newStatus = ($reqAct === 'approve') ? 'approved' : 'rejected';

    if ($reqId > 0) {
        $reqStmt = $pdo->prepare("SELECT * FROM program_requests WHERE id = ?");
        $reqStmt->execute([$reqId]);
        $reqRow = $reqStmt->fetch();

        $pdo->prepare("
            UPDATE program_requests
            SET status = ?, admin_id = ?, admin_note = ?, responded_at = NOW()
            WHERE id = ?
        ")->execute([$newStatus, $adminId, $adminNote ?: null, $reqId]);

        if ($reqRow && !empty($reqRow['reviewer_id'])) {
            if ($reqAct === 'approve') {
                $notifyTitle = 'Program Request Approved';
                $notifyMessage = "Your program request #{$reqId} has been approved.";
                $notifyType = 'success';
            } else {
                $notifyTitle = 'Program Request Rejected';
                $notifyMessage = "Your program request #{$reqId} has been rejected.";
                $notifyType = 'warning';
            }
            if ($adminNote !== '') {
                $notifyMessage .= ' Admin note: ' . $adminNote;
            }
            sendNotification($pdo, (int)$reqRow['reviewer_id'], $notifyTitle, $notifyMessage, $notifyType);
        }

        // If approved + type=suspend, set program status to 'suspended'
        if ($reqAct === 'approve') {
            $req = $pdo->prepare("SELECT * FROM program_requests WHERE id = ?");
            $req->execute([$reqId]);
            $reqRow = $req->fetch();
            if ($reqRow && $reqRow['request_type'] === 'suspend' && $reqRow['program_id']) {
                $pdo->prepare("UPDATE scholarship_programs SET status = 'suspended' WHERE id = ?")
                    ->execute([$reqRow['program_id']]);
            }
        }
        setFlash('success', 'Request #' . $reqId . ' has been ' . $newStatus . '.');
    }
    header('Location: index.php');
    exit;
}

// ── Dashboard stats ──────────────────────────────────────────
$statTotal     = (int) $pdo->query("SELECT COUNT(*) FROM scholarship_programs")->fetchColumn();
$statActive    = (int) $pdo->query("SELECT COUNT(*) FROM scholarship_programs WHERE status = 'open'")->fetchColumn();
$statClosed    = (int) $pdo->query("SELECT COUNT(*) FROM scholarship_programs WHERE status = 'closed'")->fetchColumn();
$statSuspended = (int) $pdo->query("SELECT COUNT(*) FROM scholarship_programs WHERE status = 'suspended'")->fetchColumn();
$statPending   = (int) $pdo->query("SELECT COUNT(*) FROM program_requests WHERE status = 'pending'")->fetchColumn();

// ── Search & Filter ──────────────────────────────────────────
$search       = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterType   = trim($_GET['type']   ?? '');   // scholarship type from description keyword

$validStatuses = ['open','closed','suspended','draft'];

$where  = [];
$params = [];

if ($search !== '') {
    $like     = "%$search%";
    $where[]  = "sp.name LIKE ?";
    $params[] = $like;
}
if ($filterStatus !== '' && in_array($filterStatus, $validStatuses)) {
    $where[]  = "sp.status = ?";
    $params[] = $filterStatus;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ── Fetch programs with stats ────────────────────────────────
$sql = "
    SELECT
        sp.*,
        COUNT(DISTINCT a.id)                AS total_applications,
        COUNT(DISTINCT CASE WHEN er.is_passed = 1 THEN er.application_id END) AS verified_applicants,
        COUNT(DISTINCT CASE WHEN rr.recommended = 1 THEN rr.id END)           AS awarded_students,
        COUNT(DISTINCT sc.id)               AS criteria_count,
        COUNT(DISTINCT elig.id)             AS rules_count
    FROM scholarship_programs sp
    LEFT JOIN applications a    ON a.program_id = sp.id
    LEFT JOIN (
        SELECT application_id, is_passed
        FROM eligibility_results
        WHERE id IN (SELECT MAX(id) FROM eligibility_results GROUP BY application_id)
    ) er ON er.application_id = a.id
    LEFT JOIN ranking_results rr ON rr.application_id = a.id
    LEFT JOIN scoring_criteria sc ON sc.program_id = sp.id
    LEFT JOIN eligibility_rules elig ON elig.program_id = sp.id
    $whereSql
    GROUP BY sp.id
    ORDER BY sp.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$programs = $stmt->fetchAll();

// ── Pending reviewer requests ────────────────────────────────
$requests = $pdo->query("
    SELECT pr.*, u.full_name AS reviewer_name, sp.name AS program_name
    FROM program_requests pr
    JOIN users u ON pr.reviewer_id = u.id
    LEFT JOIN scholarship_programs sp ON pr.program_id = sp.id
    WHERE pr.status = 'pending'
    ORDER BY pr.requested_at DESC
")->fetchAll();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Scholarship Programs</h1>
    <p class="page-subtitle">Manage program specifications, eligibility rules, budget, and reviewer proposals.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="create.php" class="btn btn-primary" id="btn-create-program">
      <i class="bi bi-plus-lg"></i> New Program
    </a>
  </div>
</div>

<?php showFlash(); ?>

<!-- ── Reviewer Request Banner ───────────────────────────────── -->
<?php if ($statPending > 0): ?>
  <div class="alert alert-warning mb-4" style="border-left:4px solid var(--warning);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <div>
      <i class="bi bi-bell-fill me-2"></i>
      <strong><?= $statPending ?> pending reviewer request<?= $statPending > 1 ? 's' : '' ?></strong>
      awaiting your approval.
    </div>
    <a href="#reviewer-requests" class="btn btn-sm btn-warning">
      <i class="bi bi-arrow-down"></i> Review Now
    </a>
  </div>
<?php endif; ?>

<!-- ── Dashboard Summary ──────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-award"></i></div>
      <div class="stat-body">
        <div class="stat-label">Total Programs</div>
        <div class="stat-value"><?= $statTotal ?></div>
        <div class="stat-trend">All statuses</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-unlock"></i></div>
      <div class="stat-body">
        <div class="stat-label">Active (Open)</div>
        <div class="stat-value"><?= $statActive ?></div>
        <div class="stat-trend">Accepting applications</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon" style="background:#f1f5f9;color:var(--gray-500);"><i class="bi bi-lock"></i></div>
      <div class="stat-body">
        <div class="stat-label">Closed</div>
        <div class="stat-value"><?= $statClosed ?></div>
        <div class="stat-trend">Applications ended</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon red"><i class="bi bi-pause-circle"></i></div>
      <div class="stat-body">
        <div class="stat-label">Suspended</div>
        <div class="stat-value"><?= $statSuspended ?></div>
        <div class="stat-trend">Temporarily paused</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card">
      <div class="stat-icon yellow"><i class="bi bi-inbox"></i></div>
      <div class="stat-body">
        <div class="stat-label">Pending Requests</div>
        <div class="stat-value"><?= $statPending ?></div>
        <div class="stat-trend">Reviewer proposals</div>
      </div>
    </div>
  </div>
</div>

<!-- ── Search & Filter ────────────────────────────────────────── -->
<div class="table-card mb-3" style="padding:18px 24px;">
  <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
    <div style="flex:1;min-width:200px;">
      <label class="form-label" style="margin-bottom:5px;">Search Program</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="search" class="form-control" placeholder="Program name…" value="<?= e($search) ?>">
      </div>
    </div>
    <div style="min-width:160px;">
      <label class="form-label" style="margin-bottom:5px;">Status</label>
      <select name="status" class="form-select">
        <option value="">All Statuses</option>
        <option value="open"      <?= $filterStatus === 'open'      ? 'selected' : '' ?>>Active (Open)</option>
        <option value="closed"    <?= $filterStatus === 'closed'    ? 'selected' : '' ?>>Closed</option>
        <option value="suspended" <?= $filterStatus === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        <option value="draft"     <?= $filterStatus === 'draft'     ? 'selected' : '' ?>>Draft</option>
      </select>
    </div>
    <div class="d-flex gap-2" style="padding-top:24px;">
      <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
      <?php if ($search || $filterStatus): ?>
        <a href="index.php" class="btn btn-secondary"><i class="bi bi-x-lg"></i> Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- ── Programs Table ─────────────────────────────────────────── -->
<div class="table-card mb-4">
  <div class="table-card-header">
    <span class="table-card-title">
      <?= ($search || $filterStatus) ? count($programs).' result'.(count($programs)!==1?'s':'').' found' : 'All Programs' ?>
    </span>
  </div>
  <div class="table-responsive">
    <table class="table" id="programs-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Program Name</th>
          <th>Budget</th>
          <th>Quota</th>
          <th>Application Period</th>
          <th>Status</th>
          <th>Applications</th>
          <th>Rules / Criteria</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($programs)): ?>
          <tr>
            <td colspan="9">
              <div class="empty-state" style="padding:48px 24px;">
                <span class="empty-state-icon"><i class="bi bi-award"></i></span>
                <div class="empty-state-title">No programs found</div>
                <div class="empty-state-text">
                  <?= ($search || $filterStatus) ? 'Try adjusting your search or filter.' : 'Click "New Program" to create the first scholarship program.' ?>
                </div>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($programs as $prog):
            $statusMap = [
              'open'      => ['badge-eligible',  'Active'],
              'closed'    => ['badge-inactive',  'Closed'],
              'suspended' => ['badge-ineligible','Suspended'],
              'draft'     => ['badge-warning',   'Draft'],
            ];
            [$statusBadge, $statusLabel] = $statusMap[$prog['status']] ?? ['badge-inactive', ucfirst($prog['status'])];
          ?>
            <tr id="prog-row-<?= $prog['id'] ?>">
              <td><span class="text-muted">#<?= e($prog['id']) ?></span></td>
              <td>
                <div>
                  <strong><?= e($prog['name']) ?></strong>
                  <?php if ($prog['description']): ?>
                    <div style="font-size:12px;color:var(--gray-400);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:240px;">
                      <?= e($prog['description']) ?>
                    </div>
                  <?php endif; ?>
                </div>
              </td>
              <td style="white-space:nowrap;">
                <strong style="color:var(--success);"><?= number_format((float)$prog['budget'],0,',','.') ?>đ</strong>
              </td>
              <td>
                <span class="badge badge-info"><?= e($prog['slots']) ?> slots</span>
              </td>
              <td class="text-muted" style="font-size:12.5px;white-space:nowrap;">
                <?= $prog['start_date'] ? e(date('d M Y',strtotime($prog['start_date']))) : '—' ?>
                <?= ($prog['start_date'] && $prog['end_date']) ? '<br>→ ' : '' ?>
                <?= $prog['end_date']   ? e(date('d M Y',strtotime($prog['end_date']))) : '' ?>
              </td>
              <td><span class="badge <?= $statusBadge ?>"><?= $statusLabel ?></span></td>
              <td>
                <div style="font-weight:600;font-size:14px;"><?= (int)$prog['total_applications'] ?></div>
                <?php if ($prog['awarded_students'] > 0): ?>
                  <div style="font-size:11px;color:var(--success);">
                    <i class="bi bi-trophy-fill"></i> <?= (int)$prog['awarded_students'] ?> awarded
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-size:12px;color:var(--gray-500);">
                  <i class="bi bi-funnel me-1"></i><?= (int)$prog['rules_count'] ?> rules<br>
                  <i class="bi bi-star-half me-1"></i><?= (int)$prog['criteria_count'] ?> criteria
                </div>
              </td>
              <td>
                <div class="d-flex gap-1 flex-wrap">
                  <a href="view.php?id=<?= $prog['id'] ?>"
                     class="btn btn-sm btn-outline-primary btn-action"
                     id="view-prog-<?= $prog['id'] ?>">
                    <i class="bi bi-eye"></i>
                  </a>
                  <a href="edit.php?id=<?= $prog['id'] ?>"
                     class="btn btn-sm btn-warning btn-action"
                     id="edit-prog-<?= $prog['id'] ?>">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <?php if ($prog['status'] !== 'suspended'): ?>
                    <a href="suspend.php?id=<?= $prog['id'] ?>"
                       class="btn btn-sm btn-secondary btn-action"
                       title="Suspend"
                       onclick="return confirm('Suspend this program?')"
                       id="suspend-prog-<?= $prog['id'] ?>">
                      <i class="bi bi-pause-circle"></i>
                    </a>
                  <?php else: ?>
                    <a href="suspend.php?id=<?= $prog['id'] ?>&reopen=1"
                       class="btn btn-sm btn-success btn-action"
                       title="Re-open"
                       onclick="return confirm('Re-open this program?')"
                       id="reopen-prog-<?= $prog['id'] ?>">
                      <i class="bi bi-play-circle"></i>
                    </a>
                  <?php endif; ?>
                  <a href="delete.php?id=<?= $prog['id'] ?>"
                     class="btn btn-sm btn-danger btn-action"
                     onclick="return confirm('Delete program \'<?= addslashes(e($prog['name'])) ?>\'?\n\nThis will also delete all linked rules and criteria.')"
                     id="delete-prog-<?= $prog['id'] ?>">
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

<!-- ── Reviewer Requests Panel ────────────────────────────────── -->
<div class="table-card" id="reviewer-requests">
  <div class="table-card-header">
    <span class="table-card-title">
      <i class="bi bi-inbox me-2" style="color:var(--warning);"></i>Reviewer Requests
    </span>
    <span class="badge badge-warning" style="font-size:12px;"><?= $statPending ?> pending</span>
  </div>

  <?php if (empty($requests)): ?>
    <div class="empty-state" style="padding:36px 24px;">
      <span class="empty-state-icon" style="font-size:36px;"><i class="bi bi-inbox"></i></span>
      <div class="empty-state-title" style="font-size:15px;">No pending requests</div>
      <div class="empty-state-text">All reviewer proposals have been handled.</div>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Req #</th>
            <th>Type</th>
            <th>Program</th>
            <th>Reviewer</th>
            <th>Reason</th>
            <th>Requested</th>
            <th>Approve / Reject</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($requests as $req):
            $typeMap = [
              'add'     => ['badge-info',    'bi-plus-lg',     'Add Program'],
              'update'  => ['badge-warning', 'bi-pencil',      'Update'],
              'suspend' => ['badge-inactive','bi-pause-circle','Suspend'],
              'delete'  => ['badge-danger',  'bi-trash',       'Delete'],
            ];
            [$typeBadge, $typeIcon, $typeLabel] = $typeMap[$req['request_type']] ?? ['badge-inactive','bi-question','Unknown'];
          ?>
            <tr>
              <td><span class="text-muted">#<?= e($req['id']) ?></span></td>
              <td>
                <span class="badge <?= $typeBadge ?>">
                  <i class="bi <?= $typeIcon ?> me-1"></i><?= $typeLabel ?>
                </span>
              </td>
              <td>
                <?php if ($req['program_name']): ?>
                  <strong><?= e($req['program_name']) ?></strong>
                <?php else: ?>
                  <span class="text-muted">New Program</span>
                  <?php if ($req['proposed_data']): ?>
                    <?php $pd = json_decode($req['proposed_data'], true); ?>
                    <?php if (!empty($pd['name'])): ?>
                      <div style="font-size:11px;color:var(--gray-400);"><?= e($pd['name']) ?></div>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td><?= e($req['reviewer_name']) ?></td>
              <td style="max-width:220px;font-size:12.5px;color:var(--gray-600);">
                <?= e($req['reason'] ?: '—') ?>
              </td>
              <td class="text-muted" style="white-space:nowrap;font-size:12px;">
                <?= e(date('d M Y, H:i', strtotime($req['requested_at']))) ?>
              </td>
              <td>
                <!-- Approve -->
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="req_id"     value="<?= $req['id'] ?>">
                  <input type="hidden" name="req_action" value="approve">
                  <button type="submit" class="btn btn-sm btn-success btn-action"
                          id="approve-req-<?= $req['id'] ?>"
                          onclick="return confirm('Approve this request?')">
                    <i class="bi bi-check-lg"></i> Approve
                  </button>
                </form>
                <!-- Reject -->
                <button type="button" class="btn btn-sm btn-danger btn-action"
                        id="reject-req-<?= $req['id'] ?>"
                        onclick="showRejectForm(<?= $req['id'] ?>)">
                  <i class="bi bi-x-lg"></i> Reject
                </button>
                <!-- Inline reject form (hidden until button clicked) -->
                <div id="reject-form-<?= $req['id'] ?>" style="display:none;margin-top:8px;">
                  <form method="POST">
                    <input type="hidden" name="req_id"     value="<?= $req['id'] ?>">
                    <input type="hidden" name="req_action" value="reject">
                    <div class="d-flex gap-2">
                      <input type="text" name="admin_note" class="form-control form-control-sm"
                             placeholder="Reason for rejection (optional)">
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

<script>
function showRejectForm(id) {
    const form = document.getElementById('reject-form-' + id);
    if (form) form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php require_once '../../includes/footer.php'; ?>
