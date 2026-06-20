<?php
// ============================================================
// student/my_applications.php
// Features: Draft tab with "Continue Editing" button
// ============================================================
$pageTitle = 'My Applications';

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requireRole('student');

$pdo       = getDB();
$studentId = currentUserId();

// ── Active tab: 'draft' or status value ───────────────────
$activeTab    = $_GET['tab'] ?? '';
$filterStatus = $activeTab === 'draft' ? 'draft' : ($activeTab ?: '');

// ── Fetch applications (non-draft) ────────────────────────
$where  = "WHERE a.student_id = ? AND a.status != 'draft'";
$params = [$studentId];
if ($filterStatus !== '' && $filterStatus !== 'draft') {
    $where  .= " AND a.status = ?";
    $params[] = $filterStatus;
}

$sql = "
    SELECT a.*, sp.name AS program_name,
           (SELECT rr.rank FROM ranking_results rr
            WHERE rr.application_id = a.id LIMIT 1) AS my_rank,
           (SELECT rr.total_score FROM ranking_results rr
            WHERE rr.application_id = a.id LIMIT 1) AS total_score
    FROM   applications a
    JOIN   scholarship_programs sp ON a.program_id = sp.id
    $where
    ORDER  BY a.submitted_at DESC, a.updated_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// ── Fetch drafts separately ────────────────────────────────
$stmtDrafts = $pdo->prepare("
    SELECT a.*, sp.name AS program_name
    FROM   applications a
    JOIN   scholarship_programs sp ON a.program_id = sp.id
    WHERE  a.student_id = ? AND a.status = 'draft'
    ORDER  BY a.updated_at DESC
");
$stmtDrafts->execute([$studentId]);
$drafts = $stmtDrafts->fetchAll();

// ── Handle draft delete ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_draft_id'])) {
    $delId = (int)$_POST['delete_draft_id'];
    $pdo->prepare(
        "DELETE FROM applications WHERE id=? AND student_id=? AND status='draft'"
    )->execute([$delId, $studentId]);
    setFlash('success', 'Đã xóa đơn nháp.');
    header('Location: my_applications.php?tab=draft');
    exit;
}

// ── Counts per status (exclude drafts from numbered tabs) ─
$counts = [];
$stmtCounts = $pdo->prepare("
    SELECT status, COUNT(*) AS cnt
    FROM applications WHERE student_id = ?
    GROUP BY status
");
$stmtCounts->execute([$studentId]);
foreach ($stmtCounts->fetchAll() as $row) {
    $counts[$row['status']] = (int)$row['cnt'];
}
$draftCount = $counts['draft'] ?? 0;

require_once __DIR__ . '/../includes/header.php';
//require_once __DIR__ . '/../includes/navbar.php';
?>
<?php require_once __DIR__ . '/../includes/student_header.php'; ?>
<div class="student-page">

<style>
/* ══════════════════════════════════════════════════
   MY APPLICATIONS — Page Styles
══════════════════════════════════════════════════ */

/* ── Page header ───────────────────────────────── */
.ma-page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 28px 0 20px;
  flex-wrap: wrap;
}
.ma-page-title {
  font-size: 24px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 4px;
  letter-spacing: -.01em;
}
.ma-page-subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}
.ma-new-btn {
  background: #1d4ed8;
  color: #fff;
  border: none;
  border-radius: 10px;
  padding: 10px 20px;
  font-size: 13.5px;
  font-weight: 700;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  white-space: nowrap;
  transition: all .18s;
  box-shadow: 0 2px 10px rgba(29,78,216,.25);
  flex-shrink: 0;
}
.ma-new-btn:hover {
  background: #1638a8;
  color: #fff;
  transform: translateY(-1px);
  box-shadow: 0 4px 16px rgba(29,78,216,.32);
}

/* ── Filter tabs ───────────────────────────────── */
.ma-tabs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 24px;
}
.ma-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 16px;
  border-radius: 99px;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  border: 1.5px solid #e2e8f0;
  background: #fff;
  color: #475569;
  transition: all .16s;
  white-space: nowrap;
}
.ma-tab:hover {
  border-color: #1d4ed8;
  color: #1d4ed8;
  background: #eff6ff;
}
.ma-tab.active {
  background: #1d4ed8;
  color: #fff;
  border-color: #1d4ed8;
  box-shadow: 0 2px 8px rgba(29,78,216,.22);
}
.ma-tab-count {
  background: #f1f5f9;
  color: #64748b;
  border-radius: 99px;
  padding: 1px 8px;
  font-size: 11px;
  font-weight: 700;
  line-height: 1.6;
}
.ma-tab.active .ma-tab-count {
  background: rgba(255,255,255,.25);
  color: #fff;
}
/* draft tab special */
.ma-tab-draft {
  border-color: #fde68a;
  color: #92400e;
  background: #fffbeb;
}
.ma-tab-draft:hover {
  border-color: #f59e0b;
  background: #fef3c7;
  color: #78350f;
}
.ma-tab-draft.active {
  background: #f59e0b;
  border-color: #f59e0b;
  color: #fff;
}

/* ── Table card ────────────────────────────────── */
.ma-card {
  background: #fff;
  border-radius: 16px;
  border: 1px solid #e8edf5;
  box-shadow: 0 1px 4px rgba(15,23,42,.04), 0 4px 20px rgba(15,23,42,.06);
  overflow: hidden;
  margin-bottom: 20px;
}
.ma-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px;
  border-bottom: 1px solid #f1f5f9;
  gap: 12px;
  flex-wrap: wrap;
}
.ma-card-title {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  display: flex;
  align-items: center;
  gap: 8px;
}
.ma-card-count {
  font-size: 12px;
  font-weight: 400;
  color: #94a3b8;
  margin-left: 4px;
}
.ma-card-hint {
  font-size: 12.5px;
  color: #94a3b8;
  display: flex;
  align-items: center;
  gap: 5px;
}

/* ── Table ─────────────────────────────────────── */
.ma-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
}
.ma-table thead tr {
  background: #f8fafc;
  border-bottom: 1px solid #e8edf5;
}
.ma-table th {
  padding: 12px 20px;
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .06em;
  white-space: nowrap;
}
.ma-table td {
  padding: 16px 20px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}
.ma-table tbody tr:last-child td {
  border-bottom: none;
}
.ma-table tbody tr {
  transition: background .12s;
}
.ma-table tbody tr:hover {
  background: #f8faff;
}

/* ── Program cell ──────────────────────────────── */
.ma-prog-name {
  font-weight: 700;
  color: #0f172a;
  font-size: 14px;
  line-height: 1.3;
  margin-bottom: 3px;
}
.ma-prog-id {
  font-size: 11.5px;
  color: #94a3b8;
  font-weight: 500;
}

/* ── Status badges ─────────────────────────────── */
.ma-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border-radius: 6px;
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: .01em;
  line-height: 1.4;
}
.ma-badge-submitted  { background: #dbeafe; color: #1d4ed8; }
.ma-badge-reviewing  { background: #dcfce7; color: #15803d; }
.ma-badge-eligible   { background: #dcfce7; color: #15803d; }
.ma-badge-ineligible { background: #fee2e2; color: #dc2626; }
.ma-badge-ranked     { background: #ede9fe; color: #7c3aed; }
.ma-badge-approved   { background: #ede9fe; color: #7c3aed; }
.ma-badge-rejected   { background: #fee2e2; color: #dc2626; }
.ma-badge-disbursed  { background: #ffedd5; color: #c2410c; }
.ma-badge-draft      { background: #fef3c7; color: #92400e; }

/* eligibility badges */
.ma-elig-pending    { background: #fef3c7; color: #92400e; }
.ma-elig-eligible   { background: #dcfce7; color: #15803d; }
.ma-elig-ineligible { background: #fee2e2; color: #dc2626; }

.ma-badge-helper {
  display: block;
  font-size: 11px;
  color: #94a3b8;
  font-weight: 400;
  margin-top: 4px;
}

/* ── Rank cell ─────────────────────────────────── */
.ma-rank-val {
  font-size: 15px;
  font-weight: 800;
  color: #1d4ed8;
}
.ma-rank-score {
  font-size: 11px;
  color: #94a3b8;
  display: block;
  margin-top: 2px;
}

/* ── View button ───────────────────────────────── */
.ma-view-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 14px;
  border-radius: 8px;
  border: 1.5px solid #1d4ed8;
  color: #1d4ed8;
  font-size: 12.5px;
  font-weight: 600;
  text-decoration: none;
  background: #fff;
  transition: all .15s;
  white-space: nowrap;
}
.ma-view-btn:hover {
  background: #1d4ed8;
  color: #fff;
  box-shadow: 0 2px 8px rgba(29,78,216,.22);
}

/* ── Draft action buttons ──────────────────────── */
.ma-edit-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 14px;
  border-radius: 8px;
  background: #1d4ed8;
  color: #fff;
  font-size: 12.5px;
  font-weight: 600;
  text-decoration: none;
  transition: all .15s;
  white-space: nowrap;
  border: none;
}
.ma-edit-btn:hover {
  background: #1638a8;
  color: #fff;
}
.ma-del-btn {
  display: inline-flex;
  align-items: center;
  padding: 6px 10px;
  border-radius: 8px;
  background: #fff;
  border: 1.5px solid #fecaca;
  color: #dc2626;
  font-size: 13px;
  cursor: pointer;
  transition: all .15s;
}
.ma-del-btn:hover {
  background: #fee2e2;
  border-color: #dc2626;
}

/* ── Empty state ───────────────────────────────── */
.ma-empty {
  text-align: center;
  padding: 56px 24px;
}
.ma-empty-icon {
  font-size: 48px;
  color: #cbd5e1;
  margin-bottom: 14px;
}
.ma-empty-title {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 8px;
}
.ma-empty-text {
  font-size: 13.5px;
  color: #94a3b8;
  margin-bottom: 20px;
  max-width: 340px;
  margin-left: auto;
  margin-right: auto;
}

/* ── Info card ─────────────────────────────────── */
.ma-info-card {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 12px;
  padding: 16px 20px;
  margin-bottom: 24px;
  font-size: 13.5px;
  color: #1e40af;
}
.ma-info-icon {
  font-size: 20px;
  flex-shrink: 0;
  margin-top: 1px;
}

/* ── Responsive ────────────────────────────────── */
@media (max-width: 767px) {
  .ma-page-header { padding: 20px 0 16px; }
  .ma-page-title  { font-size: 20px; }
  .ma-table th, .ma-table td { padding: 12px 14px; }
  .ma-card-header { padding: 14px 16px; }
}
@media (max-width: 575px) {
  .ma-table th:nth-child(4),
  .ma-table td:nth-child(4) { display: none; } /* hide Rank on xs */
}
.student-page{
    max-width:1280px;
    margin:0 auto;
    padding:32px;
}
</style>

<?php
// ── Badge config used in both tabs ──────────────────────────
$statusConfig = [
  'submitted'  => ['label' => 'Submitted',   'helper' => 'Submitted',   'cls' => 'ma-badge-submitted',  'icon' => 'bi-send'],
  'reviewing'  => ['label' => 'Under Review', 'helper' => 'Under Review', 'cls' => 'ma-badge-reviewing',  'icon' => 'bi-search'],
  'eligible'   => ['label' => 'Eligible',     'helper' => 'Qualified',    'cls' => 'ma-badge-eligible',   'icon' => 'bi-check-circle'],
  'ineligible' => ['label' => 'Ineligible',   'helper' => 'Not Qualified','cls' => 'ma-badge-ineligible', 'icon' => 'bi-x-circle'],
  'ranked'     => ['label' => 'Ranked',       'helper' => 'Ranked',       'cls' => 'ma-badge-ranked',     'icon' => 'bi-bar-chart'],
  'approved'   => ['label' => 'Approved',     'helper' => 'Approved',     'cls' => 'ma-badge-approved',   'icon' => 'bi-patch-check'],
  'rejected'   => ['label' => 'Rejected',     'helper' => 'Rejected',     'cls' => 'ma-badge-rejected',   'icon' => 'bi-x-octagon'],
  'disbursed'  => ['label' => 'Disbursed',    'helper' => 'Disbursed',    'cls' => 'ma-badge-disbursed',  'icon' => 'bi-cash-coin'],
];
?>

<!-- ══ PAGE HEADER ══════════════════════════════════════════ -->
<div class="ma-page-header">
  <div>
    <h1 class="ma-page-title">
      <i class="bi bi-folder-check me-2 text-primary"></i>My Applications
    </h1>
    <p class="ma-page-subtitle">Track the progress of all your scholarship applications.</p>
  </div>
  <a href="apply.php" class="ma-new-btn">
    <i class="bi bi-plus-lg"></i> New Application
  </a>
</div>

<?php showFlash(); ?>

<!-- ══ FILTER TABS ═══════════════════════════════════════════ -->
<?php
$totalNonDraft = array_sum(array_filter($counts, fn($k) => $k !== 'draft', ARRAY_FILTER_USE_KEY));
$filterTabs = [
  ''           => ['All',          $totalNonDraft],
  'submitted'  => ['Submitted',    $counts['submitted']  ?? 0],
  'reviewing'  => ['Under Review', $counts['reviewing']  ?? 0],
  'eligible'   => ['Eligible',     $counts['eligible']   ?? 0],
  'ineligible' => ['Ineligible',   $counts['ineligible'] ?? 0],
  'ranked'     => ['Ranked',       $counts['ranked']     ?? 0],
  'approved'   => ['Approved',     $counts['approved']   ?? 0],
  'disbursed'  => ['Disbursed',    $counts['disbursed']  ?? 0],
];
?>
<div class="ma-tabs">
  <?php foreach ($filterTabs as $val => [$label, $cnt]):
    $isActive = ($activeTab === $val && $activeTab !== 'draft') || ($val === '' && $activeTab === '');
  ?>
  <a href="?tab=<?= $val ?>"
     class="ma-tab <?= $isActive ? 'active' : '' ?>">
    <?= $label ?>
    <span class="ma-tab-count"><?= $cnt ?></span>
  </a>
  <?php endforeach; ?>

  <!-- Draft tab -->
  <?php $draftActive = ($activeTab === 'draft'); ?>
</div>

<!-- ══════════════════════════════════════════════════════════
     DRAFT TAB
══════════════════════════════════════════════════════════ -->
<?php if ($activeTab === 'draft'): ?>

  <?php if (empty($drafts)): ?>
    <div class="ma-card">
      <div class="ma-empty">
        <div class="ma-empty-icon"><i class="bi bi-pencil-square"></i></div>
        <div class="ma-empty-title">No draft applications</div>
        <div class="ma-empty-text">
          When you save an application as a draft, it will appear here so you can continue editing later.
        </div>
        <a href="apply.php" class="ma-new-btn" style="margin:0 auto;">
          <i class="bi bi-file-earmark-plus"></i> Start New Application
        </a>
      </div>
    </div>

  <?php else: ?>
    <div class="ma-card">
      <div class="ma-card-header">
        <span class="ma-card-title">
          <i class="bi bi-pencil-square" style="color:#f59e0b;"></i>
          Draft Applications
          <span class="ma-card-count">(<?= count($drafts) ?>)</span>
        </span>
        <span class="ma-card-hint">
          <i class="bi bi-info-circle"></i>
          Drafts are not submitted. Click "Continue Editing" to complete.
        </span>
      </div>
      <div class="table-responsive">
        <table class="ma-table">
          <thead>
            <tr>
              <th>Program</th>
              <th>Draft Note</th>
              <th>Last Updated</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($drafts as $draft): ?>
            <tr>
              <td>
                <div class="ma-prog-name"><?= e($draft['program_name']) ?></div>
                <div class="ma-prog-id">Draft #<?= $draft['id'] ?></div>
              </td>
              <td>
                <?php if (!empty($draft['draft_notes'])): ?>
                  <span style="font-size:12.5px;color:#64748b;font-style:italic;">
                    <?= e(mb_strimwidth($draft['draft_notes'], 0, 70, '…')) ?>
                  </span>
                <?php else: ?>
                  <span style="color:#cbd5e1;font-size:13px;">—</span>
                <?php endif; ?>
              </td>
              <td style="font-size:13px;color:#64748b;white-space:nowrap;">
                <?= date('d/m/Y H:i', strtotime($draft['updated_at'])) ?>
              </td>
              <td>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                  <a href="apply.php?draft_id=<?= $draft['id'] ?>" class="ma-edit-btn">
                    <i class="bi bi-pencil-fill"></i> Continue Editing
                  </a>
                  <form method="POST" style="display:inline;"
                        onsubmit="return confirm('Delete this draft? This cannot be undone.')">
                    <input type="hidden" name="delete_draft_id" value="<?= $draft['id'] ?>">
                    <button type="submit" class="ma-del-btn" title="Delete draft">
                      <i class="bi bi-trash3"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════
     SUBMITTED / ALL APPLICATIONS TAB
══════════════════════════════════════════════════════════ -->

  <?php if (empty($applications)): ?>
    <div class="ma-card">
      <div class="ma-empty">
        <div class="ma-empty-icon"><i class="bi bi-folder-x"></i></div>
        <div class="ma-empty-title">No applications found</div>
        <div class="ma-empty-text">
          <?= $filterStatus
            ? 'No applications with status "' . e(ucfirst($filterStatus)) . '" were found.'
            : 'You have not submitted any scholarship applications yet.' ?>
        </div>
        <a href="apply.php" class="ma-new-btn" style="margin:0 auto;">
          <i class="bi bi-file-earmark-plus"></i> Apply Now
        </a>
      </div>
    </div>

  <?php else: ?>
    <div class="ma-card">
      <div class="ma-card-header">
        <span class="ma-card-title">
          <i class="bi bi-folder-check text-primary"></i>
          <?= $filterStatus ? ucfirst($filterStatus) : 'All' ?> Applications
          <span class="ma-card-count">(<?= count($applications) ?>)</span>
        </span>
      </div>
      <div class="table-responsive">
        <table class="ma-table">
          <thead>
            <tr>
              <th>Program</th>
              <th>Status</th>
              <th>Eligibility</th>
              <th>Rank</th>
              <th>Submitted On</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($applications as $app):
              $sc = $statusConfig[$app['status']] ?? [
                'label'  => ucfirst($app['status']),
                'helper' => ucfirst($app['status']),
                'cls'    => 'ma-badge-submitted',
                'icon'   => 'bi-circle',
              ];
            ?>
            <tr>
              <!-- PROGRAM -->
              <td>
                <div class="ma-prog-name"><?= e($app['program_name']) ?></div>
                <div class="ma-prog-id">#<?= $app['id'] ?></div>
              </td>

              <!-- STATUS -->
              <td>
                <span class="ma-badge <?= $sc['cls'] ?>">
                  <i class="bi <?= $sc['icon'] ?>"></i>
                  <?= $sc['label'] ?>
                </span>
                <span class="ma-badge-helper"><?= $sc['helper'] ?></span>
              </td>

              <!-- ELIGIBILITY -->
              <td>
                <?php if ($app['eligible'] === null): ?>
                  <span class="ma-badge ma-elig-pending">
                    <i class="bi bi-hourglass-split"></i> Pending
                  </span>
                  <span class="ma-badge-helper">Not Checked</span>
                <?php elseif ($app['eligible']): ?>
                  <span class="ma-badge ma-elig-eligible">
                    <i class="bi bi-check-circle-fill"></i> Eligible
                  </span>
                  <span class="ma-badge-helper">Qualified</span>
                <?php else: ?>
                  <span class="ma-badge ma-elig-ineligible">
                    <i class="bi bi-x-circle-fill"></i> Ineligible
                  </span>
                  <span class="ma-badge-helper">Not Qualified</span>
                <?php endif; ?>
              </td>

              <!-- RANK -->
              <td>
                <?php if ($app['my_rank']): ?>
                  <span class="ma-rank-val">#<?= $app['my_rank'] ?></span>
                  <?php if ($app['total_score']): ?>
                    <span class="ma-rank-score"><?= number_format($app['total_score'], 1) ?> pts</span>
                  <?php endif; ?>
                <?php else: ?>
                  <span style="color:#cbd5e1;font-size:16px;">—</span>
                <?php endif; ?>
              </td>

              <!-- SUBMITTED ON -->
              <td style="white-space:nowrap;">
                <?php if ($app['submitted_at']): ?>
                  <div style="font-size:13.5px;font-weight:600;color:#0f172a;">
                    <?= date('d/m/Y', strtotime($app['submitted_at'])) ?>
                  </div>
                  <div style="font-size:11.5px;color:#94a3b8;">
                    <?= date('H:i', strtotime($app['submitted_at'])) ?>
                  </div>
                <?php else: ?>
                  <span style="color:#cbd5e1;">—</span>
                <?php endif; ?>
              </td>

              <!-- ACTION -->
              <td>
                <a href="application_details.php?id=<?= $app['id'] ?>"
                   class="ma-view-btn">
                  <i class="bi bi-eye"></i> View
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Info card -->
    <div class="ma-info-card">
      <i class="bi bi-info-circle-fill ma-info-icon"></i>
      <div>
        Click <strong>"View"</strong> to see the detailed timeline, eligibility results,
        ranking position, score breakdown, and all other information about your application.
      </div>
    </div>

  <?php endif; ?>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

