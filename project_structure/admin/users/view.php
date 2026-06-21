<?php
// ============================================================
// admin/users/view.php  –  User Profile Detail (read-only)
// ============================================================
$pageTitle = 'User Profile';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

// Fetch student profile if exists
$profile = null;
if ($user && $user['role'] === 'student') {
    $ps = $pdo->prepare("SELECT * FROM student_profiles WHERE student_id = ?");
    $ps->execute([$id]);
    $profile = $ps->fetch();
}

// Fetch application count for this user
$appCount = 0;
if ($user) {
    $as = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ?");
    $as->execute([$id]);
    $appCount = (int)$as->fetchColumn();
}

// Fetch recent notifications sent to this user (latest 5)
$userNotifs = [];
if ($user) {
    $ns = $pdo->prepare("
        SELECT title, type, is_read, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $ns->execute([$id]);
    $userNotifs = $ns->fetchAll();
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

if (!$user):
?>
  <div class="container py-4">
<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
    <div class="page-header-left">
      <h1 class="page-title">User Not Found</h1>
    </div>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
  </div>
  <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>User #<?= e($id) ?> does not exist.</div>
<?php
  require_once '../../includes/footer.php';
  exit;
endif;

// Role display helpers
$roleBadge = match($user['role']) {
    'admin'    => ['class' => 'badge-admin',    'label' => 'Admin',    'icon' => 'shield-lock'],
    'reviewer' => ['class' => 'badge-reviewer', 'label' => 'Reviewer', 'icon' => 'person-badge'],
    default    => ['class' => 'badge-student',  'label' => 'Student',  'icon' => 'mortarboard'],
};
?>

<!-- Page Header -->
<div class="container py-4">
<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">User Profile</h1>
    <p class="page-subtitle">Read-only profile for user #<?= e($user['id']) ?>. No edits can be made from this view.</p>
  </div>
  <a href="index.php" class="btn btn-secondary" id="back-to-users">
    <i class="bi bi-arrow-left"></i> Back to Directory
  </a>
</div>

<!-- ── Notice Banner ─────────────────────────────────────────── -->
<div class="alert alert-info mb-4" style="border-left:4px solid var(--info);">
  <i class="bi bi-info-circle me-2"></i>
  <strong>Read-Only View.</strong> User account information can only be changed through the registration and authentication system.
</div>

<div class="row g-3">

  <!-- Left: Profile Card -->
  <div class="col-lg-4">
    <div class="card" style="text-align:center;padding:32px 24px;">
      <!-- Avatar -->
      <div style="margin:0 auto 16px;width:80px;height:80px;border-radius:50%;
                  background:linear-gradient(135deg,#2563eb,#1e40af);
                  display:flex;align-items:center;justify-content:center;
                  font-size:32px;font-weight:900;color:#fff;
                  box-shadow:0 4px 16px rgba(37,99,235,.35);">
        <?= strtoupper(mb_substr($user['full_name'], 0, 1)) ?>
      </div>

      <div style="font-size:19px;font-weight:800;color:var(--gray-900);letter-spacing:-.01em;margin-bottom:6px;">
        <?= e($user['full_name']) ?>
      </div>
      <div style="font-size:13px;color:var(--gray-500);margin-bottom:14px;">
        <?= e($user['email']) ?>
      </div>

      <span class="badge <?= $roleBadge['class'] ?>" style="font-size:13px;padding:6px 16px;margin-bottom:20px;">
        <i class="bi bi-<?= $roleBadge['icon'] ?>"></i> <?= $roleBadge['label'] ?>
      </span>

      <?php if ($user['student_code']): ?>
        <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:var(--radius-sm);
                    padding:10px 14px;margin-bottom:8px;">
          <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--gray-400);margin-bottom:4px;">Student ID</div>
          <code style="font-size:14px;font-weight:700;color:var(--primary);"><?= e($user['student_code']) ?></code>
        </div>
      <?php endif; ?>

      <?php if ($user['role'] === 'student'): ?>
        <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--gray-100);
                    font-size:13px;color:var(--gray-500);">
          <i class="bi bi-folder2-open me-1"></i>
          <strong style="color:var(--gray-800);"><?= $appCount ?></strong> application<?= $appCount !== 1 ? 's' : '' ?> submitted
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right: Detail Panels -->
  <div class="col-lg-8 d-flex flex-column gap-3">

    <!-- Account Information -->
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-4" style="padding-bottom:12px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-person-vcard me-2" style="color:var(--primary);"></i>Account Information
        </div>
        <table class="table detail-table mb-0" style="margin-bottom:0;">
          <tbody>
            <tr>
              <th>User ID</th>
              <td>#<?= e($user['id']) ?></td>
            </tr>
            <tr>
              <th>Full Name</th>
              <td><?= e($user['full_name']) ?></td>
            </tr>
            <tr>
              <th>Email Address</th>
              <td><?= e($user['email']) ?></td>
            </tr>
            <tr>
              <th>System Role</th>
              <td>
                <span class="badge <?= $roleBadge['class'] ?>">
                  <i class="bi bi-<?= $roleBadge['icon'] ?>"></i> <?= $roleBadge['label'] ?>
                </span>
              </td>
            </tr>
            <tr>
              <th>Student ID</th>
              <td>
                <?php if ($user['student_code']): ?>
                  <code style="font-size:12.5px;background:#f1f5f9;padding:2px 8px;border-radius:4px;">
                    <?= e($user['student_code']) ?>
                  </code>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th>Registered</th>
              <td class="text-muted">
                <?= $user['created_at']
                    ? e(date('d M Y, H:i', strtotime($user['created_at'])))
                    : '—' ?>
              </td>
            </tr>
            <tr>
              <th>Last Updated</th>
              <td class="text-muted">
                <?= isset($user['updated_at']) && $user['updated_at']
                    ? e(date('d M Y, H:i', strtotime($user['updated_at'])))
                    : '—' ?>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Student Academic Profile (only for students) -->
    <?php if ($user['role'] === 'student'): ?>
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-4" style="padding-bottom:12px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-mortarboard me-2" style="color:var(--success);"></i>Academic Profile
        </div>
        <?php if ($profile): ?>
          <div class="row g-3">
            <div class="col-sm-6">
              <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:var(--radius-sm);padding:14px 16px;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--gray-400);margin-bottom:4px;">Faculty</div>
                <div style="font-size:14px;font-weight:600;color:var(--gray-800);"><?= e($profile['faculty'] ?: '—') ?></div>
              </div>
            </div>
            <div class="col-sm-6">
              <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:var(--radius-sm);padding:14px 16px;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--gray-400);margin-bottom:4px;">Major</div>
                <div style="font-size:14px;font-weight:600;color:var(--gray-800);"><?= e($profile['major'] ?: '—') ?></div>
              </div>
            </div>
            <div class="col-sm-4">
              <div style="background:var(--primary-light);border:1px solid var(--primary-muted);border-radius:var(--radius-sm);padding:14px 16px;text-align:center;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--primary);margin-bottom:4px;">GPA</div>
                <div style="font-size:22px;font-weight:900;color:var(--primary);"><?= number_format((float)$profile['gpa'], 2) ?></div>
              </div>
            </div>
            <div class="col-sm-4">
              <div style="background:var(--success-light);border:1px solid var(--success-muted);border-radius:var(--radius-sm);padding:14px 16px;text-align:center;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--success);margin-bottom:4px;">Activities</div>
                <div style="font-size:22px;font-weight:900;color:var(--success);"><?= (int)$profile['activities_count'] ?></div>
              </div>
            </div>
            <div class="col-sm-4">
              <div style="background:var(--warning-light);border:1px solid var(--warning-muted);border-radius:var(--radius-sm);padding:14px 16px;text-align:center;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--warning);margin-bottom:4px;">Research</div>
                <div style="font-size:22px;font-weight:900;color:var(--warning);"><?= (int)$profile['research_count'] ?></div>
              </div>
            </div>
            <div class="col-12">
              <table class="table detail-table mb-0">
                <tbody>
                  <tr>
                    <th>Family Income</th>
                    <td><?= $profile['family_income'] !== null ? number_format((float)$profile['family_income'], 0, ',', '.') . ' đ/month' : '—' ?></td>
                  </tr>
                  <tr>
                    <th>Failed Subjects</th>
                    <td><?= (int)$profile['failed_subjects'] ?></td>
                  </tr>
                  <tr>
                    <th>Language Certificate</th>
                    <td><?= $profile['has_language_cert'] ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-inactive">No</span>' ?></td>
                  </tr>
                  <tr>
                    <th>Disadvantaged</th>
                    <td><?= $profile['is_disadvantaged'] ? '<span class="badge badge-warning">Yes</span>' : '<span class="badge badge-inactive">No</span>' ?></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        <?php else: ?>
          <div class="empty-state" style="padding:32px 0;">
            <span class="empty-state-icon" style="font-size:36px;"><i class="bi bi-file-person"></i></span>
            <div class="empty-state-title" style="font-size:15px;">No Academic Profile</div>
            <div class="empty-state-text">This student has not completed their academic profile yet.</div>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Recent Notifications -->
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:12px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-bell me-2" style="color:var(--warning);"></i>Recent Notifications
        </div>
        <?php if (empty($userNotifs)): ?>
          <p class="text-muted" style="font-size:13px;margin:0;">No notifications have been sent to this user.</p>
        <?php else: ?>
          <ul class="list-unstyled mb-0">
            <?php
            $iconMap  = ['info'=>'info-circle','success'=>'check-circle','warning'=>'exclamation-triangle','error'=>'x-octagon'];
            $colorMap = ['info'=>'var(--info)','success'=>'var(--success)','warning'=>'var(--warning)','error'=>'var(--danger)'];
            foreach ($userNotifs as $n):
              $nType = $n['type'] ?? 'info';
              $icon  = $iconMap[$nType]  ?? 'bell';
              $color = $colorMap[$nType] ?? 'var(--info)';
            ?>
              <li style="display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid var(--gray-100);">
                <span style="color:<?= $color ?>;font-size:15px;margin-top:2px;flex-shrink:0;">
                  <i class="bi bi-<?= $icon ?>"></i>
                </span>
                <div style="flex:1;min-width:0;">
                  <div style="font-weight:600;font-size:13px;color:var(--gray-800);">
                    <?= e($n['title']) ?>
                    <?php if (!$n['is_read']): ?>
                      <span style="display:inline-block;width:7px;height:7px;border-radius:50%;
                                   background:var(--primary);vertical-align:middle;margin-left:5px;"></span>
                    <?php endif; ?>
                  </div>
                  <div style="font-size:12px;color:var(--gray-500);margin-top:1px;">
                    <?= e(date('d M Y, H:i', strtotime($n['created_at']))) ?>
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /col-lg-8 -->
</div>

<?php require_once '../../includes/footer.php'; ?>
