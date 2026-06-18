<?php
// ============================================================
// admin/users/index.php  –  User Directory (read-only)
// Admin may only VIEW users. CRUD is disabled by policy.
// ============================================================
$pageTitle = 'User Directory';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// ── Statistics ───────────────────────────────────────────────
$totalUsers     = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalStudents  = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$totalReviewers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'reviewer'")->fetchColumn();
$totalAdmins    = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

// ── Search & Filter ──────────────────────────────────────────
$search     = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role']   ?? '');

$validRoles = ['student', 'reviewer', 'admin'];

// ── Pagination ───────────────────────────────────────────────
$perPage    = 15;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;

// ── Build WHERE clause ───────────────────────────────────────
$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = "(u.full_name LIKE ? OR u.email LIKE ? OR u.student_code LIKE ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($roleFilter !== '' && in_array($roleFilter, $validRoles)) {
    $where[]  = "u.role = ?";
    $params[] = $roleFilter;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u $whereSql");
$countStmt->execute($params);
$totalFiltered = (int) $countStmt->fetchColumn();
$totalPages    = max(1, (int) ceil($totalFiltered / $perPage));
$page          = min($page, $totalPages);
$offset        = ($page - 1) * $perPage;

// Fetch users
$dataStmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.email, u.role, u.student_code, u.created_at
    FROM users u
    $whereSql
    ORDER BY u.id DESC
    LIMIT $perPage OFFSET $offset
");
$dataStmt->execute($params);
$users = $dataStmt->fetchAll();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">User Directory</h1>
    <p class="page-subtitle">Browse and view user accounts. This module is read-only — accounts are managed through the registration system.</p>
  </div>
</div>

<!-- ── Policy Notice (shown when a disabled action was attempted) ── -->
<?php
$policyMsg = match($_GET['policy'] ?? '') {
    'no_create' => 'Creating users is disabled. Accounts are registered through the authentication system.',
    'no_edit'   => 'Editing user accounts is not permitted from User Management.',
    'no_delete' => 'Deleting user accounts is not permitted from User Management.',
    default     => '',
};
if ($policyMsg): ?>
  <div class="alert alert-warning mb-4" style="border-left:4px solid var(--warning);">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Action Not Allowed:</strong> <?= e($policyMsg) ?>
  </div>
<?php endif; ?>


<!-- ── Statistics ────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-people"></i></div>
      <div class="stat-body">
        <div class="stat-label">Total Users</div>
        <div class="stat-value"><?= $totalUsers ?></div>
        <div class="stat-trend">All roles</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon cyan"><i class="bi bi-mortarboard"></i></div>
      <div class="stat-body">
        <div class="stat-label">Students</div>
        <div class="stat-value"><?= $totalStudents ?></div>
        <div class="stat-trend">Registered applicants</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-person-badge"></i></div>
      <div class="stat-body">
        <div class="stat-label">Reviewers</div>
        <div class="stat-value"><?= $totalReviewers ?></div>
        <div class="stat-trend">Evaluation council</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(109,40,217,.1);color:#7c3aed;"><i class="bi bi-shield-lock"></i></div>
      <div class="stat-body">
        <div class="stat-label">Admins</div>
        <div class="stat-value"><?= $totalAdmins ?></div>
        <div class="stat-trend">System administrators</div>
      </div>
    </div>
  </div>
</div>

<!-- ── Search & Filter Bar ───────────────────────────────────── -->
<div class="table-card mb-3" style="padding:18px 24px;">
  <form method="GET" id="user-filter-form" class="d-flex flex-wrap gap-2 align-items-end">

    <!-- Search input -->
    <div style="flex:1;min-width:220px;">
      <label class="form-label" for="search-input" style="margin-bottom:5px;">Search</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input id="search-input"
               type="text"
               name="search"
               class="form-control"
               placeholder="Name, Student ID, or Email…"
               value="<?= e($search) ?>">
      </div>
    </div>

    <!-- Role filter -->
    <div style="min-width:170px;">
      <label class="form-label" for="role-filter" style="margin-bottom:5px;">Role</label>
      <select id="role-filter" name="role" class="form-select">
        <option value="">All Roles</option>
        <option value="student"  <?= $roleFilter === 'student'  ? 'selected' : '' ?>>Student</option>
        <option value="reviewer" <?= $roleFilter === 'reviewer' ? 'selected' : '' ?>>Reviewer</option>
        <option value="admin"    <?= $roleFilter === 'admin'    ? 'selected' : '' ?>>Admin</option>
      </select>
    </div>

    <div class="d-flex gap-2" style="padding-top:24px;">
      <button type="submit" class="btn btn-primary" id="filter-btn">
        <i class="bi bi-funnel"></i> Filter
      </button>
      <?php if ($search !== '' || $roleFilter !== ''): ?>
        <a href="index.php" class="btn btn-secondary">
          <i class="bi bi-x-lg"></i> Clear
        </a>
      <?php endif; ?>
    </div>

  </form>
</div>

<!-- ── User Table ─────────────────────────────────────────────── -->
<div class="table-card">
  <div class="table-card-header">
    <span class="table-card-title">
      <?php if ($search !== '' || $roleFilter !== ''): ?>
        <?= $totalFiltered ?> result<?= $totalFiltered !== 1 ? 's' : '' ?> found
      <?php else: ?>
        All Users
      <?php endif; ?>
    </span>
    <?php if ($totalPages > 1): ?>
      <span class="text-muted" style="font-size:12.5px;">
        Page <?= $page ?> of <?= $totalPages ?>
      </span>
    <?php endif; ?>
  </div>

  <div class="table-responsive">
    <table class="table" id="users-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Full Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Student ID</th>
          <th>Registered</th>
          <th>Detail</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr>
            <td colspan="7">
              <div class="empty-state" style="padding:48px 24px;">
                <span class="empty-state-icon"><i class="bi bi-people"></i></span>
                <div class="empty-state-title">No users found</div>
                <div class="empty-state-text">
                  <?= ($search !== '' || $roleFilter !== '')
                      ? 'Try adjusting your search or filter criteria.'
                      : 'No user accounts exist yet.' ?>
                </div>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($users as $user):
            $role = $user['role'];
            $badgeClass = match($role) {
                'admin'    => 'badge-admin',
                'reviewer' => 'badge-reviewer',
                default    => 'badge-student',
            };
            $roleLabel = match($role) {
                'admin'    => 'Admin',
                'reviewer' => 'Reviewer',
                default    => 'Student',
            };
          ?>
            <tr>
              <td><span class="text-muted">#<?= e($user['id']) ?></span></td>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div style="width:32px;height:32px;border-radius:50%;
                              background:linear-gradient(135deg,#2563eb,#1e40af);
                              display:flex;align-items:center;justify-content:center;
                              font-size:13px;font-weight:700;color:#fff;flex-shrink:0;">
                    <?= strtoupper(mb_substr($user['full_name'], 0, 1)) ?>
                  </div>
                  <strong><?= e($user['full_name']) ?></strong>
                </div>
              </td>
              <td class="text-muted"><?= e($user['email']) ?></td>
              <td>
                <span class="badge <?= $badgeClass ?>"><?= $roleLabel ?></span>
              </td>
              <td>
                <?php if ($user['student_code']): ?>
                  <code style="font-size:12px;background:#f1f5f9;padding:2px 7px;border-radius:4px;color:var(--gray-700);">
                    <?= e($user['student_code']) ?>
                  </code>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-muted" style="white-space:nowrap;">
                <?= $user['created_at'] ? e(date('d M Y', strtotime($user['created_at']))) : '—' ?>
              </td>
              <td>
                <a href="view.php?id=<?= $user['id'] ?>"
                   class="btn btn-sm btn-outline-primary btn-action"
                   id="view-user-<?= $user['id'] ?>">
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
        Showing <?= min(($page - 1) * $perPage + 1, $totalFiltered) ?>–<?= min($page * $perPage, $totalFiltered) ?>
        of <?= $totalFiltered ?> users
      </span>
      <nav>
        <ul class="pagination mb-0" style="gap:4px;display:flex;list-style:none;padding:0;">
          <!-- Previous -->
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link"
               href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
              <i class="bi bi-chevron-left"></i>
            </a>
          </li>

          <?php
          $start = max(1, $page - 2);
          $end   = min($totalPages, $page + 2);
          if ($start > 1): ?>
            <li class="page-item">
              <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
            </li>
            <?php if ($start > 2): ?>
              <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
          <?php endif; ?>

          <?php for ($p = $start; $p <= $end; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
              <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>">
                <?= $p ?>
              </a>
            </li>
          <?php endfor; ?>

          <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?>
              <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
            <li class="page-item">
              <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>">
                <?= $totalPages ?>
              </a>
            </li>
          <?php endif; ?>

          <!-- Next -->
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link"
               href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
              <i class="bi bi-chevron-right"></i>
            </a>
          </li>
        </ul>
      </nav>
    </div>
  <?php endif; ?>

</div>

<?php require_once '../../includes/footer.php'; ?>
