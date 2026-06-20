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
      <style>
}
.scard-value {
  font-size: 32px; font-weight: 800; color: #0F172A; line-height: 1;
  font-variant-numeric: tabular-nums;
}
.scard-sub { font-size: 12px; color: #94A3B8; margin-top: 5px; }
.scard:has(.scard-icon.blue)   { --scard-accent: #1D4ED8; }
.scard:has(.scard-icon.green)  { --scard-accent: #16A34A; }
.scard:has(.scard-icon.amber)  { --scard-accent: #D97706; }
.scard:has(.scard-icon.violet) { --scard-accent: #7C3AED; }
.scard:has(.scard-icon.red)    { --scard-accent: #DC2626; }

/* ══ QUICK ACTIONS ═════════════════════════════════════════ */
.qa-grid {
  display: flex; flex-wrap: wrap; gap: 10px;
}
.qa-item {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 10px 18px; border-radius: 10px;
  background: #F8FAFC; border: 1.5px solid #E2E8F0;
  color: #334155; font-size: 13px; font-weight: 600;
  text-decoration: none; transition: all .18s;
  white-space: nowrap;
}
.qa-item i { color: #1D4ED8; font-size: 15px; }
.qa-item:hover {
  background: #EFF6FF; border-color: #BFDBFE;
  color: #1D4ED8; transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(29,78,216,.12);
}
.qa-item .qa-badge {
  background: #1D4ED8; color: #fff;
  border-radius: 20px; padding: 1px 8px;
  font-size: 10px; font-weight: 700;
}
.qa-item .qa-badge-amber {
  background: #F59E0B; color: #fff;
  border-radius: 20px; padding: 1px 8px;
  font-size: 10px; font-weight: 700;
}

/* ══ SECTION HEADER ════════════════════════════════════════ */
.section-header {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 10px; margin-bottom: 18px;
}
.section-title {
  font-size: 15px; font-weight: 800; color: #0F172A; margin: 0;
  display: flex; align-items: center; gap: 8px;
}
.section-title-icon {
  width: 32px; height: 32px; border-radius: 8px;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 16px;
}

/* ══ MATCH CARDS ═══════════════════════════════════════════ */
.mc {
  background: #fff; border: 1.5px solid #DBEAFE;
  border-radius: 14px; padding: 18px; height: 100%;
  display: flex; flex-direction: column;
  transition: all .2s;
  box-shadow: 0 1px 4px rgba(29,78,216,.05);
}
.mc:hover {
  border-color: #1D4ED8; transform: translateY(-3px);
  box-shadow: 0 8px 24px rgba(29,78,216,.13);
}
.mc-eligible {
  border-color: #86EFAC;
  background: linear-gradient(145deg, #fff 75%, #F0FDF4 100%);
}
.mc-fit-badge {
  border-radius: 20px; padding: 3px 10px;
  font-size: 11.5px; font-weight: 800;
  display: inline-block;
}
.mc-fit-badge.green { background: #F0FDF4; color: #16A34A; }
.mc-fit-badge.amber { background: #FFFBEB; color: #D97706; }
.mc-fit-badge.red   { background: #FEF2F2; color: #DC2626; }
.mc-bar-track {
  background: #E2E8F0; border-radius: 4px; height: 5px;
  overflow: hidden; margin: 10px 0 12px;
}
.mc-bar-fill { height: 100%; border-radius: 4px; transition: width .6s ease; }
.mc-tag {
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .07em; color: #64748b;
}

/* ══ TABLE REDESIGN ════════════════════════════════════════ */
.dash-table-card {
  background: #fff; border: 1px solid #E2E8F0;
  border-radius: 16px; overflow: hidden;
  box-shadow: 0 1px 4px rgba(15,23,42,.05), 0 4px 16px rgba(15,23,42,.04);
}
.dash-table-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 18px 22px; border-bottom: 1px solid #F1F5F9;
  background: #FAFBFC; flex-wrap: wrap; gap: 10px;
}
.dash-table-title { font-size: 14.5px; font-weight: 700; color: #0F172A; }
.dash-table table { margin: 0; }
.dash-table thead th {
  font-size: 10.5px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .08em; color: #94A3B8;
  padding: 12px 20px; border-bottom: 1px solid #F1F5F9;
  background: #FAFBFC; white-space: nowrap;
}
.dash-table tbody td {
  padding: 14px 20px; vertical-align: middle;
  border-bottom: 1px solid #F8FAFC; font-size: 13.5px;
  color: #334155;
}
.dash-table tbody tr:last-child td { border-bottom: none; }
.dash-table tbody tr:hover td { background: #FAFBFF; }
.dash-table .prog-name { font-weight: 600; color: #0F172A; font-size: 13.5px; }
.dash-table .prog-id   { font-size: 11px; color: #CBD5E1; margin-top: 2px; }

/* ══ STATUS BADGES ══════════════════════════════════════════ */
.sb {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 4px 11px; border-radius: 20px;
  font-size: 11.5px; font-weight: 700; letter-spacing: .02em;
}
.sb-submitted  { background: #EFF6FF; color: #1D4ED8; }
.sb-reviewing  { background: #FFFBEB; color: #92400E; }
.sb-eligible   { background: #F0FDF4; color: #166534; }
.sb-ineligible { background: #FEF2F2; color: #991B1B; }
.sb-approved   { background: #F0FDF4; color: #166534; }
.sb-rejected   { background: #FEF2F2; color: #991B1B; }
.sb-draft      { background: #F5F5F4; color: #57534E; }
.sb-pending    { background: #FFFBEB; color: #92400E; }

/* ══ NOTIFICATION PANEL — Modern SaaS ═════════════════════ */
.notif-panel {
  background: #fff;
  border: 1px solid #E8EDF5;
  border-radius: 20px;
  box-shadow:
    0 0 0 1px rgba(15,23,42,.03),
    0 2px 8px rgba(15,23,42,.06),
    0 12px 32px rgba(15,23,42,.07);
  overflow: hidden;
}


/* ── Header row ───────────────────────────────────────────── */
.notif-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 22px 16px;
  background: #fff;
  border-bottom: 1px solid #F1F5F9;
}
.notif-panel-title {
  font-size: 15px; font-weight: 800; color: #0F172A;
  display: flex; align-items: center; gap: 10px;
  letter-spacing: -.01em;
}
.notif-bell-wrap {
  width: 32px; height: 32px; border-radius: 9px;
  background: #EFF6FF;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 14px; flex-shrink: 0;
}
.notif-new-badge {
  background: #1558d6; color: #fff;
  border-radius: 99px; padding: 2px 9px;
  font-size: 10.5px; font-weight: 700;
}
.notif-view-all {
  font-size: 12.5px; color: #1558d6; text-decoration: none;
  font-weight: 600; display: flex; align-items: center; gap: 4px;
  transition: all .15s;
}
.notif-view-all:hover { color: #1246b5; }

/* ── Tabs (reference image style) ────────────────────────── */
.notif-tabs {
  display: flex;
  gap: 8px;
  padding: 14px 20px 12px;
  border-bottom: 1px solid #F1F5F9;
  overflow-x: auto;
  scrollbar-width: none;
}
.notif-tabs::-webkit-scrollbar { display: none; }
.notif-tab {
  padding: 4px 14px;
  font-size: 12.5px; font-weight: 600;
  color: #64748B;
  border: 1.5px solid #E2E8F0;
  background: #fff;
  border-radius: 99px;
  cursor: pointer;
  transition: all .15s;
  white-space: nowrap;
  display: flex; align-items: center; gap: 5px;
  line-height: 1.6;
}
.notif-tab:hover {
  color: #1558d6; background: #EFF6FF; border-color: #BFDBFE;
}
.notif-tab.active {
  color: #1558d6;
  background: #EFF6FF;
  border-color: #1558d6;
  font-weight: 700;
}
.notif-tab-count {
  background: #ef4444; color: #fff;
  border-radius: 99px; padding: 1px 6px;
  font-size: 10px; font-weight: 700;
  line-height: 1.5;
}



/* ── Notification list ────────────────────────────────────── */
.notif-list {
  max-height: 360px;
  overflow-y: auto;
  padding: 8px 0;
}
.notif-list::-webkit-scrollbar { width: 4px; }
.notif-list::-webkit-scrollbar-thumb { background: #E2E8F0; border-radius: 4px; }

/* ── Individual notification card ────────────────────────── */
.notif-card {
  display: flex;
  align-items: flex-start;
  gap: 13px;
  padding: 13px 18px 13px 14px;
  margin: 3px 10px;
  border-radius: 12px;
  border-left: 3px solid transparent;
  cursor: pointer;
  transition: background .14s, transform .14s, box-shadow .14s;
  position: relative;
}
.notif-card:hover {
  background: #F5F8FF;
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(29,78,216,.08);
}
.notif-card.nc-unread {
  background: #F0F6FF;
  border-left-color: #3B82F6;
}
.notif-card.nc-unread:hover {
  background: #E5EFFF;
  box-shadow: 0 4px 14px rgba(29,78,216,.12);
}

/* Avatar */
.nc-avatar {
  width: 44px; height: 44px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; flex-shrink: 0;
  box-shadow: 0 1px 4px rgba(0,0,0,.08);
}
.nc-avatar-blue   { background: linear-gradient(135deg,#DBEAFE,#BFDBFE); color: #1D4ED8; }
.nc-avatar-green  { background: linear-gradient(135deg,#DCFCE7,#BBF7D0); color: #16A34A; }
.nc-avatar-orange { background: linear-gradient(135deg,#FEF3C7,#FDE68A); color: #D97706; }
.nc-avatar-red    { background: linear-gradient(135deg,#FEE2E2,#FECACA); color: #DC2626; }
.nc-avatar-purple { background: linear-gradient(135deg,#EDE9FE,#DDD6FE); color: #7C3AED; }

/* Body */
.nc-body { flex: 1; min-width: 0; }
.nc-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 6px;
  margin-bottom: 3px;
}
.nc-title {
  font-size: 13px; font-weight: 600; color: #1E293B;
  line-height: 1.35;
  white-space: normal;
  word-break: break-word;
}
.nc-title.nc-bold { font-weight: 800; color: #0F172A; }
.nc-right {
  display: flex; flex-direction: column; align-items: flex-end;
  gap: 5px; flex-shrink: 0;
}
.nc-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: #3B82F6;
  box-shadow: 0 0 0 2px rgba(59,130,246,.2);
  flex-shrink: 0;
}
.nc-time {
  font-size: 10.5px; color: #94A3B8; white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
.nc-preview {
  font-size: 12px; color: #64748B;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.5;
  margin-bottom: 6px;
}
.nc-footer {
  display: flex; align-items: center; gap: 6px; margin-top: 2px;
}
.nc-badge {
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .05em; padding: 2px 8px; border-radius: 99px;
  display: inline-block;
}
.nc-badge-blue   { background: #DBEAFE; color: #1D4ED8; }
.nc-badge-green  { background: #DCFCE7; color: #16A34A; }
.nc-badge-orange { background: #FEF3C7; color: #D97706; }
.nc-badge-red    { background: #FEE2E2; color: #DC2626; }
.nc-badge-purple { background: #EDE9FE; color: #7C3AED; }

/* Empty state */
.nc-empty {
  text-align: center;
  padding: 44px 24px;
  color: #94A3B8;
}
.nc-empty-icon {
  font-size: 38px; display: block;
  margin-bottom: 12px; opacity: .35;
}
.nc-empty-title { font-size: 14px; font-weight: 700; color: #CBD5E1; margin-bottom: 4px; }
.nc-empty-sub   { font-size: 12.5px; }

/* ── Footer ───────────────────────────────────────────────── */
.notif-footer {
  padding: 14px 18px;
  border-top: 1px solid #F1F5F9;
  background: #FAFBFC;
}
.notif-footer-btn {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  width: 100%; padding: 9px;
  border-radius: 10px;
  background: #fff;
  border: 1.5px solid #E2E8F0;
  color: #475569;
  font-size: 13px; font-weight: 600;
  text-decoration: none;
  transition: all .15s;
}
.notif-footer-btn:hover {
  background: #EFF6FF;
  border-color: #BFDBFE;
  color: #1D4ED8;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(29,78,216,.1);
}

/* ── Responsive ─────────────────────────────────────────────*/
@media (max-width: 767px) {
  .stu-canvas { padding: 16px; }
  .stu-welcome { flex-direction: column; align-items: flex-start; gap: 14px; padding: 20px 20px; }
  .stu-welcome-title { font-size: 18px; }
  .stu-welcome-cta { align-self: stretch; justify-content: center; }
  .scard-value { font-size: 26px; }
  .scard-icon { width: 44px; height: 44px; font-size: 18px; }
  .stu-header-top { padding: 10px 16px; }
  .stu-nav { padding: 0 6px; gap: 2px; }
  .stu-nav-link { padding: 8px 10px; font-size: 12px; gap: 4px; }
  .stu-role-badge { display: none; }
}
@media (max-width: 575px) {
  .stu-nav-link span.nav-label { display: none; }
}
/* ===== DASHBOARD STAT CARDS ===== */

.scard{
    background:#fff;
    border-radius:18px;
    padding:24px;
    min-height:120px;

    display:flex;
    align-items:center;
    gap:18px;

    border:1px solid #E2E8F0;
    box-shadow:0 4px 16px rgba(15,23,42,.06);

    transition:.2s ease;
}

.scard:hover{
    transform:translateY(-3px);
    box-shadow:0 10px 24px rgba(15,23,42,.10);
}

.scard-icon{
    width:58px;
    height:58px;
    border-radius:16px;

    display:flex;
    align-items:center;
    justify-content:center;

    flex-shrink:0;
    font-size:24px;
}

.scard-icon.blue{
    background:#EFF6FF;
    color:#2563EB;
}

.scard-icon.amber{
    background:#FEF3C7;
    color:#D97706;
}

.scard-icon.green{
    background:#DCFCE7;
    color:#16A34A;
}

.scard-icon.red{
    background:#FEE2E2;
    color:#DC2626;
}

.scard-icon.violet{
    background:#EDE9FE;
    color:#7C3AED;
}

.scard-label{
    font-size:12px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.08em;
    color:#94A3B8;
    margin-bottom:6px;
}

.scard-value{
    font-size:42px;
    font-weight:800;
    line-height:1;
    color:#0F172A;
    margin-bottom:6px;
}

.scard-sub{
    font-size:14px;
    color:#64748B;
}
/* ===== HERO WELCOME ===== */

.welcome-hero{
    margin:32px 0 28px;
    padding:32px 40px;

    background:linear-gradient(
        135deg,
        #1D4ED8 0%,
        #2563EB 50%,
        #3B82F6 100%
    );

    border-radius:24px;

    display:flex;
    align-items:center;
    justify-content:space-between;

    color:#fff;
    overflow:hidden;
    position:relative;

    box-shadow:0 10px 30px rgba(37,99,235,.20);
}

.welcome-badge{
    display:inline-block;

    background:rgba(255,255,255,.15);
    backdrop-filter:blur(8px);

    padding:8px 14px;
    border-radius:999px;

    font-size:12px;
    font-weight:700;
    letter-spacing:.04em;

    margin-bottom:14px;
}

.welcome-title{
    font-size:38px;
    font-weight:800;
    line-height:1.2;
    margin:0 0 10px;
}

.welcome-subtitle{
    margin:0;
    max-width:650px;

    font-size:16px;
    color:rgba(255,255,255,.85);
}

.welcome-icon{
    font-size:90px;
    opacity:.15;
}

@media(max-width:768px){

    .welcome-hero{
        flex-direction:column;
        text-align:center;
        padding:28px;
    }

    .welcome-icon{
        margin-top:18px;
        font-size:70px;
    }

    .welcome-title{
        font-size:28px;
    }
}
.dashboard-container{
    max-width: 1500px;
    margin: 0 auto;
    padding: 32px;
}
</style>

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
  <div class="welcome-hero">
    <div class="welcome-content">
        <div class="welcome-badge">
            🎓 Student Dashboard
        </div>

        <h1 class="welcome-title">
            Welcome back, <?= e(currentUserName()) ?> 👋
        </h1>

        <p class="welcome-subtitle">
            Track your scholarship applications, monitor progress,
            and discover new opportunities tailored to your profile.
        </p>
    </div>

    <div class="welcome-icon">
        <i class="bi bi-mortarboard-fill"></i>
    </div>
</div>

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
