<?php
// ============================================================
// student/application_details.php
// View application progress, evaluation scores, and outcomes
// ============================================================

$pageTitle = 'Application Details';

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requireRole('student');

require_once __DIR__ . '/../includes/header.php';
//require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/student_header.php';

$pdo = getDB();
$studentId = currentUserId();
$appId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* ============================================================
   FETCH APPLICATION DETAILS
   ============================================================ */
$sql = "
    SELECT 
        a.id AS app_id,
        a.status AS app_status,
        a.eligible AS app_eligible,
        a.submitted_at,
        sp.id AS program_id,
        sp.name AS program_name,
        sp.budget AS program_budget,
        sp.slots AS program_slots,
        p.gpa AS student_gpa,
        p.faculty AS student_faculty,
        p.major AS student_major
    FROM applications a
    JOIN scholarship_programs sp ON a.program_id = sp.id
    LEFT JOIN student_profiles p ON a.student_id = p.student_id
    WHERE a.id = ? AND a.student_id = ?
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$appId, $studentId]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    echo '<div class="container py-5"><div class="alert alert-danger">Application not found or you do not have permission to view it.</div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

/* ============================================================
   FETCH EVALUATION SCORES
   ============================================================ */
$evalSql = "
    SELECT 
        es.score,
        es.note,
        es.scored_at,
        sc.criterion_name,
        sc.weight
    FROM evaluation_scores es
    JOIN scoring_criteria sc ON es.criteria_id = sc.id
    WHERE es.application_id = ?
    ORDER BY sc.id ASC
";
$evalStmt = $pdo->prepare($evalSql);
$evalStmt->execute([$appId]);
$scores = $evalStmt->fetchAll(PDO::FETCH_ASSOC);

/* ============================================================
   FETCH DISBURSEMENT & CERTIFICATE INFO
   ============================================================ */
$disbSql = "SELECT * FROM disbursements WHERE application_id = ? LIMIT 1";
$disbStmt = $pdo->prepare($disbSql);
$disbStmt->execute([$appId]);
$disbursement = $disbStmt->fetch(PDO::FETCH_ASSOC);

$certSql = "SELECT * FROM award_certificates WHERE application_id = ? LIMIT 1";
$certStmt = $pdo->prepare($certSql);
$certStmt->execute([$appId]);
$certificate = $certStmt->fetch(PDO::FETCH_ASSOC);

/* ============================================================
   FETCH EVIDENCE FILES
   ============================================================ */
$evidSql = "SELECT * FROM application_evidence WHERE application_id = ? ORDER BY uploaded_at ASC";
$evidStmt = $pdo->prepare($evidSql);
$evidStmt->execute([$appId]);
$evidenceFiles = $evidStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
/* ============================================================
   HELPERS — derived values used throughout the template
   ============================================================ */

// Status display config
$statusCfg = [
    'submitted'  => ['label'=>'Submitted',   'bg'=>'#dbeafe','color'=>'#1d4ed8','icon'=>'bi-send-fill'],
    'reviewing'  => ['label'=>'Under Review','bg'=>'#dcfce7','color'=>'#15803d','icon'=>'bi-search'],
    'eligible'   => ['label'=>'Eligible',    'bg'=>'#dcfce7','color'=>'#15803d','icon'=>'bi-check-circle-fill'],
    'ineligible' => ['label'=>'Ineligible',  'bg'=>'#fee2e2','color'=>'#dc2626','icon'=>'bi-x-circle-fill'],
    'ranked'     => ['label'=>'Ranked',      'bg'=>'#ede9fe','color'=>'#7c3aed','icon'=>'bi-bar-chart-fill'],
    'approved'   => ['label'=>'Approved',    'bg'=>'#ede9fe','color'=>'#7c3aed','icon'=>'bi-patch-check-fill'],
    'rejected'   => ['label'=>'Rejected',    'bg'=>'#fee2e2','color'=>'#dc2626','icon'=>'bi-x-octagon-fill'],
    'disbursed'  => ['label'=>'Disbursed',   'bg'=>'#ffedd5','color'=>'#c2410c','icon'=>'bi-cash-coin'],
];
$sc = $statusCfg[$app['app_status']] ?? ['label'=>ucfirst($app['app_status']),'bg'=>'#f1f5f9','color'=>'#475569','icon'=>'bi-circle'];

// Fetch ranking info
$rankRow = null;
$rankStmt = $pdo->prepare("SELECT rank, total_score FROM ranking_results WHERE application_id=? LIMIT 1");
$rankStmt->execute([$appId]);
$rankRow = $rankStmt->fetch(PDO::FETCH_ASSOC);

$totalApplicants = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE program_id={$app['program_id']} AND status != 'draft'")->fetchColumn();

// Fetch eligibility rules vs profile for results card
$rulesStmt = $pdo->prepare("SELECT * FROM eligibility_rules WHERE program_id=?");
$rulesStmt->execute([$app['program_id']]);
$eligRules = $rulesStmt->fetchAll(PDO::FETCH_ASSOC);

$profStmt = $pdo->prepare("SELECT * FROM student_profiles WHERE student_id=?");
$profStmt->execute([currentUserId()]);
$prof = $profStmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Progress timeline stages
$timelineStages = [
    'submitted' => ['title'=>'Submitted',        'desc'=>'Application received by the system.',         'icon'=>'bi-send-fill'],
    'reviewing' => ['title'=>'Under Review',     'desc'=>'Reviewers are evaluating your application.',  'icon'=>'bi-search'],
    'eligible'  => ['title'=>'Eligibility Check','desc'=>'System checked your academic requirements.',  'icon'=>'bi-clipboard2-check'],
    'ranked'    => ['title'=>'Ranking',          'desc'=>'Application has been scored and ranked.',      'icon'=>'bi-bar-chart-fill'],
    'approved'  => ['title'=>'Approved',         'desc'=>'Scholarship has been approved.',              'icon'=>'bi-patch-check-fill'],
    'disbursed' => ['title'=>'Disbursed',        'desc'=>'Scholarship funds have been transferred.',    'icon'=>'bi-cash-coin'],
];

$stageOrder = array_keys($timelineStages);
$currentStage = $app['app_status'];
if ($currentStage === 'ineligible') $currentStage = 'eligible';
if ($currentStage === 'rejected')   $currentStage = 'approved';
$currentIdx = array_search($currentStage, $stageOrder);
if ($currentIdx === false) $currentIdx = 0;

$isTerminal = in_array($app['app_status'], ['ineligible','rejected']);
?>

<style>
/* ══════════════════════════════════════════════════════
   APPLICATION DETAILS — Page Styles
   ══════════════════════════════════════════════════════ */

.ad-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 16px;
}

/* ── Hero header ──────────────────────────────────── */
.ad-hero {
  background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 60%, #312e81 100%);
  border-radius: 16px;
  padding: 36px 40px;
  color: #fff;
  margin-bottom: 28px;
  position: relative;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(29, 78, 216, 0.15);
}
.ad-hero::before {
  content: '';
  position: absolute;
  top: -40px; right: -40px;
  width: 200px; height: 200px;
  border-radius: 50%;
  background: rgba(255,255,255,.06);
}
.ad-hero::after {
  content: '';
  position: absolute;
  bottom: -60px; right: 80px;
  width: 160px; height: 160px;
  border-radius: 50%;
  background: rgba(255,255,255,.04);
}
.ad-back-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: rgba(255,255,255,.75);
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  margin-bottom: 16px;
  transition: color .15s;
}
.ad-back-btn:hover { color: #fff; }
.ad-hero-title {
  font-size: 30px;
  font-weight: 800;
  letter-spacing: -.02em;
  margin: 0 0 4px;
}
.ad-hero-prog {
  font-size: 15px;
  color: rgba(255,255,255,.8);
  margin: 0 0 18px;
  font-weight: 500;
}
.ad-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 99px;
  font-size: 13px;
  font-weight: 700;
  background: rgba(255,255,255,.18);
  color: #fff;
  border: 1.5px solid rgba(255,255,255,.3);
  backdrop-filter: blur(4px);
  margin-right: 10px;
}
.ad-hero-meta {
  font-size: 12.5px;
  color: rgba(255,255,255,.6);
  margin-top: 14px;
}
.ad-hero-icon {
  position: absolute;
  right: 40px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 80px;
  color: rgba(255,255,255,.08);
  line-height: 1;
}

/* ── Shared card ──────────────────────────────────── */
.ad-card {
  background: #fff;
  border-radius: 16px;
  border: 1px solid #e8edf5;
  box-shadow: 0 4px 20px rgba(15,23,42,.05);
  margin-bottom: 24px;
  overflow: hidden;
}
.ad-card-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 18px 24px;
  border-bottom: 1px solid #f1f5f9;
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
}
.ad-card-header-icon {
  width: 32px; height: 32px;
  border-radius: 8px;
  background: #eff6ff;
  display: flex; align-items: center; justify-content: center;
  color: #1d4ed8;
  font-size: 14px;
  flex-shrink: 0;
}
.ad-card-body { padding: 24px; }

/* ── Vertical timeline ────────────────────────────── */
.ad-timeline { display: flex; flex-direction: column; gap: 0; }
.ad-tl-item {
  display: flex;
  gap: 16px;
  position: relative;
  padding-bottom: 28px;
}
.ad-tl-item:last-child { padding-bottom: 0; }
.ad-tl-left {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
  width: 36px;
}
.ad-tl-dot {
  width: 36px; height: 36px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 15px;
  flex-shrink: 0;
  z-index: 1;
  position: relative;
}
.ad-tl-dot-done   { background: #dcfce7; color: #15803d; border: 2px solid #bbf7d0; }
.ad-tl-dot-active { background: #1d4ed8; color: #fff;    border: 2px solid #1d4ed8; box-shadow: 0 0 0 4px #dbeafe; }
.ad-tl-dot-fail   { background: #fee2e2; color: #dc2626; border: 2px solid #fecaca; }
.ad-tl-dot-future { background: #f8fafc; color: #cbd5e1; border: 2px solid #e2e8f0; }
.ad-tl-line {
  width: 2px;
  flex: 1;
  margin-top: 4px;
  min-height: 28px;
  border-radius: 2px;
}
.ad-tl-line-done   { background: #bbf7d0; }
.ad-tl-line-future { background: #e2e8f0; }
.ad-tl-item:last-child .ad-tl-line { display: none; }
.ad-tl-content { padding-top: 6px; flex: 1; }
.ad-tl-title {
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 3px;
}
.ad-tl-title-future { color: #94a3b8; }
.ad-tl-desc {
  font-size: 12.5px;
  color: #64748b;
  line-height: 1.5;
}

/* ── Sidebar stats ────────────────────────────────── */
.ad-sidebar-stat {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #f1f5f9;
  font-size: 13.5px;
}
.ad-sidebar-stat:last-child { border-bottom: none; }
.ad-stat-label { color: #64748b; font-weight: 500; display: flex; align-items: center; }
.ad-stat-val   { font-weight: 700; color: #0f172a; text-align: right; }

/* ── Eligibility results ──────────────────────────── */
.ad-elig-row {
  border-bottom: 1px solid #f1f5f9;
}
.ad-elig-row:last-child { border-bottom: none; }

/* ── Badges ───────────────────────────────────────── */
.ad-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 10px; border-radius: 6px;
  font-size: 11.5px; font-weight: 700;
}
.ad-badge-pend { background:#fef3c7; color:#92400e; border: 1px solid #fde68a; }
.ad-badge-pass { background:#dcfce7; color:#15803d; border: 1px solid #bbf7d0; }
.ad-badge-fail { background:#fee2e2; color:#dc2626; border: 1px solid #fecaca; }

/* ── Responsive ───────────────────────────────────── */
@media (max-width: 991px) {
  .ad-hero { padding: 24px; }
  .ad-hero-icon { display: none; }
  .ad-hero-title { font-size: 22px; }
  .ad-card-body { padding: 16px; }
  .h-100 { height: auto !important; }
}
</style>

<div class="ad-container py-4">

  <!-- ════════════════════════════════════════════════
       HERO HEADER
       ════════════════════════════════════════════════ -->
  <div class="ad-hero">
    <a href="my_applications.php" class="ad-back-btn">
      <i class="bi bi-arrow-left"></i> Back to Applications
    </a>
    <h1 class="ad-hero-title">Application #<?= e($app['app_id']) ?></h1>
    <p class="ad-hero-prog"><i class="bi bi-award me-1"></i><?= e($app['program_name']) ?></p>
    <div>
      <span class="ad-status-badge">
        <i class="bi <?= $sc['icon'] ?>"></i>
        <?= $sc['label'] ?>
      </span>
    </div>
    <?php if ($app['submitted_at']): ?>
    <div class="ad-hero-meta">
      <i class="bi bi-calendar3 me-1"></i>
      Submitted on <?= date('F j, Y \a\t H:i', strtotime($app['submitted_at'])) ?>
    </div>
    <?php endif; ?>
    <div class="ad-hero-icon"><i class="bi bi-folder2-open"></i></div>
  </div>

  <!-- ════════════════════════════════════════════════
       SECTION 1: Progress & Quick Summary
       ════════════════════════════════════════════════ -->
  <div class="row g-4 mb-4">
    <!-- Left column (8 cols): Application Progress timeline -->
    <div class="col-lg-8">
      <div class="ad-card h-100 mb-0">
        <div class="ad-card-header">
          <div class="ad-card-header-icon"><i class="bi bi-clock-history"></i></div>
          Application Progress
        </div>
        <div class="ad-card-body">
          <div class="ad-timeline">
            <?php foreach ($timelineStages as $key => $stage):
              $idx = array_search($key, $stageOrder);
              if ($idx < $currentIdx) {
                // Completed
                $dotCls  = 'ad-tl-dot-done';
                $lineCls = 'ad-tl-line-done';
                $icon    = 'bi-check-lg';
                $titleCls = '';
              } elseif ($idx === $currentIdx) {
                // Current — check for terminal failure
                if ($isTerminal && $key === 'eligible') {
                  $dotCls = 'ad-tl-dot-fail'; $icon = 'bi-x-lg';
                } elseif ($isTerminal && $key === 'approved') {
                  $dotCls = 'ad-tl-dot-fail'; $icon = 'bi-x-lg';
                } else {
                  $dotCls = 'ad-tl-dot-active'; $icon = $stage['icon'];
                }
                $lineCls = 'ad-tl-line-future';
                $titleCls = '';
              } else {
                // Future
                $dotCls = 'ad-tl-dot-future'; $icon = $stage['icon'];
                $lineCls = 'ad-tl-line-future';
                $titleCls = 'ad-tl-title-future';
              }
            ?>
            <div class="ad-tl-item">
              <div class="ad-tl-left">
                <div class="ad-tl-dot <?= $dotCls ?>">
                  <i class="bi <?= $icon ?>"></i>
                </div>
                <div class="ad-tl-line <?= $lineCls ?>"></div>
              </div>
              <div class="ad-tl-content">
                <div class="ad-tl-title <?= $titleCls ?>">
                  <?php
                  // Override label for terminal states
                  if ($isTerminal && $key === 'eligible' && in_array($app['app_status'], ['ineligible'])) {
                      echo 'Ineligible — Application Stopped';
                  } elseif ($isTerminal && $key === 'approved' && $app['app_status'] === 'rejected') {
                      echo 'Rejected';
                  } else {
                      echo $stage['title'];
                  }
                  ?>
                </div>
                <div class="ad-tl-desc"><?= $stage['desc'] ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Right column (4 cols): Quick Summary -->
    <div class="col-lg-4">
      <div class="ad-card h-100 mb-0">
        <div class="ad-card-header">
          <div class="ad-card-header-icon"><i class="bi bi-card-text"></i></div>
          Quick Summary
        </div>
        <div class="ad-card-body d-flex flex-column justify-content-between">
          <div class="ad-sidebar-stat py-3">
            <span class="ad-stat-label"><i class="bi bi-activity me-2 text-primary"></i>Current Status</span>
            <span class="ad-stat-val">
              <span class="ad-badge" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;font-weight:700;border-radius:6px;padding:4px 10px;font-size:12px;">
                <i class="bi <?= $sc['icon'] ?> me-1"></i><?= $sc['label'] ?>
              </span>
            </span>
          </div>
          <div class="ad-sidebar-stat py-3">
            <span class="ad-stat-label"><i class="bi bi-shield-check me-2 text-primary"></i>Eligibility</span>
            <span class="ad-stat-val">
              <?php if ($app['app_eligible'] === null): ?>
                <span class="ad-badge ad-badge-pend"><i class="bi bi-hourglass-split me-1"></i>Pending</span>
              <?php elseif ($app['app_eligible']): ?>
                <span class="ad-badge ad-badge-pass"><i class="bi bi-check-circle-fill me-1"></i>Eligible</span>
              <?php else: ?>
                <span class="ad-badge ad-badge-fail"><i class="bi bi-x-circle-fill me-1"></i>Ineligible</span>
              <?php endif; ?>
            </span>
          </div>
          <div class="ad-sidebar-stat py-3">
            <span class="ad-stat-label"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>GPA at Application</span>
            <span class="ad-stat-val fw-bold"><?= e($app['student_gpa'] ?: '—') ?></span>
          </div>
          <div class="ad-sidebar-stat py-3">
            <span class="ad-stat-label"><i class="bi bi-calendar3 me-2 text-primary"></i>Submission Date</span>
            <span class="ad-stat-val fw-bold">
              <?= $app['submitted_at'] ? date('d/m/Y H:i', strtotime($app['submitted_at'])) : '—' ?>
            </span>
          </div>
          <div class="ad-sidebar-stat py-3">
            <span class="ad-stat-label"><i class="bi bi-award me-2 text-primary"></i>Program</span>
            <span class="ad-stat-val fw-bold text-end" style="max-width: 180px; display: inline-block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= e($app['program_name']) ?>">
              <?= e($app['program_name']) ?>
            </span>
          </div>
          <div class="ad-sidebar-stat py-3">
            <span class="ad-stat-label"><i class="bi bi-building me-2 text-primary"></i>Faculty</span>
            <span class="ad-stat-val fw-bold"><?= e($app['student_faculty'] ?: '—') ?></span>
          </div>
          <div class="ad-sidebar-stat py-3 border-0">
            <span class="ad-stat-label"><i class="bi bi-book me-2 text-primary"></i>Major</span>
            <span class="ad-stat-val fw-bold"><?= e($app['student_major'] ?: '—') ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════
       SECTION 2: Eligibility & Evaluation
       ════════════════════════════════════════════════ -->
  <div class="row g-4 mb-4">
    <!-- Left column (8 cols): Eligibility Results -->
    <div class="col-lg-8">
      <div class="ad-card h-100 mb-0">
        <div class="ad-card-header">
          <div class="ad-card-header-icon"><i class="bi bi-shield-check"></i></div>
          Eligibility Results
        </div>
        <div class="ad-card-body">
          <?php if (!empty($eligRules)): ?>
            <?php
            $allPass = true;
            $anyChecked = ($app['app_eligible'] !== null);
            foreach ($eligRules as $rule):
              $ruleType = $rule['rule_type'];
              $profVal  = $prof[$ruleType] ?? null;
              $ruleVal  = $rule['value'];
              $op       = $rule['operator'];
              $pass = false;
              if ($profVal !== null) {
                if ($op === '>=') $pass = (float)$profVal >= (float)$ruleVal;
                elseif ($op === '<=') $pass = (float)$profVal <= (float)$ruleVal;
                elseif ($op === '=')  $pass = (string)$profVal === (string)$ruleVal;
                elseif ($op === '>')  $pass = (float)$profVal >  (float)$ruleVal;
                elseif ($op === '<')  $pass = (float)$profVal <  (float)$ruleVal;
              }
              if (!$pass) $allPass = false;
              $labelMap = [
                'gpa' => 'GPA Requirement', 'activities_count' => 'Activity Requirement',
                'family_income' => 'Income Requirement', 'has_language_cert' => 'Language Certificate',
                'research_count' => 'Research Experience', 'failed_subjects' => 'No Failed Subjects',
                'is_disadvantaged' => 'Disadvantaged Status',
              ];
              $ruleLabel = $labelMap[$ruleType] ?? ucwords(str_replace('_',' ',$ruleType));
            ?>
            <div class="ad-elig-row py-3">
              <div class="d-flex align-items-center gap-3">
                <?php if (!$anyChecked): ?>
                  <i class="bi bi-hourglass-split text-warning fs-5"></i>
                <?php elseif ($pass): ?>
                  <i class="bi bi-check-circle-fill text-success fs-5"></i>
                <?php else: ?>
                  <i class="bi bi-x-circle-fill text-danger fs-5"></i>
                <?php endif; ?>
                <div class="flex-grow-1">
                  <div class="fw-bold text-dark mb-1" style="font-size:14px;"><?= e($ruleLabel) ?></div>
                  <div class="text-muted small" style="font-size:12px;">
                    <?php if ($ruleType === 'has_language_cert'): ?>
                      Requirement: Yes &nbsp;&middot;&nbsp; Student Value: <?= ($profVal ? 'Yes' : 'No') ?>
                    <?php else: ?>
                      Requirement: <?= e($op) ?> <?= e($ruleVal) ?> &nbsp;&middot;&nbsp; Student Value: <?= e($profVal ?? '—') ?>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-info-circle fs-2 mb-3 d-block text-secondary"></i>
              No eligibility rules defined for this scholarship program.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Right column (4 cols): Evaluation Scores -->
    <div class="col-lg-4">
      <div class="ad-card h-100 mb-0">
        <div class="ad-card-header">
          <div class="ad-card-header-icon"><i class="bi bi-list-check"></i></div>
          Evaluation Scores
        </div>
        <div class="ad-card-body">
          <?php if (empty($scores)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-info-circle fs-2 mb-3 d-block text-secondary"></i>
              No scores assigned yet.
            </div>
          <?php else: ?>
            <div class="d-flex flex-column gap-3">
              <?php 
              $totalScore = 0;
              $totalWeight = 0;
              foreach ($scores as $row): 
                  $totalScore += $row['score'] * ($row['weight'] / 100);
                  $totalWeight += $row['weight'];
              ?>
                  <div class="pb-3 border-bottom">
                      <div class="d-flex justify-content-between align-items-center mb-1">
                          <span class="fw-bold text-dark" style="font-size: 13.5px;"><?= e($row['criterion_name']) ?></span>
                          <span class="badge bg-primary rounded-pill"><?= e($row['score']) ?>/10</span>
                      </div>
                      <div class="d-flex justify-content-between text-muted small" style="font-size: 11px;">
                          <span>Weight: <?= e($row['weight']) ?>%</span>
                          <span class="text-truncate ms-2" style="max-width: 150px;" title="<?= e($row['note'] ? $row['note'] : '-') ?>">
                            <?= e($row['note'] ? $row['note'] : '—') ?>
                          </span>
                      </div>
                  </div>
              <?php endforeach; ?>
              <div class="d-flex justify-content-between align-items-center pt-2">
                  <span class="fw-bold text-dark" style="font-size: 14px;">Weighted Avg (<?= $totalWeight ?>%)</span>
                  <span class="badge bg-success fs-6 rounded-3 px-3 py-2"><?= number_format($totalScore, 2) ?></span>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════
       SECTION 3: Evidence & Payout + Certificate
       ════════════════════════════════════════════════ -->
  <div class="row g-4">
    <!-- Left column (8 cols): Evidence Documents -->
    <div class="col-lg-8">
      <div class="ad-card h-100 mb-0">
        <div class="ad-card-header">
          <div class="ad-card-header-icon"><i class="bi bi-paperclip"></i></div>
          Evidence Documents
        </div>
        <div class="ad-card-body">
          <?php if (empty($evidenceFiles)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-file-earmark-x fs-2 mb-3 d-block text-secondary"></i>
              No evidence files were uploaded with this application.
            </div>
          <?php else: ?>
            <div class="d-flex flex-column gap-3">
            <?php foreach ($evidenceFiles as $ev):
                $isImage = strpos($ev['file_type'], 'image/') === 0;
                $isPdf   = $ev['file_type'] === 'application/pdf';
                $iconClass = $isImage ? 'bi-file-earmark-image' : ($isPdf ? 'bi-file-earmark-pdf' : 'bi-file-earmark-text');
                $iconBg    = $isImage ? '#f0fdf4' : ($isPdf ? '#fef2f2' : '#eff6ff');
                $iconColor = $isImage ? '#16a34a' : ($isPdf ? '#dc2626' : '#1d4ed8');
                
                $statusColors = [
                  'pending'  => ['#fef3c7', '#d97706', '#f59e0b'], 
                  'approved' => ['#dcfce7', '#15803d', '#16a34a'], 
                  'rejected' => ['#fee2e2', '#b91c1c', '#dc2626']
                ];
                $scColors = $statusColors[$ev['status']] ?? $statusColors['pending'];
                
                $rawPath = str_replace('\\', '/', $ev['file_path']);
                $segments = explode('/', trim($rawPath, '/'));
                $fileUrl = '/scholarship-management-system/project%20structure/' .
           implode('/', array_map('rawurlencode', $segments));
            ?>
                <div class="p-3 border rounded-3 bg-light bg-opacity-25">
                    <div class="d-flex align-items-start gap-3">
                        <div class="ad-file-icon-box" style="background: <?= $iconBg ?>; color: <?= $iconColor ?>; width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                            <i class="bi <?= $iconClass ?>"></i>
                        </div>
                        <div class="flex-grow-1 min-width-0">
                            <div class="fw-bold text-dark text-truncate mb-1" style="font-size: 13.5px;" title="<?= e($ev['original_name']) ?>">
                                <?= e($ev['original_name']) ?>
                            </div>
                            <div class="text-muted mb-2" style="font-size: 11px;">
                                <?= number_format($ev['file_size'] / 1024, 1) ?> KB &nbsp;&middot;&nbsp;
                                <?= e($ev['file_type']) ?> &nbsp;&middot;&nbsp;
                                Uploaded: <?= date('M d, Y H:i', strtotime($ev['uploaded_at'])) ?>
                            </div>
                            <?php if ($ev['reviewer_comment']): ?>
                            <div class="small text-muted p-2 rounded mb-2" style="background: #f1f5f9; border-left: 3px solid #1d4ed8; font-size: 12px;">
                                <strong>Reviewer note:</strong> <?= e($ev['reviewer_comment']) ?>
                            </div>
                            <?php endif; ?>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge" style="background: <?= $scColors[0] ?>; color: <?= $scColors[1] ?>; border: 1.5px solid <?= $scColors[2] ?>; font-size: 10px; font-weight: 700; text-transform: capitalize;">
                                    <?= e($ev['status']) ?>
                                </span>
                                <?php if ($isImage): ?>
                                    <a href="<?= e($fileUrl) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2" style="font-size: 11px;">
                                        <i class="bi bi-eye me-1"></i>View Image
                                    </a>
                                <?php else: ?>
                                    <a href="<?= e($fileUrl) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-1 px-2" style="font-size: 11px;">
                                        <i class="bi bi-download me-1"></i>Download
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Right column (4 cols): Payout Status & Award Certificate -->
    <div class="col-lg-4 d-flex flex-column gap-4">
      <!-- Payout Status -->
      <div class="ad-card flex-grow-1 mb-0">
        <div class="ad-card-header">
          <div class="ad-card-header-icon"><i class="bi bi-cash-coin"></i></div>
          Payout Status
        </div>
        <div class="ad-card-body">
    <?php if (!$disbursement): ?>
        <div class="text-muted text-center py-4">
            <i class="bi bi-hourglass fs-3 mb-2 d-block text-secondary"></i>
            No disbursements are scheduled yet.
        </div>
    <?php else: ?>
        <div class="mb-3">
            <label class="text-muted small d-block mb-1">Payout Amount</label>
            <h3 class="fw-bold text-success mb-1">
                <?= number_format($disbursement['amount'], 0) ?> <span class="fs-6 text-muted fw-normal">VND</span>
            </h3>
        </div>

        <div class="mb-3">
            <label class="text-muted small d-block mb-1">Transaction Status</label>
            <?php
            // Khai báo logic UI đơn giản trực tiếp (nếu chưa tách được Helper)
            $disbBadge = match ($disbursement['status'] ?? '') {
                'approved' => 'bg-info',
                'paid'     => 'bg-success',
                'failed'   => 'bg-danger',
                default    => 'bg-secondary',
            };
            ?>
            <span class="badge <?= $disbBadge ?> text-capitalize px-3 py-1.5">
                <?= e($disbursement['status']) ?>
            </span>
        </div>

        <?php if (!empty($disbursement['disbursed_at'])): ?>
            <div class="mb-3">
                <label class="text-muted small d-block mb-1">Disbursed Date</label>
                <strong class="text-dark">
                    <?= htmlspecialchars(date('F j, Y', strtotime($disbursement['disbursed_at'])), ENT_QUOTES, 'UTF-8') ?>
                </strong>
            </div>
        <?php endif; ?>

        <?php if (!empty($disbursement['note'])): ?>
            <div>
                <label class="text-muted small d-block mb-1">Notes</label>
                <div class="p-2 bg-light rounded text-muted small" style="font-size: 11.5px;">
                    <?= e($disbursement['note']) ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?> </div>
      </div>

      <!-- Award Certificate -->
      <div class="ad-card flex-grow-1 mb-0">
        <div class="ad-card-header">
          <div class="ad-card-header-icon"><i class="bi bi-patch-check"></i></div>
          Award Certificate
        </div>
        <div class="ad-card-body text-center py-4">
          <?php if (!$certificate): ?>
            <div class="text-muted">
              <i class="bi bi-lock fs-3 mb-2 d-block text-secondary"></i>
              A certificate will be issued after the scholarship is successfully disbursed.
            </div>
          <?php else: ?>
            <div class="fs-1 text-warning mb-3">
                <i class="bi bi-trophy-fill"></i>
            </div>
            <h6 class="fw-bold mb-1">Scholarship Award Certificate</h6>
            <p class="small text-muted mb-3">Certificate Code: <code class="text-dark"><?= e($certificate['certificate_code']) ?></code></p>
            <div class="alert alert-success small mb-3 py-2 px-3">
                <i class="bi bi-calendar-check me-1"></i> Issued on <?= date('F j, Y', strtotime($certificate['issued_at'])) ?>
            </div>
            <button class="btn btn-primary w-100" onclick="alert('Downloading certificate: <?= e($certificate['certificate_code']) ?>.pdf')">
                <i class="bi bi-download me-2"></i> Download PDF
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
