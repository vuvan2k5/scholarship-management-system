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
require_once __DIR__ . '/../includes/navbar.php';
?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
<h1 class="page-title">
      <i class="bi bi-folder-check me-2 text-primary"></i>My Applications
    </h1>
    <p class="page-subtitle">Track the progress of all your scholarship applications.</p>
  </div>
  <a href="apply.php" class="btn btn-primary">
    <i class="bi bi-plus-lg"></i> Nộp đơn mới
  </a>
</div>

<?php showFlash(); ?>

<!-- ── Status filter tabs ──────────────────────────────────── -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;">

  <?php
  $tabs = [
    ''           => ['Tất cả',       array_sum(array_filter($counts, fn($k)=>$k!=='draft', ARRAY_FILTER_USE_KEY))],
    'submitted'  => ['Đã nộp',       $counts['submitted']  ?? 0],
    'reviewing'  => ['Đang xét',     $counts['reviewing']  ?? 0],
    'eligible'   => ['Đủ điều kiện', $counts['eligible']   ?? 0],
    'ineligible' => ['Không đủ ĐK',  $counts['ineligible'] ?? 0],
    'approved'   => ['Được duyệt',   $counts['approved']   ?? 0],
    'rejected'   => ['Từ chối',      $counts['rejected']   ?? 0],
  ];
  foreach ($tabs as $val => [$label, $cnt]):
    $active = ($activeTab === $val && $activeTab !== 'draft');
    if ($val === '' && $activeTab === '') $active = true;
  ?>
  <a href="?tab=<?= $val ?>"
     class="filter-tab <?= $active ? 'active' : '' ?>">
    <?= $label ?>
    <?php if ($cnt > 0): ?>
    <span class="filter-tab-count <?= $active ? 'active' : '' ?>"><?= $cnt ?></span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>

  <!-- Draft tab -->
  <?php $draftActive = ($activeTab === 'draft'); ?>
  <a href="?tab=draft"
     class="filter-tab <?= $draftActive ? 'active' : '' ?>"
     style="<?= $draftActive ? '' : 'border-color:#fde68a;color:#92400e;background:#fffbeb;' ?>">
    <i class="bi bi-pencil-square me-1"></i>Nháp
    <?php if ($draftCount > 0): ?>
    <span class="filter-tab-count" style="background:<?= $draftActive?'rgba(255,255,255,.3)':'#fef3c7' ?>;color:<?= $draftActive?'#fff':'#92400e' ?>;">
      <?= $draftCount ?>
    </span>
    <?php endif; ?>
  </a>

</div>

<!-- ════════════════════════════════════════════════
     DRAFT TAB
════════════════════════════════════════════════ -->
<?php if ($activeTab === 'draft'): ?>

<?php if (empty($drafts)): ?>
  <div class="card">
    <div class="card-body">
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bi bi-pencil-square"></i></div>
        <div class="empty-state-title">Không có đơn nháp</div>
        <div class="empty-state-text">
          Khi bạn nhấn "Lưu nháp" trong form nộp đơn, đơn sẽ xuất hiện ở đây.
        </div>
        <a href="apply.php" class="btn btn-primary">
          <i class="bi bi-file-earmark-plus"></i> Tạo đơn mới
        </a>
      </div>
    </div>
  </div>

<?php else: ?>
  <div class="table-card">
    <div class="table-card-header">
      <span class="table-card-title">
        <i class="bi bi-pencil-square me-2" style="color:#f59e0b;"></i>
        Đơn nháp
        <span style="font-size:12px;font-weight:400;color:#94a3b8;margin-left:6px;">(<?= count($drafts) ?>)</span>
      </span>
      <span style="font-size:12px;color:#94a3b8;">
        <i class="bi bi-info-circle me-1"></i>
        Đơn nháp chưa được gửi đến hội đồng. Nhấn "Tiếp tục chỉnh sửa" để hoàn thiện.
      </span>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Chương trình</th>
            <th>Ghi chú nháp</th>
            <th>Cập nhật lần cuối</th>
            <th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($drafts as $draft): ?>
          <tr>
            <td>
              <div style="font-weight:600;color:#0f172a;"><?= e($draft['program_name']) ?></div>
              <div style="font-size:11px;color:#94a3b8;">Nháp #<?= $draft['id'] ?></div>
            </td>
            <td>
              <?php if (!empty($draft['draft_notes'])): ?>
                <span style="font-size:12.5px;color:#64748b;font-style:italic;">
                  <?= e(mb_strimwidth($draft['draft_notes'], 0, 60, '…')) ?>
                </span>
              <?php else: ?>
                <span style="color:#cbd5e1;font-size:12px;">—</span>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;color:#64748b;">
              <?= date('d/m/Y H:i', strtotime($draft['updated_at'])) ?>
            </td>
            <td>
              <div class="d-flex gap-2 flex-wrap">
                <a href="apply.php?draft_id=<?= $draft['id'] ?>"
                   class="btn btn-sm btn-primary">
                  <i class="bi bi-pencil-fill"></i> Tiếp tục chỉnh sửa
                </a>
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('Xóa đơn nháp này?')">
                  <input type="hidden" name="delete_draft_id" value="<?= $draft['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger">
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
<!-- ════════════════════════════════════════════════
     SUBMITTED / ALL APPLICATIONS TAB
════════════════════════════════════════════════ -->
<?php if (empty($applications)): ?>
  <div class="card">
    <div class="card-body">
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bi bi-folder-x"></i></div>
        <div class="empty-state-title">Không có đơn nào</div>
        <div class="empty-state-text">
          <?= $filterStatus
            ? 'Không có đơn với trạng thái "' . e($filterStatus) . '".'
            : 'Bạn chưa nộp đơn học bổng nào.' ?>
        </div>
        <a href="apply.php" class="btn btn-primary">Nộp đơn ngay</a>
      </div>
    </div>
  </div>

<?php else: ?>
  <div class="table-card">
    <div class="table-card-header">
      <span class="table-card-title">
        <?= $filterStatus ? ucfirst($filterStatus) : 'Tất cả' ?> đơn đăng ký
        <span style="font-size:12px;font-weight:400;color:#94a3b8;margin-left:6px;">(<?= count($applications) ?>)</span>
      </span>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Chương trình</th>
            <th>Trạng thái</th>
            <th>Điều kiện</th>
            <th>Xếp hạng</th>
            <th>Ngày nộp</th>
            <th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($applications as $app): ?>
          <tr>
            <td>
              <div style="font-weight:600;color:#0f172a;"><?= e($app['program_name']) ?></div>
              <div style="font-size:11px;color:#94a3b8;">#<?= $app['id'] ?></div>
            </td>
            <td>
              <span class="badge badge-status-<?= e($app['status']) ?>">
                <?php
                  $statusLabels = [
                    'submitted'=>'Đã nộp','reviewing'=>'Đang xét',
                    'eligible'=>'Đủ ĐK','ineligible'=>'Không đủ ĐK',
                    'approved'=>'Được duyệt','rejected'=>'Từ chối',
                    'disbursed'=>'Đã giải ngân',
                  ];
                  echo $statusLabels[$app['status']] ?? ucfirst(e($app['status']));
                ?>
              </span>
            </td>
            <td>
              <?php if ($app['eligible'] === null): ?>
                <span class="badge badge-pending">Chờ kiểm tra</span>
              <?php elseif ($app['eligible']): ?>
                <span class="badge badge-eligible"><i class="bi bi-check2"></i> Đạt</span>
              <?php else: ?>
                <span class="badge badge-ineligible"><i class="bi bi-x"></i> Không đạt</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($app['my_rank']): ?>
                <span style="font-weight:700;color:#1D4ED8;font-size:14px;">#<?= $app['my_rank'] ?></span>
                <?php if ($app['total_score']): ?>
                  <span style="font-size:11px;color:#94a3b8;display:block;">
                    <?= number_format($app['total_score'],1) ?> điểm
                  </span>
                <?php endif; ?>
              <?php else: ?>
                <span style="color:#94a3b8;font-size:12px;">—</span>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;color:#64748b;">
              <?= $app['submitted_at']
                ? date('d/m/Y', strtotime($app['submitted_at'])) : '—' ?>
            </td>
            <td>
              <a href="application_details.php?id=<?= $app['id'] ?>"
                 class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye"></i> Xem
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
<?php endif; ?>

<style>
.filter-tab {
  display:inline-flex;align-items:center;gap:6px;
  padding:7px 14px;border-radius:20px;font-size:12.5px;
  font-weight:600;text-decoration:none;transition:all .2s;
  background:#fff;color:#475569;border:1.5px solid #e2e8f0;
}
.filter-tab.active {
  background:#1D4ED8;color:#fff;border-color:#1D4ED8;
}
.filter-tab-count {
  background:#f1f5f9;color:#64748b;border-radius:10px;
  padding:1px 7px;font-size:11px;
}
.filter-tab.active .filter-tab-count { background:rgba(255,255,255,.25);color:#fff; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
