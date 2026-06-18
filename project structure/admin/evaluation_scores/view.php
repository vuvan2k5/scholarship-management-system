<?php
// ============================================================
// admin/evaluation_scores/view.php
// Detailed view for admin: criterion-by-criterion breakdown,
// evidence, reviewer comments, score history.
// Admin cannot modify scores.
// ============================================================
$pageTitle = 'Evaluation Score Detail';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo        = getDB();
$appId      = isset($_GET['app_id'])     ? (int)$_GET['app_id']     : 0;
$reviewerId = isset($_GET['reviewer_id']) ? (int)$_GET['reviewer_id'] : 0;

if (!$appId) {
    header('Location: index.php');
    exit;
}

// ── Fetch application + student + program ─────────────────────
$appStmt = $pdo->prepare("
    SELECT
        a.id, a.eligible, a.status, a.submitted_at,
        su.id           AS student_id,
        su.full_name    AS student_name,
        su.student_code,
        su.email        AS student_email,
        sp.id           AS program_id,
        sp.name         AS program_name,
        sp.slots,
        er.is_passed    AS elig_passed,
        er.reason       AS elig_reason,
        er.checked_at   AS elig_checked_at
    FROM applications a
    JOIN users su             ON a.student_id = su.id
    JOIN scholarship_programs sp ON a.program_id = sp.id
    LEFT JOIN (
        SELECT application_id, is_passed, reason, checked_at
        FROM eligibility_results
        WHERE id IN (SELECT MAX(id) FROM eligibility_results GROUP BY application_id)
    ) er ON er.application_id = a.id
    WHERE a.id = ?
    LIMIT 1
");
$appStmt->execute([$appId]);
$app = $appStmt->fetch();

if (!$app) {
    require_once '../../includes/header.php';
    require_once '../../includes/navbar.php';
    echo '<div class="page-header"><div class="page-header-left">
          <h1 class="page-title">Not Found</h1></div>
          <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a></div>
          <div class="alert alert-danger">Application not found.</div>';
    require_once '../../includes/footer.php';
    exit;
}

// ── Eligibility guard ─────────────────────────────────────────
$eligPassed = $app['elig_passed'];
$isEligible = ($eligPassed == 1 || $app['eligible'] == 1);

// ── Fetch all scoring criteria for this program ───────────────
$criteriaList = $pdo->prepare("
    SELECT id, criterion_name, weight, max_score, description
    FROM scoring_criteria
    WHERE program_id = ? AND (is_active IS NULL OR is_active = 1)
    ORDER BY weight DESC
");
$criteriaList->execute([$app['program_id']]);
$criteriaList = $criteriaList->fetchAll();

// ── Fetch all scores for this application ─────────────────────
// Optionally filtered by reviewer
$scWhere  = $reviewerId ? "AND es.council_id = ?" : "";
$scParams = $reviewerId ? [$appId, $reviewerId] : [$appId];

$scoresStmt = $pdo->prepare("
    SELECT
        es.*,
        sc.criterion_name,
        sc.weight,
        sc.max_score     AS criterion_max,
        u.full_name      AS reviewer_name
    FROM evaluation_scores es
    JOIN scoring_criteria sc ON es.criteria_id = sc.id
    JOIN users u             ON es.council_id  = u.id
    WHERE es.application_id = ? $scWhere
    ORDER BY sc.weight DESC, es.scored_at DESC
");
$scoresStmt->execute($scParams);
$scores = $scoresStmt->fetchAll();

// ── Group scores by reviewer ──────────────────────────────────
$byReviewer = [];
foreach ($scores as $s) {
    $rid = $s['council_id'];
    $byReviewer[$rid]['info'] = ['id' => $rid, 'name' => $s['reviewer_name']];
    $byReviewer[$rid]['scores'][$s['criteria_id']] = $s;
}

// ── Compute weighted totals per reviewer ──────────────────────
$reviewerTotals = [];
foreach ($byReviewer as $rid => $rv) {
    $total = 0;
    foreach ($rv['scores'] as $s) {
        $total += (float)$s['score'] * (float)$s['weight'] / 100;
    }
    $reviewerTotals[$rid] = round($total, 2);
}

// ── Get score history ─────────────────────────────────────────
$historyStmt = $pdo->prepare("
    SELECT esh.*, sc.criterion_name, u.full_name AS reviewer_name
    FROM evaluation_score_history esh
    JOIN scoring_criteria sc ON esh.criteria_id = sc.id
    JOIN users u             ON esh.reviewer_id  = u.id
    WHERE esh.application_id = ?
    ORDER BY esh.changed_at DESC
    LIMIT 50
");
$historyStmt->execute([$appId]);
$history = $historyStmt->fetchAll();

// ── Fetch evidence/documents ──────────────────────────────────
$evidenceStmt = $pdo->prepare("
    SELECT ae.*
    FROM application_evidence ae
    WHERE ae.application_id = ?
    ORDER BY ae.uploaded_at ASC
");
$evidenceStmt->execute([$appId]);
$evidence = $evidenceStmt->fetchAll();

// ── Fetch student profile ─────────────────────────────────────
$profileStmt = $pdo->prepare("SELECT * FROM student_profiles WHERE student_id = ?");
$profileStmt->execute([$app['student_id']]);
$profile = $profileStmt->fetch();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">
      Application #<?= e($app['id']) ?> — Score Detail
    </h1>
    <p class="page-subtitle">
      <?= e($app['student_name']) ?> · <?= e($app['program_name']) ?>
      &nbsp;·&nbsp;<span style="color:var(--gray-400);">Admin view only</span>
    </p>
  </div>
  <div class="d-flex gap-2">
    <a href="export.php?format=pdf&app_id=<?= $appId ?><?= $reviewerId ? '&reviewer_id='.$reviewerId : '' ?>"
       class="btn btn-danger" id="btn-export-pdf">
      <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
    </a>
    <a href="index.php" class="btn btn-secondary">
      <i class="bi bi-arrow-left me-1"></i> Back
    </a>
  </div>
</div>

<?php showFlash(); ?>

<!-- ── Eligibility Warning ───────────────────────────────────── -->
<?php if (!$isEligible): ?>
  <div class="alert alert-danger mb-4" style="border-left:4px solid var(--danger);">
    <i class="bi bi-shield-exclamation me-2"></i>
    <strong>Eligibility Not Met:</strong> This application did not pass the Eligibility Engine.
    <?php if ($app['elig_reason']): ?>
      <div style="margin-top:4px;font-size:12.5px;"><?= e($app['elig_reason']) ?></div>
    <?php endif; ?>
    According to business rules, failed applications cannot be scored.
  </div>
<?php else: ?>
  <div class="alert alert-success mb-4" style="border-left:4px solid var(--success);font-size:13px;padding:10px 16px;">
    <i class="bi bi-patch-check-fill me-2"></i>
    <strong>Eligibility Passed</strong> — This application is eligible for reviewer scoring.
    <?php if ($app['elig_checked_at']): ?>
      Evaluated on <?= e(date('d M Y, H:i', strtotime($app['elig_checked_at']))) ?>.
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="row g-4">

  <!-- ── LEFT COLUMN ────────────────────────────────────────── -->
  <div class="col-lg-4">

    <!-- Student Information -->
    <div class="card mb-4">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-person me-2" style="color:var(--primary);"></i>Student Information
        </div>
        <dl style="display:grid;grid-template-columns:auto 1fr;gap:8px 14px;margin:0;font-size:13px;">
          <dt style="color:var(--gray-500);">Name</dt>
          <dd style="margin:0;font-weight:700;"><?= e($app['student_name']) ?></dd>
          <dt style="color:var(--gray-500);">Student ID</dt>
          <dd style="margin:0;font-family:monospace;color:var(--primary);"><?= e($app['student_code'] ?: '—') ?></dd>
          <dt style="color:var(--gray-500);">Email</dt>
          <dd style="margin:0;"><?= e($app['student_email'] ?: '—') ?></dd>
          <dt style="color:var(--gray-500);">Program</dt>
          <dd style="margin:0;"><?= e($app['program_name']) ?></dd>
          <dt style="color:var(--gray-500);">Submitted</dt>
          <dd style="margin:0;"><?= $app['submitted_at'] ? e(date('d M Y', strtotime($app['submitted_at']))) : '—' ?></dd>
        </dl>

        <?php if ($profile): ?>
          <div style="margin-top:14px;padding-top:10px;border-top:1px solid var(--gray-100);">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
                         color:var(--gray-400);margin-bottom:8px;">Academic Profile</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
              <?php
              $profileItems = [
                  'GPA'          => number_format((float)($profile['gpa'] ?? 0), 2),
                  'Faculty'      => $profile['faculty'] ?? '—',
                  'Major'        => $profile['major'] ?? '—',
                  'Activities'   => ($profile['activities_count'] ?? 0).' acts.',
                  'Research'     => ($profile['research_count'] ?? 0).' papers',
                  'Lang. Cert.'  => ($profile['language_certificate'] ?? 0) ? 'Yes' : 'No',
              ];
              foreach ($profileItems as $lbl => $val): ?>
                <div style="background:var(--gray-50);border-radius:6px;padding:7px 10px;">
                  <div style="font-size:10px;color:var(--gray-400);"><?= e($lbl) ?></div>
                  <div style="font-size:13px;font-weight:700;"><?= e($val) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Evidence / Documents -->
    <div class="card mb-4">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-paperclip me-2" style="color:var(--info);"></i>Uploaded Evidence
          <span style="font-size:12px;font-weight:400;color:var(--gray-400);">(<?= count($evidence) ?> files)</span>
        </div>
        <?php if (empty($evidence)): ?>
          <p style="font-size:13px;color:var(--gray-400);margin:0;">No evidence files uploaded.</p>
        <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($evidence as $ev):
              $isImg  = str_starts_with($ev['file_type'] ?? '', 'image/');
              $isPdf  = str_contains($ev['file_type'] ?? '', 'pdf');
              $icon   = $isPdf ? 'bi-file-earmark-pdf' : ($isImg ? 'bi-file-earmark-image' : 'bi-file-earmark');
              $iconC  = $isPdf ? 'var(--danger)' : ($isImg ? 'var(--info)' : 'var(--gray-500)');
              $eStatus = $ev['status'] ?? 'pending';
              $eSClass = match($eStatus) { 'approved'=>'badge-eligible', 'rejected'=>'badge-ineligible', default=>'badge-warning' };
            ?>
              <div style="border:1px solid var(--gray-200);border-radius:8px;padding:10px 12px;">
                <div style="display:flex;align-items:center;gap:8px;">
                  <i class="bi <?= $icon ?>" style="color:<?= $iconC ?>;font-size:18px;flex-shrink:0;"></i>
                  <div style="flex:1;min-width:0;">
                    <div style="font-size:12.5px;font-weight:600;
                                 overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                         title="<?= e($ev['original_name']) ?>">
                      <?= e($ev['original_name']) ?>
                    </div>
                    <div style="font-size:10.5px;color:var(--gray-400);">
                      <?= round(($ev['file_size'] ?? 0) / 1024, 1) ?> KB
                    </div>
                  </div>
                  <span class="badge <?= $eSClass ?>" style="font-size:10px;"><?= ucfirst($eStatus) ?></span>
                </div>
                <?php if ($ev['reviewer_comment']): ?>
                  <div style="margin-top:6px;font-size:11.5px;color:var(--gray-500);padding-left:26px;">
                    <i class="bi bi-chat-dots me-1"></i><?= e($ev['reviewer_comment']) ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /col-lg-4 -->

  <!-- ── RIGHT COLUMN ───────────────────────────────────────── -->
  <div class="col-lg-8">

    <!-- Criterion-Based Scores per Reviewer -->
    <?php if (empty($byReviewer)): ?>
      <div class="card mb-4">
        <div class="card-body" style="text-align:center;padding:40px 24px;">
          <i class="bi bi-star-half" style="font-size:40px;color:var(--gray-300);display:block;margin-bottom:12px;"></i>
          <div style="font-weight:700;font-size:15px;color:var(--gray-600);">No Scores Submitted Yet</div>
          <div style="font-size:13px;color:var(--gray-400);margin-top:6px;">
            <?= $isEligible
                ? 'This eligible application has not been scored by any reviewer yet.'
                : 'This application did not pass eligibility — it cannot be scored.' ?>
          </div>
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($byReviewer as $rid => $rv): ?>
        <?php
        $rTotal = $reviewerTotals[$rid] ?? 0;
        $rScores = $rv['scores'];
        // Check for any evidence issues
        $allVerifStatuses = array_column(array_values($rScores), 'verification_status');
        $hasIssue = in_array('need_clarification', $allVerifStatuses) || in_array('rejected_evidence', $allVerifStatuses);
        ?>
        <div class="card mb-4" style="border-top:3px solid var(--primary);">
          <div class="card-body">
            <!-- Reviewer header -->
            <div style="display:flex;align-items:center;justify-content:space-between;
                         padding-bottom:12px;border-bottom:1px solid var(--gray-100);margin-bottom:14px;flex-wrap:wrap;gap:8px;">
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:38px;height:38px;border-radius:50%;background:rgba(37,99,235,.12);
                             display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                  <i class="bi bi-person-badge" style="color:var(--primary);font-size:18px;"></i>
                </div>
                <div>
                  <div style="font-weight:700;font-size:14px;"><?= e($rv['info']['name']) ?></div>
                  <div style="font-size:11px;color:var(--gray-400);">Reviewer</div>
                </div>
              </div>
              <div style="text-align:right;">
                <div style="font-size:24px;font-weight:900;color:var(--primary);line-height:1;">
                  <?= number_format($rTotal, 2) ?>
                </div>
                <div style="font-size:11px;color:var(--gray-400);">Weighted Total Score</div>
              </div>
            </div>

            <!-- Per-criterion table -->
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
              <thead>
                <tr style="background:var(--gray-50);">
                  <th style="padding:8px 10px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Criterion</th>
                  <th style="padding:8px 10px;text-align:center;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Weight</th>
                  <th style="padding:8px 10px;text-align:center;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Max Score</th>
                  <th style="padding:8px 10px;text-align:center;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Reviewer Score</th>
                  <th style="padding:8px 10px;text-align:center;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Weighted</th>
                  <th style="padding:8px 10px;text-align:center;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Evidence</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($criteriaList as $cr):
                  $sc = $rScores[$cr['id']] ?? null;
                  $scored = $sc !== null;
                  $wt = round((float)($sc['score'] ?? 0) * (float)$cr['weight'] / 100, 2);
                  $verifStat = $sc['verification_status'] ?? null;
                  $verifLabel = match($verifStat) {
                      'need_clarification' => 'Need Clarification',
                      'rejected_evidence'  => 'Rejected Evidence',
                      'verified'           => 'Verified',
                      default              => '—',
                  };
                  $verifColor = match($verifStat) {
                      'need_clarification' => 'var(--warning)',
                      'rejected_evidence'  => 'var(--danger)',
                      'verified'           => 'var(--success)',
                      default              => 'var(--gray-300)',
                  };
                  $pct = $cr['max_score'] > 0
                      ? min(100, round(((float)($sc['score'] ?? 0) / (float)$cr['max_score']) * 100))
                      : 0;
                ?>
                  <tr style="border-bottom:1px solid var(--gray-100);<?= !$scored ? 'opacity:.5;' : '' ?>">
                    <td style="padding:10px 10px;">
                      <div style="font-weight:600;"><?= e($cr['criterion_name']) ?></div>
                      <?php if (!empty($cr['description'])): ?>
                        <div style="font-size:11px;color:var(--gray-400);"><?= e($cr['description']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td style="padding:10px;text-align:center;font-weight:700;color:var(--primary);">
                      <?= e($cr['weight']) ?>%
                    </td>
                    <td style="padding:10px;text-align:center;color:var(--gray-500);">
                      <?= e($cr['max_score']) ?>
                    </td>
                    <td style="padding:10px;text-align:center;">
                      <?php if ($scored): ?>
                        <div style="font-size:16px;font-weight:800;color:#0f172a;"><?= number_format((float)$sc['score'], 2) ?></div>
                        <!-- Score bar -->
                        <div style="margin:4px auto;width:60px;height:4px;background:var(--gray-200);border-radius:99px;overflow:hidden;">
                          <div style="width:<?= $pct ?>%;height:100%;background:var(--primary);border-radius:99px;"></div>
                        </div>
                        <div style="font-size:10px;color:var(--gray-400);"><?= $pct ?>%</div>
                      <?php else: ?>
                        <span style="color:var(--gray-300);font-size:18px;">—</span>
                      <?php endif; ?>
                    </td>
                    <td style="padding:10px;text-align:center;font-weight:700;color:var(--gray-700);">
                      <?= $scored ? number_format($wt, 2) : '—' ?>
                    </td>
                    <td style="padding:10px;text-align:center;">
                      <?php if ($scored): ?>
                        <span style="font-size:11px;font-weight:700;color:<?= $verifColor ?>;">
                          <i class="bi bi-circle-fill" style="font-size:7px;"></i>
                          <?= e($verifLabel) ?>
                        </span>
                      <?php else: ?>
                        <span style="color:var(--gray-300);">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>

                  <!-- Reviewer comment for this criterion -->
                  <?php if ($scored && !empty($sc['note'])): ?>
                    <tr style="border-bottom:1px solid var(--gray-100);background:var(--gray-50);">
                      <td colspan="6" style="padding:6px 10px 8px 24px;">
                        <span style="font-size:11.5px;color:var(--gray-600);">
                          <i class="bi bi-chat-dots me-1" style="color:var(--info);"></i>
                          <em><?= e($sc['note']) ?></em>
                        </span>
                      </td>
                    </tr>
                  <?php endif; ?>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr style="background:linear-gradient(135deg,rgba(37,99,235,.06),rgba(124,58,237,.06));">
                  <td colspan="4" style="padding:12px 10px;font-weight:700;font-size:13px;">
                    <i class="bi bi-calculator me-1" style="color:var(--primary);"></i>
                    Total Weighted Score (auto-calculated, not editable)
                  </td>
                  <td style="padding:12px 10px;text-align:center;font-size:20px;font-weight:900;color:var(--primary);">
                    <?= number_format($rTotal, 2) ?>
                  </td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- Score History -->
    <?php if ($history): ?>
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-clock-history me-2" style="color:var(--info);"></i>Score History
        </div>
        <div class="table-responsive">
          <table class="table" style="font-size:12.5px;margin:0;">
            <thead>
              <tr>
                <th>Criterion</th>
                <th>Reviewer</th>
                <th>Previous Score</th>
                <th>New Score</th>
                <th>Evidence Status</th>
                <th>Changed At</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $h): ?>
                <tr>
                  <td><?= e($h['criterion_name']) ?></td>
                  <td><?= e($h['reviewer_name']) ?></td>
                  <td style="color:var(--gray-400);">
                    <?= $h['old_score'] !== null ? number_format((float)$h['old_score'], 2) : '<em style="color:var(--gray-300);">New</em>' ?>
                  </td>
                  <td style="font-weight:700;color:var(--primary);"><?= number_format((float)$h['new_score'], 2) ?></td>
                  <td>
                    <?php if ($h['new_verification_status']): ?>
                      <span style="font-size:11px;">
                        <?= ucwords(str_replace('_', ' ', $h['new_verification_status'])) ?>
                      </span>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td class="text-muted" style="white-space:nowrap;">
                    <?= e(date('d M Y, H:i', strtotime($h['changed_at']))) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /col-lg-8 -->
</div><!-- /row -->

<?php require_once '../../includes/footer.php'; ?>
