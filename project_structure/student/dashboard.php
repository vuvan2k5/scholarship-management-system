<?php
$pageTitle = 'Student Dashboard';

require_once '../config/db.php';
require_once '../includes/auth.php';

requireLogin();
requireRole('student');

require_once '../includes/header.php';
require_once '../includes/student_header.php';

$pdo       = getDB();
$studentId = currentUserId();

// ── Stats ──────────────────────────────────────────────────────
$totalApps = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ?");
$totalApps->execute([$studentId]);
$totalApplications = (int)$totalApps->fetchColumn();

$approvedApps = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ? AND status = 'approved'");
$approvedApps->execute([$studentId]);
$approvedApplications = (int)$approvedApps->fetchColumn();

$pendingApps = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ? AND status IN ('submitted','reviewing','eligible')");
$pendingApps->execute([$studentId]);
$pendingApplications = (int)$pendingApps->fetchColumn();

$unreadNotif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unreadNotif->execute([$studentId]);
$unreadNotifications = (int)$unreadNotif->fetchColumn();

// ── Open programs count ────────────────────────────────────────
$openPrograms = (int)$pdo->query("SELECT COUNT(*) FROM scholarship_programs WHERE status='open'")->fetchColumn();

// ── Draft count ────────────────────────────────────────────────
$stmtDraftCnt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id=? AND status='draft'");
$stmtDraftCnt->execute([$studentId]);
$draftCount = (int)$stmtDraftCnt->fetchColumn();

// ── Profile completeness ───────────────────────────────────────
$stmtProf = $pdo->prepare("SELECT * FROM student_profiles WHERE student_id = ?");
$stmtProf->execute([$studentId]);
$profile = $stmtProf->fetch();

// ── Recent Applications ────────────────────────────────────────
$stmtRecent = $pdo->prepare("
    SELECT a.*, sp.name AS program_name
    FROM   applications a
    JOIN   scholarship_programs sp ON a.program_id = sp.id
    WHERE  a.student_id = ?
    ORDER  BY a.id DESC LIMIT 5
");
$stmtRecent->execute([$studentId]);
$recentApplications = $stmtRecent->fetchAll();

// ── Latest Notifications ───────────────────────────────────────
$stmtNotif = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 8");
$stmtNotif->execute([$studentId]);
$latestNotifications = $stmtNotif->fetchAll();

$stmtUnreadNotif = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 8");
$stmtUnreadNotif->execute([$studentId]);
$unreadNotifList = $stmtUnreadNotif->fetchAll();

// ── Scholarship Matching ───────────────────────────────────────
$matchedPrograms = [];
if ($profile) {
    $stmtMatch = $pdo->prepare("
        SELECT sp.*,
               COUNT(DISTINCT er.id) AS rule_count
        FROM scholarship_programs sp
        LEFT JOIN eligibility_rules er ON er.program_id = sp.id
        WHERE sp.status = 'open'
          AND sp.id NOT IN (
              SELECT program_id FROM applications
              WHERE student_id = ? AND status != 'draft'
          )
        GROUP BY sp.id
        ORDER BY sp.end_date ASC
    ");
    $stmtMatch->execute([$studentId]);
    $candidatePrograms = $stmtMatch->fetchAll();
}

if (!empty($candidatePrograms)) {
    $progIds  = array_column($candidatePrograms, 'id');
    $inClause = implode(',', array_fill(0, count($progIds), '?'));
    $stmtRules = $pdo->prepare(
        "SELECT * FROM eligibility_rules WHERE program_id IN ($inClause)"
    );
    $stmtRules->execute($progIds);
    $rulesByProgram = $stmtRules->fetchAll();
} ?>
<!-- Student header is included at the top of the file via student_header.php -->


<div class="dashboard-container">
<!-- ══ CANVAS ══════════════════════════════════════════════════ -->
<div class="stu-canvas">

  <?php showFlash(); ?>

  <?php if (!$profile): ?>
  <div class="alert alert-warning mb-4" style="font-size:13px;border-radius:12px;">
    <i class="bi bi-person-exclamation me-2"></i>
    <strong>Profile incomplete!</strong> Fill in your academic profile so the system can check your eligibility.
    <a href="profile.php" class="btn btn-sm btn-warning ms-3" style="font-size:12px;">Complete Profile</a>
  </div>
  <?php endif; ?>

  <!-- ══ WELCOME CARD ═══════════════════════════════════════════ -->
  <!-- <div class="welcome-hero">
    <div class="welcome-content">
        <div class="welcome-badge">
            🎓 Student Dashboard
        </div>

        <h1 class="welcome-title">
            Welcome back,  /*e(currentUserName()) */ //nho them tag dong mo cho php
        </h1>

        <p class="welcome-subtitle">
            Track your scholarship applications, monitor progress,
            and discover new opportunities tailored to your profile.
        </p>
    </div>

    <div class="welcome-icon">
        <i class="bi bi-mortarboard-fill"></i>
    </div>
</div> -->

  <!-- ══ STAT CARDS ═════════════════════════════════════════════ -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-sm-3">
      <div class="scard">
        <div class="scard-icon blue"><i class="bi bi-folder2-open"></i></div>
        <div>
          <div class="scard-label">Total Applications</div>
          <div class="scard-value"><?= $totalApplications ?></div>
          <div class="scard-sub">All time</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-sm-3">
      <div class="scard">
        <div class="scard-icon amber"><i class="bi bi-hourglass-split"></i></div>
        <div>
          <div class="scard-label">In Progress</div>
          <div class="scard-value"><?= $pendingApplications ?></div>
          <div class="scard-sub">Under review</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-sm-3">
      <div class="scard">
        <div class="scard-icon green"><i class="bi bi-patch-check-fill"></i></div>
        <div>
          <div class="scard-label">Approved</div>
          <div class="scard-value"><?= $approvedApplications ?></div>
          <div class="scard-sub">Scholarships awarded</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-sm-3">
      <div class="scard">
        <div class="scard-icon <?= $unreadNotifications > 0 ? 'red' : 'violet' ?>">
          <i class="bi bi-bell<?= $unreadNotifications > 0 ? '-fill' : '' ?>"></i>
        </div>
        <div>
          <div class="scard-label">Notifications</div>
          <div class="scard-value"><?= $unreadNotifications ?></div>
          <div class="scard-sub">Unread</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ QUICK ACTIONS ══════════════════════════════════════════ -->
  <div class="card mb-4" style="border-radius:16px;border:1px solid #E2E8F0;box-shadow:0 1px 4px rgba(15,23,42,.05);">
    <div class="card-body" style="padding:20px 24px;">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#CBD5E1;margin-bottom:14px;">
        Quick Actions
      </div>
      <div class="qa-grid">
        <a href="scholarships.php" class="qa-item">
          <i class="bi bi-award"></i> All Scholarships
          <?php if ($openPrograms > 0): ?>
            <span class="qa-badge"><?= $openPrograms ?> open</span>
          <?php endif; ?>
        </a>
        <a href="apply.php" class="qa-item">
          <i class="bi bi-file-earmark-plus"></i> Apply Now
        </a>
        <a href="my_applications.php" class="qa-item">
          <i class="bi bi-folder-check"></i> Track Applications
        </a>
        <a href="my_results.php" class="qa-item">
          <i class="bi bi-trophy"></i> Results
        </a>
        <a href="profile.php" class="qa-item">
          <i class="bi bi-person-gear"></i> My Profile
        </a>
        <a href="notifications.php" class="qa-item">
          <i class="bi bi-bell"></i> Notifications
          <?php if ($unreadNotifications > 0): ?>
            <span class="qa-badge"><?= $unreadNotifications ?></span>
          <?php endif; ?>
        </a>
      </div>
    </div>
  </div>

  <!-- ══ SCHOLARSHIP RECOMMENDATIONS ═══════════════════════════ -->
  <?php if ($profile && !empty($matchedPrograms)): ?>
  <div class="card mb-4" style="border-radius:16px;border:1px solid #E2E8F0;box-shadow:0 1px 4px rgba(15,23,42,.05);overflow:hidden;">
    <div style="padding:20px 24px 18px;border-bottom:1px solid #F1F5F9;background:linear-gradient(135deg,#EFF6FF 0%,#F8FAFC 100%);">
      <div class="section-header">
        <div>
          <div class="section-title">
            <span class="section-title-icon" style="background:#FEF3C7;">⭐</span>
            Scholarship Recommendations for You
          </div>
          <div style="font-size:12.5px;color:#64748b;margin-top:5px;margin-left:40px;">
            Based on your profile — GPA <strong style="color:#1D4ED8;"><?= $profile['gpa'] ?></strong>,
            <?= $profile['activities_count'] ?> activities,
            <?= $profile['research_count'] ?? 0 ?> research projects
          </div>
        </div>
        <a href="scholarships.php" class="btn btn-sm btn-outline-primary" style="border-radius:8px;">
          View All <i class="bi bi-arrow-right ms-1"></i>
        </a>
      </div>
    </div>
    <div style="padding:20px 24px;">
      <div class="row g-3">
      <?php foreach ($matchedPrograms as $mp):
        $fitClass  = $mp['fit_pct'] >= 100 ? 'green' : ($mp['fit_pct'] >= 60 ? 'amber' : 'red');
        $barColor  = $mp['fit_pct'] >= 100 ? '#16A34A' : ($mp['fit_pct'] >= 60 ? '#D97706' : '#DC2626');
        $daysLeft  = $mp['end_date']
            ? (int)ceil((strtotime($mp['end_date']) - time()) / 86400) : null;
        $urgentDay = $daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 7;
      ?>
      <div class="col-sm-6 col-xl-3">
        <div class="mc <?= $mp['fully_eligible'] ? 'mc-eligible' : '' ?>">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="mc-tag <?= $mp['fully_eligible'] ? 'text-success' : '' ?>">
              <?php if ($mp['fully_eligible']): ?>
                <i class="bi bi-check-circle-fill text-success me-1"></i>Eligible
              <?php else: ?>
                <i class="bi bi-exclamation-circle-fill text-warning me-1"></i>Nearly eligible
              <?php endif; ?>
            </span>
            <span class="mc-fit-badge <?= $fitClass ?>"><?= $mp['fit_pct'] ?>%</span>
          </div>
          <div style="font-size:13.5px;font-weight:700;color:#0F172A;line-height:1.35;min-height:38px;margin-bottom:4px;">
            <?= e($mp['name']) ?>
          </div>
          <div class="mc-bar-track">
            <div class="mc-bar-fill" style="width:<?= $mp['fit_pct'] ?>%;background:<?= $barColor ?>;"></div>
          </div>
          <div class="d-flex justify-content-between mb-2" style="font-size:11.5px;color:#64748b;">
            <span><i class="bi bi-people me-1"></i><?= $mp['slots'] ?> slots</span>
            <?php if ($daysLeft !== null && $daysLeft >= 0): ?>
            <span style="color:<?= $urgentDay ? '#DC2626' : '#64748b' ?>;font-weight:<?= $urgentDay ? '700' : '400' ?>;">
              <i class="bi bi-clock me-1"></i><?= $daysLeft ?> days left
            </span>
            <?php endif; ?>
          </div>
          <?php if (!empty($mp['failed_rules'])): ?>
          <div style="background:#FEF2F2;border-radius:8px;padding:8px 10px;margin-bottom:10px;">
            <?php
            $ruleNames = ['gpa'=>'GPA','activities_count'=>'Activities',
              'activities'=>'Activities','failed_subjects'=>'Failed Subjects',
              'research_count'=>'Research Projects','research_projects'=>'Research Projects',
              'has_language_cert'=>'Language Certificate',
              'family_income'=>'Family Income'];
            foreach (array_slice($mp['failed_rules'],0,2) as $fr):
              $rn = $ruleNames[$fr['rule_type']] ?? $fr['rule_type'];
            ?>
            <div style="font-size:11px;color:#DC2626;display:flex;align-items:center;gap:4px;">
              <i class="bi bi-x-circle-fill" style="font-size:10px;flex-shrink:0;"></i>
              <?= e($rn) ?> <?= e($fr['operator']) ?> <?= e($fr['value']) ?>
            </div>
            <?php endforeach;
            if (count($mp['failed_rules']) > 2): ?>
            <div style="font-size:10.5px;color:#94A3B8;margin-top:2px;">
              +<?= count($mp['failed_rules'])-2 ?> more requirements
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <div style="font-size:11.5px;color:#16A34A;font-weight:700;margin-bottom:12px;">
            <i class="bi bi-currency-exchange me-1"></i>
            <?= number_format((float)$mp['budget'], 0, ',', '.') ?> ₫
          </div>
          <div class="mt-auto">
            <a href="apply.php?program_id=<?= $mp['id'] ?>"
               class="btn btn-sm w-100 <?= $mp['fully_eligible'] ? 'btn-primary' : 'btn-outline-primary' ?>"
               style="border-radius:9px;">
              <i class="bi bi-<?= $mp['fully_eligible'] ? 'send-fill' : 'eye' ?>"></i>
              <?= $mp['fully_eligible'] ? 'Apply Now' : 'View Details' ?>
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php elseif (!$profile): ?>
  <div class="alert mb-4"
       style="background:#EFF6FF;border:1.5px solid #BFDBFE;border-radius:14px;
              padding:16px 20px;font-size:13px;color:#1E40AF;display:flex;
              align-items:center;gap:12px;">
    <span style="font-size:22px;">⭐</span>
    <div>
      <strong>Scholarship Recommendations:</strong>
      <a href="profile.php" class="fw-bold ms-1" style="color:#1D4ED8;">
        Complete your academic profile
      </a>
      so the system can suggest the best-matching scholarships for you.
    </div>
  </div>
  <?php endif; ?>

  <!-- ══ MAIN CONTENT: APPLICATIONS + NOTIFICATIONS ══════════════ -->
  <div class="row g-4">

    <!-- ── LEFT: Recent Applications ──────────────────────────── -->
    <div class="col-lg-7">
      <?php if (empty($recentApplications)): ?>
      <div class="dash-table-card" style="border-radius:16px;">
        <div class="card-body" style="padding:40px 20px;text-align:center;">
          <i class="bi bi-folder-x" style="font-size:40px;color:#CBD5E1;display:block;margin-bottom:12px;"></i>
          <div style="font-size:15px;font-weight:700;color:#0F172A;margin-bottom:6px;">No Applications Yet</div>
          <div style="font-size:13px;color:#94A3B8;margin-bottom:20px;">
            Start by browsing available scholarships and submit your first application.
          </div>
          <a href="scholarships.php" class="btn btn-primary" style="border-radius:10px;">
            <i class="bi bi-award"></i> Browse Scholarships
          </a>
        </div>
      </div>
      <?php else: ?>
      <div class="dash-table-card">
        <div class="dash-table-header">
          <span class="dash-table-title">
            <i class="bi bi-folder2-open me-2 text-primary"></i>Recent Applications
          </span>
          <a href="my_applications.php" class="btn btn-sm btn-outline-primary" style="border-radius:8px;font-size:12.5px;">
            View All <i class="bi bi-arrow-right ms-1"></i>
          </a>
        </div>
        <div class="dash-table table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Program</th>
                <th>Status</th>
                <th>Eligibility</th>
                <th>Submitted</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentApplications as $app): ?>
              <tr>
                <td>
                  <div class="prog-name"><?= e($app['program_name']) ?></div>
                  <div class="prog-id">#<?= $app['id'] ?></div>
                </td>
                <td>
                  <?php
                    $sbMap = [
                      'submitted'  => ['sb-submitted',  'Submitted'],
                      'reviewing'  => ['sb-reviewing',  'Reviewing'],
                      'eligible'   => ['sb-eligible',   'Eligible'],
                      'ineligible' => ['sb-ineligible', 'Ineligible'],
                      'approved'   => ['sb-approved',   'Approved'],
                      'rejected'   => ['sb-rejected',   'Rejected'],
                      'draft'      => ['sb-draft',      'Draft'],
                      'disbursed'  => ['sb-eligible',   'Disbursed'],
                    ];
                    [$cls,$lbl] = $sbMap[$app['status']] ?? ['sb-submitted', ucfirst($app['status'])];
                  ?>
                  <span class="sb <?= $cls ?>"><?= $lbl ?></span>
                </td>
                <td>
                  <?php if ($app['eligible'] === null): ?>
                    <span class="sb sb-pending" style="font-size:11px;">Pending</span>
                  <?php elseif ($app['eligible']): ?>
                    <span class="sb sb-eligible"><i class="bi bi-check2"></i> Yes</span>
                  <?php else: ?>
                    <span class="sb sb-ineligible"><i class="bi bi-x"></i> No</span>
                  <?php endif; ?>
                </td>
                <td style="font-size:12px;color:#94A3B8;">
                  <?= $app['submitted_at'] ? date('d/m/Y', strtotime($app['submitted_at'])) : '—' ?>
                </td>
                <td>
                  <a href="application_details.php?id=<?= $app['id'] ?>"
                     class="btn btn-sm btn-outline-primary"
                     style="border-radius:8px;padding:5px 12px;">
                    <i class="bi bi-eye"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── RIGHT: Notifications Panel ─────────────────────────── -->
    <div class="col-lg-5">
      <div class="notif-panel">

        <!-- Header -->
        <div class="notif-panel-header">
          <span class="notif-panel-title">
            <span class="notif-bell-wrap"><i class="bi bi-bell-fill text-primary"></i></span>
            Notifications
            <?php if ($unreadNotifications > 0): ?>
              <span class="notif-new-badge"><?= $unreadNotifications ?> new</span>
            <?php endif; ?>
          </span>
          <a href="notifications.php" class="notif-view-all">
            View All <i class="bi bi-arrow-right"></i>
          </a>
        </div>

        <?php
        // ── Type config: [avatarClass, badgeClass, icon, label, tab]
        $ncTypes = [
          'success' => ['nc-avatar-green',  'nc-badge-green',  'bi-patch-check-fill',          'Approved',    'system'],
          'error'   => ['nc-avatar-red',    'nc-badge-red',    'bi-x-octagon-fill',             'Alert',       'system'],
          'warning' => ['nc-avatar-orange', 'nc-badge-orange', 'bi-exclamation-triangle-fill',  'Eligibility', 'system'],
          'info'    => ['nc-avatar-blue',   'nc-badge-blue',   'bi-info-circle-fill',           'Update',      'system'],
          'message' => ['nc-avatar-purple', 'nc-badge-purple', 'bi-chat-dots-fill',             'Message',     'messages'],
          'reply'   => ['nc-avatar-purple', 'nc-badge-purple', 'bi-reply-fill',                 'Message',     'messages'],
        ];
        $ncDefault = ['nc-avatar-blue','nc-badge-blue','bi-bell-fill','Notification','system'];

        // Classify each notification
        $allNotifs     = $latestNotifications;
        $msgNotifs     = array_filter($allNotifs, fn($n) => in_array($n['type'],['message','reply']));
        $sysNotifs     = array_filter($allNotifs, fn($n) => !in_array($n['type'],['message','reply']));
        $msgCount      = count($msgNotifs);
        $sysCount      = count($sysNotifs);
        ?>

        <!-- Pill tabs (reference-style: All · Unread · Updates) -->
        <div class="notif-tabs">
          <button class="notif-tab active" onclick="switchNotifTab2('all',this)">All</button>
          <button class="notif-tab" onclick="switchNotifTab2('unread',this)">
            Unread
            <?php if ($unreadNotifications > 0): ?>
              <span class="notif-tab-count"><?= $unreadNotifications ?></span>
            <?php endif; ?>
          </button>
          <button class="notif-tab" onclick="switchNotifTab2('messages',this)">
            Messages
            <?php if ($msgCount > 0): ?>
              <span class="notif-tab-count"><?= $msgCount ?></span>
            <?php endif; ?>
          </button>
          <button class="notif-tab" onclick="switchNotifTab2('system',this)">Updates</button>
        </div>

        <?php
        // Helper to render a single notification card
        function renderNotifCard(array $notif, array $ncTypes, array $ncDefault): void {
            $tc  = $ncTypes[$notif['type']] ?? $ncDefault;
            $unread = !(bool)$notif['is_read'];
            $time = strtotime($notif['created_at']);
            $now  = time();
            $diff = $now - $time;
            if ($diff < 60)         $timeStr = 'Just now';
            elseif ($diff < 3600)   $timeStr = (int)($diff/60) . 'm ago';
            elseif ($diff < 86400)  $timeStr = (int)($diff/3600) . 'h ago';
            elseif ($diff < 604800) $timeStr = (int)($diff/86400) . 'd ago';
            else                    $timeStr = date('d M', $time);
            ?>
            <div class="notif-card <?= $unread ? 'nc-unread' : '' ?>">
              <!-- Avatar -->
              <div class="nc-avatar <?= $tc[0] ?>">
                <i class="bi <?= $tc[2] ?>"></i>
              </div>
              <!-- Body -->
              <div class="nc-body">
                <div class="nc-top">
                  <div class="nc-title <?= $unread ? 'nc-bold' : '' ?>">
                    <?= e($notif['title']) ?>
                  </div>
                  <div class="nc-right">
                    <?php if ($unread): ?>
                      <span class="nc-dot"></span>
                    <?php endif; ?>
                    <span class="nc-time"><?= $timeStr ?></span>
                  </div>
                </div>
                <div class="nc-preview"><?= e($notif['message']) ?></div>
                <div class="nc-footer">
                  <span class="nc-badge <?= $tc[1] ?>"><?= $tc[3] ?></span>
                </div>
              </div>
            </div>
        <?php } ?>

        <!-- TAB: All -->
        <div id="nc-tab-all" class="notif-list">
          <?php if (empty($allNotifs)): ?>
            <div class="nc-empty">
              <i class="bi bi-bell-slash nc-empty-icon"></i>
              <div class="nc-empty-title">All clear!</div>
              <div class="nc-empty-sub">No notifications yet.</div>
            </div>
          <?php else: foreach ($allNotifs as $notif) renderNotifCard($notif, $ncTypes, $ncDefault); endif; ?>
        </div>

        <!-- TAB: Unread -->
        <div id="nc-tab-unread" class="notif-list" style="display:none;">
          <?php if (empty($unreadNotifList)): ?>
            <div class="nc-empty">
              <i class="bi bi-check-circle nc-empty-icon"></i>
              <div class="nc-empty-title">All caught up!</div>
              <div class="nc-empty-sub">No unread notifications.</div>
            </div>
          <?php else: foreach ($unreadNotifList as $notif) renderNotifCard($notif, $ncTypes, $ncDefault); endif; ?>
        </div>

        <!-- TAB: Messages -->
        <div id="nc-tab-messages" class="notif-list" style="display:none;">
          <?php if (empty($msgNotifs)): ?>
            <div class="nc-empty">
              <i class="bi bi-chat-dots nc-empty-icon"></i>
              <div class="nc-empty-title">No messages</div>
              <div class="nc-empty-sub">Message notifications appear here.</div>
            </div>
          <?php else: foreach ($msgNotifs as $notif) renderNotifCard($notif, $ncTypes, $ncDefault); endif; ?>
        </div>

        <!-- TAB: System -->
        <div id="nc-tab-system" class="notif-list" style="display:none;">
          <?php if (empty($sysNotifs)): ?>
            <div class="nc-empty">
              <i class="bi bi-cpu nc-empty-icon"></i>
              <div class="nc-empty-title">No system updates</div>
              <div class="nc-empty-sub">Application and eligibility updates appear here.</div>
            </div>
          <?php else: foreach ($sysNotifs as $notif) renderNotifCard($notif, $ncTypes, $ncDefault); endif; ?>
        </div>

        <!-- Footer -->
        <div class="notif-footer">
          <a href="notifications.php" class="notif-footer-btn">
            <i class="bi bi-bell"></i> View all notifications
          </a>
        </div>

      </div>
    </div>

  </div><!-- /row -->


</div><!-- /stu-canvas -->

<script>
function switchNotifTab2(tab, btn) {
  ['all','unread','messages','system'].forEach(t => {
    const el = document.getElementById('nc-tab-' + t);
    if (el) el.style.display = 'none';
  });
  document.querySelectorAll('.notif-tab').forEach(b => b.classList.remove('active'));
  const pane = document.getElementById('nc-tab-' + tab);
  if (pane) pane.style.display = 'block';
  btn.classList.add('active');
}
</script>
</div>

<?php require_once '../includes/footer.php'; ?>
