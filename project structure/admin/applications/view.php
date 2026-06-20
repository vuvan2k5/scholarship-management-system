<?php
// ============================================================
// admin/applications/view.php  –  Application Detail (read-only)
// Admin views the full scholarship workflow for one application.
// ============================================================
$pageTitle = 'Application Detail';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ── Core application row ─────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT a.*,
           u.full_name, u.email, u.student_code, u.created_at AS user_created,
           sp.name AS program_name, sp.budget, sp.slots, sp.start_date, sp.end_date, sp.status AS program_status
    FROM applications a
    JOIN users u               ON a.student_id  = u.id
    JOIN scholarship_programs sp ON a.program_id = sp.id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$app = $stmt->fetch();

if (!$app) {
    require_once '../../includes/header.php';
    require_once '../../includes/navbar.php';
    echo '<div class="page-header"><div class="page-header-left"><h1 class="page-title">Not Found</h1></div>
          <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a></div>
          <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>Application #' . (int)$id . ' does not exist.</div>';
    require_once '../../includes/footer.php';
    exit;
}

// ── Student academic profile ─────────────────────────────────
$profileStmt = $pdo->prepare("SELECT * FROM student_profiles WHERE student_id = ?");
$profileStmt->execute([$app['student_id']]);
$profile = $profileStmt->fetch();

// ── Eligibility results (all checks, latest first) ───────────
$eligStmt = $pdo->prepare("
    SELECT * FROM eligibility_results
    WHERE application_id = ?
    ORDER BY checked_at DESC
");
$eligStmt->execute([$id]);
$eligResults = $eligStmt->fetchAll();

// ── Evaluation scores grouped by criterion ───────────────────
$scoreStmt = $pdo->prepare("
    SELECT es.id, es.score, es.note, es.scored_at,
           sc.criterion_name, sc.weight, sc.max_score,
           u.full_name AS reviewer_name
    FROM evaluation_scores es
    JOIN scoring_criteria sc ON es.criteria_id = sc.id
    JOIN users u              ON es.council_id  = u.id
    WHERE es.application_id = ?
    ORDER BY sc.criterion_name ASC, es.scored_at ASC
");
$scoreStmt->execute([$id]);
$scores = $scoreStmt->fetchAll();

// ── Ranking result ───────────────────────────────────────────
$rankStmt = $pdo->prepare("SELECT * FROM ranking_results WHERE application_id = ?");
$rankStmt->execute([$id]);
$ranking = $rankStmt->fetch();

// ── Uploaded evidence ────────────────────────────────────────
$evidStmt = $pdo->prepare("
    SELECT ae.*, u.full_name AS student_name
    FROM application_evidence ae
    JOIN users u ON ae.student_id = u.id
    WHERE ae.application_id = ?
    ORDER BY ae.uploaded_at ASC
");
$evidStmt->execute([$id]);
$evidence = $evidStmt->fetchAll();

// ── Disbursement ─────────────────────────────────────────────
$disbStmt = $pdo->prepare("SELECT * FROM disbursements WHERE application_id = ? LIMIT 1");
$disbStmt->execute([$id]);
$disbursement = $disbStmt->fetch();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

// Helper: workflow step resolver
function workflowStep(array $app, ?array $ranking, array $eligResults): int {
    if (!empty($ranking) && $ranking['recommended']) return 6; // Awarded
    if (!empty($ranking)) return 5;                             // Ranked
    if (!empty($eligResults) && $eligResults[0]['is_passed']) return 4; // Verified
    if ($app['status'] === 'reviewing') return 3;              // Under Review
    if (!empty($eligResults)) return 2;                        // Eligibility Checked
    if ($app['status'] === 'submitted') return 1;              // Submitted
    return 0;
}

$wfStep = workflowStep($app, $ranking ?: [], $eligResults);
$wfSteps = ['Submitted','Eligibility Checked','Under Review','Verified','Ranked','Awarded'];
?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Application #<?= e($app['id']) ?></h1>
    <p class="page-subtitle">
      Full workflow detail for <strong><?= e($app['full_name']) ?></strong>
      — <em><?= e($app['program_name']) ?></em>. Read-only view.
    </p>
  </div>
  <div class="d-flex gap-2">
    <a href="index.php" class="btn btn-secondary" id="back-to-apps">
      <i class="bi bi-arrow-left"></i> Back
    </a>
    <a href="?id=<?= $id ?>&export=pdf" class="btn btn-outline-primary" id="export-pdf-btn">
      <i class="bi bi-printer"></i> Print
    </a>
  </div>
</div>

<!-- ── Read-only notice ───────────────────────────────────────── -->
<div class="alert alert-info mb-4" style="border-left:4px solid var(--info);">
  <i class="bi bi-info-circle me-2"></i>
  <strong>Read-Only View.</strong> Applications are student-owned records. Admin monitors workflow progress only.
</div>

<!-- ── Workflow Progress Bar ─────────────────────────────────── -->
<div class="card mb-3">
  <div class="card-body" style="padding:20px 28px;">
    <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
      <i class="bi bi-diagram-3 me-2" style="color:var(--primary);"></i>Workflow Status
    </div>
    <div style="display:flex;align-items:center;gap:0;flex-wrap:nowrap;overflow-x:auto;">
      <?php foreach ($wfSteps as $i => $label):
        $stepNum  = $i + 1;
        $done     = $wfStep >= $stepNum;
        $current  = $wfStep === $stepNum;
        $dotBg    = $done  ? 'var(--primary)' : 'var(--gray-200)';
        $dotColor = $done  ? '#fff' : 'var(--gray-400)';
        $txtColor = $current ? 'var(--primary)' : ($done ? 'var(--gray-700)' : 'var(--gray-400)');
      ?>
        <div style="display:flex;flex-direction:column;align-items:center;flex:1;min-width:80px;">
          <div style="width:32px;height:32px;border-radius:50%;background:<?= $dotBg ?>;color:<?= $dotColor ?>;
                      display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;
                      box-shadow:<?= $done ? '0 0 0 3px rgba(37,99,235,.15)' : 'none' ?>;
                      transition:all .3s;position:relative;z-index:1;">
            <?php if ($done && !$current): ?>
              <i class="bi bi-check-lg"></i>
            <?php else: ?>
              <?= $stepNum ?>
            <?php endif; ?>
          </div>
          <div style="font-size:11px;font-weight:<?= $current ? '700' : '500' ?>;color:<?= $txtColor ?>;
                      margin-top:6px;text-align:center;white-space:nowrap;">
            <?= $label ?>
          </div>
        </div>
        <?php if ($i < count($wfSteps) - 1): ?>
          <div style="flex:1;height:2px;background:<?= $wfStep > $stepNum ? 'var(--primary)' : 'var(--gray-200)' ?>;
                      min-width:20px;margin-bottom:18px;transition:background .3s;"></div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="row g-3">

  <!-- ── Left Column ──────────────────────────────────────────── -->
  <div class="col-lg-4 d-flex flex-column gap-3">

    <!-- Application Summary Card -->
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-folder2-open me-2" style="color:var(--primary);"></i>Application
        </div>
        <table class="table detail-table mb-0" style="font-size:13px;">
          <tr><th>App ID</th><td>#<?= e($app['id']) ?></td></tr>
          <tr>
            <th>Status</th>
            <td><span class="badge badge-status-<?= e($app['status']) ?>"><?= ucfirst(e($app['status'])) ?></span></td>
          </tr>
          <tr>
            <th>Submitted</th>
            <td><?= $app['submitted_at'] ? e(date('d M Y, H:i', strtotime($app['submitted_at']))) : '—' ?></td>
          </tr>
          <tr><th>Program</th><td><?= e($app['program_name']) ?></td></tr>
          <tr>
            <th>Prog. Status</th>
            <td><span class="badge <?= $app['program_status'] === 'open' ? 'badge-eligible' : 'badge-inactive' ?>"><?= ucfirst(e($app['program_status'])) ?></span></td>
          </tr>
          <tr><th>Budget</th><td><?= number_format((float)$app['budget'], 0, ',', '.') ?>đ</td></tr>
          <tr><th>Slots</th><td><?= e($app['slots']) ?></td></tr>
          <tr>
            <th>App. Eligible</th>
            <td>
              <?php if ($app['eligible'] === null): ?>
                <span class="badge badge-inactive">Unknown</span>
              <?php elseif ($app['eligible']): ?>
                <span class="badge badge-eligible"><i class="bi bi-check2"></i> Yes</span>
              <?php else: ?>
                <span class="badge badge-ineligible"><i class="bi bi-x"></i> No</span>
              <?php endif; ?>
            </td>
          </tr>
        </table>
      </div>
    </div>

    <!-- Student Info Card -->
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-person-vcard me-2" style="color:var(--success);"></i>Student
        </div>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
          <div style="width:44px;height:44px;border-radius:50%;
                      background:linear-gradient(135deg,#2563eb,#1e40af);
                      display:flex;align-items:center;justify-content:center;
                      font-size:18px;font-weight:900;color:#fff;flex-shrink:0;">
            <?= strtoupper(mb_substr($app['full_name'], 0, 1)) ?>
          </div>
          <div>
            <div style="font-weight:700;font-size:14px;color:var(--gray-900);"><?= e($app['full_name']) ?></div>
            <div style="font-size:12px;color:var(--gray-500);"><?= e($app['email']) ?></div>
          </div>
        </div>
        <table class="table detail-table mb-0" style="font-size:13px;">
          <tr><th>Student ID</th>
            <td><?= $app['student_code']
                    ? '<code style="font-size:12px;background:#f1f5f9;padding:2px 7px;border-radius:4px;">' . e($app['student_code']) . '</code>'
                    : '<span class="text-muted">—</span>' ?></td>
          </tr>
          <?php if ($profile): ?>
            <tr><th>Faculty</th><td><?= e($profile['faculty'] ?: '—') ?></td></tr>
            <tr><th>Major</th><td><?= e($profile['major'] ?: '—') ?></td></tr>
          <?php endif; ?>
          <tr><th>Registered</th><td><?= $app['user_created'] ? e(date('d M Y', strtotime($app['user_created']))) : '—' ?></td></tr>
          <tr>
            <td colspan="2" style="padding-top:8px;">
              <a href="../users/view.php?id=<?= e($app['student_id']) ?>" class="btn btn-sm btn-outline-primary w-100">
                <i class="bi bi-person-badge"></i> View Full Profile
              </a>
            </td>
          </tr>
        </table>
      </div>
    </div>

    <!-- Ranking / Result Card -->
    <?php if ($ranking): ?>
    <div class="card" style="border-color:<?= $ranking['recommended'] ? 'rgba(245,158,11,.4)' : 'var(--gray-200)' ?>;">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-bar-chart-steps me-2" style="color:var(--warning);"></i>Ranking Result
        </div>
        <div style="text-align:center;padding:12px 0;">
          <div style="font-size:42px;font-weight:900;color:var(--primary);line-height:1;">
            #<?= e($ranking['rank']) ?>
          </div>
          <div style="font-size:12px;color:var(--gray-400);margin:4px 0 12px;">Overall Rank</div>
          <div style="font-size:22px;font-weight:800;color:var(--gray-800);">
            <?= number_format((float)$ranking['total_score'], 2) ?> / 100
          </div>
          <div style="font-size:12px;color:var(--gray-400);margin:4px 0 14px;">Total Score</div>
          <?php if ($ranking['recommended']): ?>
            <span class="badge" style="background:#fef3c7;color:#92400e;font-size:13px;padding:7px 16px;">
              <i class="bi bi-trophy-fill me-1"></i> Awarded
            </span>
          <?php else: ?>
            <span class="badge badge-inactive" style="font-size:13px;padding:7px 16px;">Not Awarded</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Disbursement Card -->
    <?php if ($disbursement): ?>
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-cash-stack me-2" style="color:var(--success);"></i>Disbursement
        </div>
        <table class="table detail-table mb-0" style="font-size:13px;">
          <tr><th>Amount</th><td><?= number_format((float)$disbursement['amount'], 0, ',', '.') ?>đ</td></tr>
          <tr>
            <th>Status</th>
            <td><span class="badge badge-status-<?= e($disbursement['status']) ?>"><?= ucfirst(e($disbursement['status'])) ?></span></td>
          </tr>
          <tr><th>Disbursed At</th><td><?= $disbursement['disbursed_at'] ? e(date('d M Y', strtotime($disbursement['disbursed_at']))) : '—' ?></td></tr>
          <tr><th>Note</th><td><?= e($disbursement['note'] ?: '—') ?></td></tr>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /col-lg-4 -->

  <!-- ── Right Column ─────────────────────────────────────────── -->
  <div class="col-lg-8 d-flex flex-column gap-3">

    <!-- Academic Profile -->
    <?php if ($profile): ?>
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-mortarboard me-2" style="color:var(--primary);"></i>Academic Profile
        </div>
        <div class="row g-2 mb-3">
          <?php
          $highlights = [
            ['label'=>'GPA',          'value'=>number_format((float)$profile['gpa'],2),            'bg'=>'var(--primary-light)',  'color'=>'var(--primary)'],
            ['label'=>'Activities',   'value'=>(int)$profile['activities_count'],                  'bg'=>'var(--success-light)',  'color'=>'var(--success)'],
            ['label'=>'Research',     'value'=>(int)$profile['research_count'],                    'bg'=>'var(--warning-light)',  'color'=>'var(--warning)'],
            ['label'=>'Failed Subj.', 'value'=>(int)$profile['failed_subjects'],                   'bg'=>($profile['failed_subjects']>0?'var(--danger-light)':'var(--gray-50)'), 'color'=>($profile['failed_subjects']>0?'var(--danger)':'var(--gray-400)')],
          ];
          foreach ($highlights as $h): ?>
            <div class="col-6 col-md-3">
              <div style="background:<?= $h['bg'] ?>;border-radius:var(--radius-md);padding:14px;text-align:center;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:<?= $h['color'] ?>;margin-bottom:4px;"><?= $h['label'] ?></div>
                <div style="font-size:22px;font-weight:900;color:<?= $h['color'] ?>;"><?= $h['value'] ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <table class="table detail-table mb-0" style="font-size:13px;">
          <tr><th>Family Income</th><td><?= $profile['family_income'] !== null ? number_format((float)$profile['family_income'],0,',','.').' đ/month' : '—' ?></td></tr>
          <tr><th>Language Certificate</th><td><?= $profile['has_language_cert'] ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-inactive">No</span>' ?></td></tr>
          <tr><th>Disadvantaged</th><td><?= $profile['is_disadvantaged'] ? '<span class="badge badge-warning">Yes</span>' : '<span class="badge badge-inactive">No</span>' ?></td></tr>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Eligibility Results -->
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-shield-check me-2" style="color:var(--info);"></i>Eligibility Check Results
          <span style="font-size:12px;font-weight:400;color:var(--gray-400);margin-left:8px;"><?= count($eligResults) ?> check<?= count($eligResults) !== 1 ? 's' : '' ?></span>
        </div>
        <?php if (empty($eligResults)): ?>
          <p class="text-muted" style="font-size:13px;margin:0;">No eligibility check has been run for this application yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table" style="font-size:13px;margin:0;">
              <thead>
                <tr>
                  <th>Result</th>
                  <th>Reason / Notes</th>
                  <th>Checked At</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($eligResults as $er): ?>
                  <tr>
                    <td>
                      <?php if ($er['is_passed']): ?>
                        <span class="badge badge-eligible"><i class="bi bi-check2 me-1"></i>Pass</span>
                      <?php else: ?>
                        <span class="badge badge-ineligible"><i class="bi bi-x me-1"></i>Fail</span>
                      <?php endif; ?>
                    </td>
                    <td style="max-width:340px;"><?= e($er['reason'] ?: 'Automatically checked.') ?></td>
                    <td class="text-muted" style="white-space:nowrap;"><?= e(date('d M Y, H:i', strtotime($er['checked_at']))) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Evaluation Scores -->
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-star-half me-2" style="color:var(--warning);"></i>Reviewer Evaluation Scores
          <span style="font-size:12px;font-weight:400;color:var(--gray-400);margin-left:8px;"><?= count($scores) ?> score<?= count($scores) !== 1 ? 's' : '' ?></span>
        </div>
        <?php if (empty($scores)): ?>
          <p class="text-muted" style="font-size:13px;margin:0;">No evaluation scores have been submitted for this application yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table" style="font-size:13px;margin:0;">
              <thead>
                <tr>
                  <th>Criterion</th>
                  <th>Weight</th>
                  <th>Score</th>
                  <th>Reviewer</th>
                  <th>Comment</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($scores as $sc):
                  $pct = $sc['max_score'] > 0 ? round(($sc['score']/$sc['max_score'])*100) : 0;
                  $barColor = $pct >= 80 ? 'var(--success)' : ($pct >= 50 ? 'var(--primary)' : 'var(--warning)');
                ?>
                  <tr>
                    <td><span class="badge badge-info"><?= e($sc['criterion_name']) ?></span></td>
                    <td><?= e($sc['weight']) ?>%</td>
                    <td>
                      <div style="display:flex;align-items:center;gap:7px;">
                        <strong style="color:var(--primary);"><?= number_format((float)$sc['score'],2) ?></strong>
                        <span style="font-size:11px;color:var(--gray-400);">/ <?= e($sc['max_score']) ?></span>
                        <div style="width:60px;height:5px;background:var(--gray-200);border-radius:99px;overflow:hidden;">
                          <div style="width:<?= $pct ?>%;height:100%;background:<?= $barColor ?>;border-radius:99px;"></div>
                        </div>
                      </div>
                    </td>
                    <td><?= e($sc['reviewer_name']) ?></td>
                    <td style="max-width:200px;font-size:12px;color:var(--gray-500);"><?= e($sc['note'] ?: '—') ?></td>
                    <td class="text-muted" style="white-space:nowrap;font-size:12px;"><?= e(date('d M Y', strtotime($sc['scored_at']))) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Uploaded Evidence -->
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-paperclip me-2" style="color:var(--gray-500);"></i>Uploaded Documents / Evidence
          <span style="font-size:12px;font-weight:400;color:var(--gray-400);margin-left:8px;"><?= count($evidence) ?> file<?= count($evidence) !== 1 ? 's' : '' ?></span>
        </div>
        <?php if (empty($evidence)): ?>
          <p class="text-muted" style="font-size:13px;margin:0;">No documents have been uploaded for this application.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table" style="font-size:13px;margin:0;">
              <thead>
                <tr><th>File Name</th><th>Type</th><th>Size</th><th>Status</th><th>Reviewer Note</th><th>Uploaded</th></tr>
              </thead>
              <tbody>
                <?php foreach ($evidence as $ev):
                  $ext = strtolower(pathinfo($ev['original_name'], PATHINFO_EXTENSION));
                  $icon = match($ext) {
                      'pdf'  => 'bi-file-pdf text-danger',
                      'jpg','jpeg','png','gif' => 'bi-file-image text-info',
                      default => 'bi-file-earmark text-secondary'
                  };
                ?>
                  <tr>
                    <td>
                      <i class="bi <?= $icon ?> me-1"></i>
                      <?= e($ev['original_name']) ?>
                    </td>
                    <td class="text-muted"><?= e($ev['file_type'] ?: '—') ?></td>
                    <td class="text-muted"><?= $ev['file_size'] ? round($ev['file_size']/1024, 1).'KB' : '—' ?></td>
                    <td>
                      <?php
                      $eStatus = $ev['status'] ?? 'pending';
                      $eBadge  = match($eStatus) { 'approved'=>'badge-eligible', 'rejected'=>'badge-ineligible', default=>'badge-inactive' };
                      ?>
                      <span class="badge <?= $eBadge ?>"><?= ucfirst(e($eStatus)) ?></span>
                    </td>
                    <td style="max-width:180px;font-size:12px;color:var(--gray-500);"><?= e($ev['reviewer_comment'] ?: '—') ?></td>
                    <td class="text-muted" style="white-space:nowrap;"><?= e(date('d M Y', strtotime($ev['uploaded_at']))) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /col-lg-8 -->
</div>

<?php require_once '../../includes/footer.php'; ?>
