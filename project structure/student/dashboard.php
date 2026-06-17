<?php
$pageTitle = 'Student Dashboard';

require_once '../config/db.php';
require_once '../includes/auth.php';

requireLogin();
requireRole('student');

require_once '../includes/header.php';
require_once '../includes/navbar.php';

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
$draftCount = (int)$pdo->prepare(
    "SELECT COUNT(*) FROM applications WHERE student_id=? AND status='draft'"
)->execute([$studentId]) ? (int)$pdo->query(
    "SELECT COUNT(*) FROM applications WHERE student_id=$studentId AND status='draft'"
)->fetchColumn() : 0;
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
$stmtNotif = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmtNotif->execute([$studentId]);
$latestNotifications = $stmtNotif->fetchAll();

// ═══════════════════════════════════════════════════════════════
// FEATURE 3 – Scholarship Matching
// Match open programs against student profile, rank by fit score
// ═══════════════════════════════════════════════════════════════
$matchedPrograms = [];
if ($profile) {
    // Fetch all open programs the student hasn't formally applied to
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

    // Load all rules for candidate programs in one query
    if (!empty($candidatePrograms)) {
        $progIds  = array_column($candidatePrograms, 'id');
        $inClause = implode(',', array_fill(0, count($progIds), '?'));
        $stmtRules = $pdo->prepare(
            "SELECT * FROM eligibility_rules WHERE program_id IN ($inClause)"
        );
        $stmtRules->execute($progIds);
        $allRules = $stmtRules->fetchAll();

        // Map rules per program
        $rulesPerProg = [];
        foreach ($allRules as $r) {
            $rulesPerProg[$r['program_id']][] = $r;
        }

        // Profile values map
        $profileMap = [
            'gpa'                  => (float)$profile['gpa'],
            'activities'           => (int)$profile['activities_count'],
            'activities_count'     => (int)$profile['activities_count'],
            'failed_subjects'      => (int)$profile['failed_subjects'],
            'research_projects'    => (int)($profile['research_count'] ?? 0),
            'research_count'       => (int)($profile['research_count'] ?? 0),
            'language_certificate' => (int)($profile['language_certificate'] ?? 0),
            'family_income'        => (float)($profile['family_income'] ?? 0),
        ];

        foreach ($candidatePrograms as $prog) {
            $rules       = $rulesPerProg[$prog['id']] ?? [];
            $passedCount = 0;
            $totalCount  = count($rules);
            $failedRules = [];

            foreach ($rules as $rule) {
                $type = $rule['rule_type'];
                $myVal = $profileMap[$type] ?? null;
                if ($myVal === null) { $passedCount++; continue; } // unknown rule → skip

                $req = (float)$rule['value'];
                $ok  = match($rule['operator']) {
                    '>='  => $myVal >= $req,
                    '>'   => $myVal >  $req,
                    '<='  => $myVal <= $req,
                    '<'   => $myVal <  $req,
                    '='   => $myVal == $req,
                    default => false,
                };
                if ($ok) $passedCount++;
                else $failedRules[] = $rule;
            }

            $fitPct = $totalCount > 0
                ? (int)round($passedCount / $totalCount * 100)
                : 100; // no rules = open to all

            $fullyEligible = empty($failedRules);

            $matchedPrograms[] = array_merge($prog, [
                'fit_pct'        => $fitPct,
                'passed_count'   => $passedCount,
                'total_rules'    => $totalCount,
                'failed_rules'   => $failedRules,
                'fully_eligible' => $fullyEligible,
            ]);
        }

        // Sort: fully eligible first, then by fit percentage DESC
        usort($matchedPrograms, function($a, $b) {
            if ($a['fully_eligible'] !== $b['fully_eligible'])
                return $b['fully_eligible'] <=> $a['fully_eligible'];
            return $b['fit_pct'] <=> $a['fit_pct'];
        });

        // Show top 4
        $matchedPrograms = array_slice($matchedPrograms, 0, 4);
    }
}

$stmtNotif->execute([$studentId]);
$latestNotifications = $stmtNotif->fetchAll();
?>

<!-- ═══════════════════════════════════════════════════════════
     DASHBOARD CUSTOM STYLES
════════════════════════════════════════════════════════════ -->
<style>
/* ── Page background ──────────────────────────────────────── */
.page-body { background:#F8FAFC; }

/* ── Page header greeting ─────────────────────────────────── */
.dash-greeting { font-size:22px; font-weight:800; color:#0F172A; margin:0 0 4px; }
.dash-greeting-sub { font-size:13.5px; color:#64748b; margin:0; }

/* ══ STAT CARDS ════════════════════════════════════════════ */
.scard {
  background:#fff;
  border:1px solid #E2E8F0;
  border-radius:16px;
  padding:22px 20px;
  display:flex;
  align-items:center;
  gap:16px;
  box-shadow:0 1px 4px rgba(15,23,42,.05), 0 4px 16px rgba(15,23,42,.04);
  transition:transform .2s, box-shadow .2s;
  overflow:hidden;
  position:relative;
}
.scard::before {
  content:'';
  position:absolute;
  top:0; left:0; right:0;
  height:3px;
  border-radius:16px 16px 0 0;
  background:var(--scard-accent, #1D4ED8);
}
.scard:hover { transform:translateY(-4px); box-shadow:0 8px 28px rgba(15,23,42,.10); }
.scard-icon {
  width:52px; height:52px; border-radius:14px;
  display:flex; align-items:center; justify-content:center;
  font-size:22px; flex-shrink:0;
}
.scard-icon.blue   { background:#EFF6FF; color:#1D4ED8; --scard-accent:#1D4ED8; }
.scard-icon.green  { background:#F0FDF4; color:#16A34A; --scard-accent:#16A34A; }
.scard-icon.amber  { background:#FFFBEB; color:#D97706; --scard-accent:#D97706; }
.scard-icon.violet { background:#F5F3FF; color:#7C3AED; --scard-accent:#7C3AED; }
.scard-icon.cyan   { background:#ECFEFF; color:#0891B2; --scard-accent:#0891B2; }
.scard-label {
  font-size:11px; font-weight:700; text-transform:uppercase;
  letter-spacing:.07em; color:#94A3B8; margin-bottom:4px;
}
.scard-value {
  font-size:30px; font-weight:800; color:#0F172A; line-height:1;
  font-variant-numeric:tabular-nums;
}
.scard-sub { font-size:12px; color:#94A3B8; margin-top:5px; }
/* Pass accent into ::before via parent */
.scard:has(.scard-icon.blue)   { --scard-accent:#1D4ED8; }
.scard:has(.scard-icon.green)  { --scard-accent:#16A34A; }
.scard:has(.scard-icon.amber)  { --scard-accent:#D97706; }
.scard:has(.scard-icon.violet) { --scard-accent:#7C3AED; }
.scard:has(.scard-icon.cyan)   { --scard-accent:#0891B2; }

/* ══ QUICK ACTIONS ═════════════════════════════════════════ */
.qa-grid {
  display:flex; flex-wrap:wrap; gap:10px;
}
.qa-item {
  display:inline-flex; align-items:center; gap:8px;
  padding:9px 16px; border-radius:10px;
  background:#F8FAFC; border:1.5px solid #E2E8F0;
  color:#334155; font-size:13px; font-weight:600;
  text-decoration:none; transition:all .18s;
  white-space:nowrap;
}
.qa-item i { color:#1D4ED8; font-size:15px; }
.qa-item:hover {
  background:#EFF6FF; border-color:#BFDBFE;
  color:#1D4ED8; transform:translateY(-1px);
  box-shadow:0 4px 12px rgba(29,78,216,.12);
}
.qa-item .qa-badge {
  background:#1D4ED8; color:#fff;
  border-radius:20px; padding:1px 8px;
  font-size:10px; font-weight:700;
}
.qa-item .qa-badge-amber {
  background:#F59E0B; color:#fff;
  border-radius:20px; padding:1px 8px;
  font-size:10px; font-weight:700;
}

/* ══ SECTION HEADER ════════════════════════════════════════ */
.section-header {
  display:flex; align-items:center; justify-content:space-between;
  flex-wrap:wrap; gap:10px; margin-bottom:18px;
}
.section-title {
  font-size:15px; font-weight:800; color:#0F172A; margin:0;
  display:flex; align-items:center; gap:8px;
}
.section-title-icon {
  width:32px; height:32px; border-radius:8px;
  display:inline-flex; align-items:center; justify-content:center;
  font-size:16px;
}

/* ══ MATCH CARDS ═══════════════════════════════════════════ */
.mc {
  background:#fff; border:1.5px solid #DBEAFE;
  border-radius:14px; padding:18px; height:100%;
  display:flex; flex-direction:column;
  transition:all .2s;
  box-shadow:0 1px 4px rgba(29,78,216,.05);
}
.mc:hover {
  border-color:#1D4ED8; transform:translateY(-3px);
  box-shadow:0 8px 24px rgba(29,78,216,.13);
}
.mc-eligible {
  border-color:#86EFAC;
  background:linear-gradient(145deg,#fff 75%,#F0FDF4 100%);
}
.mc-fit-badge {
  border-radius:20px; padding:3px 10px;
  font-size:11.5px; font-weight:800;
  display:inline-block;
}
.mc-fit-badge.green { background:#F0FDF4; color:#16A34A; }
.mc-fit-badge.amber { background:#FFFBEB; color:#D97706; }
.mc-fit-badge.red   { background:#FEF2F2; color:#DC2626; }
.mc-bar-track {
  background:#E2E8F0; border-radius:4px; height:5px;
  overflow:hidden; margin:10px 0 12px;
}
.mc-bar-fill {
  height:100%; border-radius:4px; transition:width .6s ease;
}
.mc-tag {
  font-size:10px; font-weight:700; text-transform:uppercase;
  letter-spacing:.07em; color:#64748b;
}

/* ══ TABLE REDESIGN ════════════════════════════════════════ */
.dash-table-card {
  background:#fff; border:1px solid #E2E8F0;
  border-radius:16px; overflow:hidden;
  box-shadow:0 1px 4px rgba(15,23,42,.05), 0 4px 16px rgba(15,23,42,.04);
}
.dash-table-header {
  display:flex; align-items:center; justify-content:space-between;
  padding:18px 22px; border-bottom:1px solid #F1F5F9;
  background:#FAFBFC; flex-wrap:wrap; gap:10px;
}
.dash-table-title {
  font-size:14.5px; font-weight:700; color:#0F172A;
}
.dash-table table { margin:0; }
.dash-table thead th {
  font-size:10.5px; font-weight:700; text-transform:uppercase;
  letter-spacing:.08em; color:#94A3B8;
  padding:12px 20px; border-bottom:1px solid #F1F5F9;
  background:#FAFBFC; white-space:nowrap;
}
.dash-table tbody td {
  padding:14px 20px; vertical-align:middle;
  border-bottom:1px solid #F8FAFC; font-size:13.5px;
  color:#334155;
}
.dash-table tbody tr:last-child td { border-bottom:none; }
.dash-table tbody tr:hover td { background:#FAFBFF; }
.dash-table .prog-name { font-weight:600; color:#0F172A; font-size:13.5px; }
.dash-table .prog-id   { font-size:11px; color:#CBD5E1; margin-top:2px; }

/* ══ STATUS BADGES (re-styled) ══════════════════════════════ */
.sb {
  display:inline-flex; align-items:center; gap:4px;
  padding:4px 11px; border-radius:20px;
  font-size:11.5px; font-weight:700; letter-spacing:.02em;
}
.sb-submitted  { background:#EFF6FF; color:#1D4ED8; }
.sb-reviewing  { background:#FFFBEB; color:#92400E; }
.sb-eligible   { background:#F0FDF4; color:#166534; }
.sb-ineligible { background:#FEF2F2; color:#991B1B; }
.sb-approved   { background:#F0FDF4; color:#166534; }
.sb-rejected   { background:#FEF2F2; color:#991B1B; }
.sb-draft      { background:#F5F5F4; color:#57534E; }
.sb-pending    { background:#FFFBEB; color:#92400E; }

/* ══ NOTIFICATION PANEL ════════════════════════════════════ */
.notif-panel {
  background:#fff; border:1px solid #E2E8F0; border-radius:16px;
  box-shadow:0 1px 4px rgba(15,23,42,.05), 0 4px 16px rgba(15,23,42,.04);
  overflow:hidden;
}
.notif-panel-header {
  display:flex; align-items:center; justify-content:space-between;
  padding:18px 22px; border-bottom:1px solid #F1F5F9;
  background:#FAFBFC;
}
.notif-panel-title {
  font-size:14.5px; font-weight:700; color:#0F172A;
  display:flex; align-items:center; gap:8px;
}
.notif-item {
  display:flex; gap:12px;
  padding:13px 20px; border-bottom:1px solid #F8FAFC;
  transition:background .15s;
}
.notif-item:last-child { border-bottom:none; }
.notif-item:hover { background:#FAFBFF; }
.notif-item.unread { background:#F8FBFF; }
.notif-icon-wrap {
  width:34px; height:34px; border-radius:10px;
  display:flex; align-items:center; justify-content:center;
  flex-shrink:0; font-size:15px;
}
.notif-msg {
  font-size:11.5px; color:#64748b;
  overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
.notif-time { font-size:10.5px; color:#CBD5E1; margin-top:3px; }
.notif-dot {
  width:6px; height:6px; border-radius:50%;
  background:#1D4ED8; display:inline-block;
  margin-left:5px; vertical-align:middle;
}

/* ── Responsive tweaks ──────────────────────────────────────*/
@media(max-width:575px) {
  .scard-value { font-size:24px; }
  .scard-icon  { width:42px; height:42px; font-size:18px; }
}
</style>

<!-- ── Page Header ─────────────────────────────────────────── -->
<div class="page-header mb-4">
  <div class="page-header-left">
    <h1 class="dash-greeting">Welcome back, <?= e(currentUserName()) ?> 👋</h1>
    <p class="dash-greeting-sub">Track your scholarship applications and stay up to date.</p>
  </div>
  <a href="apply.php" class="btn btn-primary">
    <i class="bi bi-file-earmark-plus"></i> Apply for Scholarship
  </a>
</div>

<?php showFlash(); ?>

<?php if (!$profile): ?>
<div class="alert alert-warning mb-4" style="font-size:13px;">
  <i class="bi bi-person-exclamation me-2"></i>
  <strong>Profile incomplete!</strong> Fill in your academic profile so the system can check your eligibility when you apply.
  <a href="profile.php" class="btn btn-sm btn-warning ms-3" style="font-size:12px;">Complete Profile</a>
</div>
<?php endif; ?>

<!-- ══ STAT CARDS ════════════════════════════════════════════ -->
<div class="row g-3 mb-4">

  <div class="col-6 col-sm-3">
    <div class="scard">
      <div class="scard-icon blue"><i class="bi bi-folder2-open"></i></div>
      <div>
        <div class="scard-label">Total Applications</div>
        <div class="scard-value"><?= $totalApplications ?></div>
        <div class="scard-sub">Submitted</div>
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
      <div class="scard-icon <?= $unreadNotifications > 0 ? 'amber' : 'cyan' ?>">
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
    <div class="section-header mb-3" style="margin-bottom:14px!important;">
      <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#CBD5E1;">
        Quick Actions
      </span>
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

      <a href="my_applications.php?tab=draft" class="qa-item">
        <i class="bi bi-pencil-square"></i> Drafts
        <?php if ($draftCount > 0): ?>
          <span class="qa-badge-amber"><?= $draftCount ?></span>
        <?php endif; ?>
      </a>

      <a href="document_wallet.php" class="qa-item">
        <i class="bi bi-folder2-open"></i> Document Wallet
      </a>

    </div>
  </div>
</div>

<!-- ══ FEATURE 3 – GỢI Ý HỌC BỔNG ══════════════════════════════ -->
<?php if ($profile && !empty($matchedPrograms)): ?>
<div class="card mb-4" style="border-radius:16px;border:1px solid #E2E8F0;
     box-shadow:0 1px 4px rgba(15,23,42,.05);overflow:hidden;">

  <!-- Header -->
  <div style="padding:20px 24px 0;border-bottom:1px solid #F1F5F9;padding-bottom:18px;
              background:linear-gradient(135deg,#EFF6FF 0%,#F8FAFC 100%);">
    <div class="section-header">
      <div>
        <div class="section-title">
          <span class="section-title-icon" style="background:#FEF3C7;">⭐</span>
          Scholarship Recommendations for You
        </div>
        <div style="font-size:12.5px;color:#64748b;margin-top:5px;margin-left:40px;">
          Based on your profile — GPA
          <strong style="color:#1D4ED8;"><?= $profile['gpa'] ?></strong>,
          <?= $profile['activities_count'] ?> activities,
          <?= $profile['research_count']??0 ?> research projects
        </div>
      </div>
      <a href="scholarships.php" class="btn btn-sm btn-outline-primary" style="border-radius:8px;">
        View All <i class="bi bi-arrow-right ms-1"></i>
      </a>
    </div>
  </div>

  <!-- Cards -->
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

        <!-- Top row: eligibility tag + fit badge -->
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="mc-tag <?= $mp['fully_eligible'] ? 'text-success' : '' ?>">
            <?php if ($mp['fully_eligible']): ?>
              <i class="bi bi-check-circle-fill text-success me-1"></i>Eligible
            <?php else: ?>
              <i class="bi bi-exclamation-circle-fill text-warning me-1"></i>Nearly eligible
            <?php endif; ?>
          </span>
          <span class="mc-fit-badge <?= $fitClass ?>">
            <?= $mp['fit_pct'] ?>%
          </span>
        </div>

        <!-- Program name -->
        <div style="font-size:13.5px;font-weight:700;color:#0F172A;
                    line-height:1.35;min-height:38px;margin-bottom:4px;">
          <?= e($mp['name']) ?>
        </div>

        <!-- Fit bar -->
        <div class="mc-bar-track">
          <div class="mc-bar-fill"
               style="width:<?= $mp['fit_pct'] ?>%;background:<?= $barColor ?>;"></div>
        </div>

        <!-- Meta row -->
        <div class="d-flex justify-content-between mb-2"
             style="font-size:11.5px;color:#64748b;">
          <span><i class="bi bi-people me-1"></i><?= $mp['slots'] ?> slots</span>
          <?php if ($daysLeft !== null && $daysLeft >= 0): ?>
          <span style="color:<?= $urgentDay ? '#DC2626' : '#64748b' ?>;font-weight:<?= $urgentDay ? '700' : '400' ?>;">
            <i class="bi bi-clock me-1"></i><?= $daysLeft ?> days left
          </span>
          <?php endif; ?>
        </div>

        <!-- Failed rules (compact) -->
        <?php if (!empty($mp['failed_rules'])): ?>
        <div style="background:#FEF2F2;border-radius:8px;padding:8px 10px;margin-bottom:10px;">
          <?php
          $ruleNames = ['gpa'=>'GPA','activities_count'=>'Activities',
            'activities'=>'Activities','failed_subjects'=>'Failed Subjects',
            'research_count'=>'Research Projects','research_projects'=>'Research Projects',
            'language_certificate'=>'Language Certificate',
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

        <!-- Budget chip -->
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

<div class="row g-4">
  <!-- ── Recent Applications ─────────────────────────────── -->
  <div class="col-lg-7">
    <?php if (empty($recentApplications)): ?>
    <div class="dash-table-card" style="border-radius:16px;">
      <div class="card-body">
        <div class="empty-state">
          <div class="empty-state-icon"><i class="bi bi-folder-x"></i></div>
          <div class="empty-state-title">No Applications Yet</div>
          <div class="empty-state-text">
            Start by browsing available scholarships and submit your first application.
          </div>
          <a href="scholarships.php" class="btn btn-primary" style="border-radius:10px;">
            <i class="bi bi-award"></i> Browse Scholarships
          </a>
        </div>
      </div>
    </div>

    <?php else: ?>
    <div class="dash-table-card">
      <div class="dash-table-header">
        <span class="dash-table-title">
          <i class="bi bi-folder2-open me-2 text-primary"></i>Recent Applications
        </span>
        <a href="my_applications.php" class="btn btn-sm btn-outline-primary"
           style="border-radius:8px;font-size:12.5px;">
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

  <!-- ── Notifications Panel ────────────────────────────── -->
  <div class="col-lg-5">
    <div class="notif-panel">

      <div class="notif-panel-header">
        <span class="notif-panel-title">
          <span style="width:28px;height:28px;border-radius:8px;background:#EFF6FF;
                       display:inline-flex;align-items:center;justify-content:center;font-size:13px;">
            <i class="bi bi-bell-fill text-primary"></i>
          </span>
          Notifications
          <?php if ($unreadNotifications > 0): ?>
          <span class="sb sb-submitted" style="font-size:11px;">
            <?= $unreadNotifications ?> new
          </span>
          <?php endif; ?>
        </span>
        <a href="notifications.php"
           style="font-size:12px;color:#1D4ED8;text-decoration:none;font-weight:600;">
          Xem tất cả
        </a>
      </div>

      <?php if (empty($latestNotifications)): ?>
        <div style="text-align:center;padding:40px 20px;color:#94A3B8;font-size:13px;">
          <i class="bi bi-bell-slash"
             style="font-size:32px;display:block;margin-bottom:10px;opacity:.4;"></i>
          No notifications yet.
        </div>
      <?php else: ?>
        <?php
        $typeConfig = [
          'success' => ['#F0FDF4','#16A34A','#DCFCE7','bi-check-circle-fill'],
          'error'   => ['#FEF2F2','#DC2626','#FEE2E2','bi-x-circle-fill'],
          'warning' => ['#FFFBEB','#D97706','#FEF3C7','bi-exclamation-triangle-fill'],
          'info'    => ['#EFF6FF','#1D4ED8','#DBEAFE','bi-info-circle-fill'],
        ];
        foreach ($latestNotifications as $notif):
          $tc = $typeConfig[$notif['type']] ?? $typeConfig['info'];
        ?>
        <div class="notif-item <?= !$notif['is_read'] ? 'unread' : '' ?>">
          <div class="notif-icon-wrap"
               style="background:<?= $tc[2] ?>;color:<?= $tc[1] ?>;">
            <i class="bi <?= $tc[3] ?>"></i>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:13px;
                        font-weight:<?= $notif['is_read'] ? '500' : '700' ?>;
                        color:#0F172A;margin-bottom:2px;
                        display:flex;align-items:center;gap:4px;">
              <?= e($notif['title']) ?>
              <?php if (!$notif['is_read']): ?>
                <span class="notif-dot"></span>
              <?php endif; ?>
            </div>
            <div class="notif-msg">
              <?= e(mb_strimwidth($notif['message'], 0, 80, '…')) ?>
            </div>
            <div class="notif-time">
              <i class="bi bi-clock me-1"></i>
              <?= date('d/m, H:i', strtotime($notif['created_at'])) ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>

        <div style="padding:14px 20px;">
          <a href="notifications.php"
             class="btn btn-secondary w-100 btn-sm"
             style="border-radius:9px;font-size:13px;">
            Xem tất cả thông báo
          </a>
        </div>
      <?php endif; ?>

    </div>
  </div>

</div>

<?php require_once '../includes/footer.php'; ?>
