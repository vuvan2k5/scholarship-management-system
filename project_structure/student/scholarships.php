<?php
// ============================================================
// student/scholarships.php  –  Browse available scholarships
// ============================================================
$pageTitle = 'Available Scholarships';

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requireRole('student');

require_once __DIR__ . '/../includes/header.php';
// require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/student_header.php';

$pdo       = getDB();
$studentId = currentUserId();

// Fetch all open programs with eligibility rule count, slots, and whether student already applied
$sql = "
    SELECT sp.*,
           COUNT(DISTINCT er.id)    AS rule_count,
           COUNT(DISTINCT sc.id)    AS criteria_count,
           (SELECT a.id FROM applications a
            WHERE a.student_id = ? AND a.program_id = sp.id LIMIT 1) AS my_app_id
    FROM scholarship_programs sp
    LEFT JOIN eligibility_rules er ON er.program_id = sp.id
    LEFT JOIN scoring_criteria  sc ON sc.program_id = sp.id
    GROUP BY sp.id
    ORDER BY sp.status = 'open' DESC, sp.end_date ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$studentId]);
$programs = $stmt->fetchAll();

// Build a map of eligibility rules per program for quick display
$rulesMap = [];
$rulesRows = $pdo->query("SELECT * FROM eligibility_rules ORDER BY program_id, id")->fetchAll();
foreach ($rulesRows as $r) {
    $rulesMap[$r['program_id']][] = $r;
}
?>
<style>
.page-header{
    margin:32px 0 !important;

    display:block !important;
    width:100% !important;
}

.page-title{
    display:block !important;
    font-size:40px !important;
    width:100% !important;
}

.page-subtitle{
    display:block !important;
    width:100% !important;

    margin-top:12px !important;
    max-width:850px;
}
.page-container{
    max-width:1600px;
    margin:0 auto;
    padding:32px;
}
.page-header{
    margin-top:36px;
    margin-bottom:28px;
}
</style>
<div class="page-container">
<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">Available Scholarships</h1>

    <p class="page-subtitle">
        Explore available scholarship opportunities, review eligibility requirements,
        and submit your application before the deadline.
    </p>
</div>

<?php showFlash(); ?>

<?php if (empty($programs)): ?>
  <div class="card">
    <div class="card-body">
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bi bi-award"></i></div>
        <div class="empty-state-title">No Scholarships Available</div>
        <div class="empty-state-text">There are no scholarship programs at the moment. Check back later.</div>
      </div>
    </div>
  </div>
<?php else: ?>

  <!-- Stats row -->
  <div class="row g-3 mb-4">
    <?php
      $openCount   = count(array_filter($programs, fn($p) => $p['status'] === 'open'));
      $appliedCount= count(array_filter($programs, fn($p) => $p['my_app_id']));
    ?>
    <div class="col-sm-4">
      <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-award"></i></div>
        <div class="stat-body">
          <div class="stat-label">Total Programs</div>
          <div class="stat-value"><?= count($programs) ?></div>
          <div class="stat-trend">All available programs</div>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-door-open"></i></div>
        <div class="stat-body">
          <div class="stat-label">Open for Applications</div>
          <div class="stat-value"><?= $openCount ?></div>
          <div class="stat-trend">Accepting now</div>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="stat-card">
        <div class="stat-icon yellow"><i class="bi bi-folder-check"></i></div>
        <div class="stat-body">
          <div class="stat-label">Already Applied</div>
          <div class="stat-value"><?= $appliedCount ?></div>
          <div class="stat-trend">Your applications</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Program cards grid -->
  <div class="row g-4">
    <?php foreach ($programs as $prog): ?>
    <?php
      $isOpen    = $prog['status'] === 'open';
      $applied   = !empty($prog['my_app_id']);
      $deadlinePast = $prog['end_date'] && strtotime($prog['end_date']) < time();
      $canApply  = $isOpen && !$applied && !$deadlinePast;
      $rules     = $rulesMap[$prog['id']] ?? [];

      // Days remaining
      $daysLeft = null;
      if ($prog['end_date']) {
        $daysLeft = (int)ceil((strtotime($prog['end_date']) - time()) / 86400);
      }
    ?>
    <div class="col-md-6 col-xl-4">
      <div class="card h-100" style="border-top: 4px solid <?= $isOpen ? '#2563eb' : '#94a3b8' ?>;">
        <div class="card-body" style="padding:24px;">
          <!-- Header -->
          <div class="d-flex justify-content-between align-items-start mb-3">
            <div style="width:44px;height:44px;border-radius:10px;background:<?= $isOpen ? '#eff6ff' : '#f1f5f9' ?>;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
              🏆
            </div>
            <div class="d-flex gap-2 flex-wrap justify-content-end">
              <?php if ($applied): ?>
                <span class="badge badge-eligible"><i class="bi bi-check-circle-fill"></i> Applied</span>
              <?php endif; ?>
              <span class="badge <?= $isOpen ? 'badge-approved' : 'badge-inactive' ?>">
                <?= $isOpen ? 'Open' : 'Closed' ?>
              </span>
            </div>
          </div>

          <h5 style="font-weight:700;color:#0f172a;margin-bottom:8px;font-size:15px;line-height:1.3;">
            <?= e($prog['name']) ?>
          </h5>
          <p style="font-size:13px;color:#64748b;margin-bottom:16px;min-height:40px;">
            <?= e(mb_strimwidth($prog['description'] ?? '', 0, 120, '…')) ?>
          </p>

          <!-- Meta info -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
            <div style="background:#f8fafc;border-radius:8px;padding:10px 12px;">
              <div style="font-size:10px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.06em;">Budget</div>
              <div style="font-size:14px;font-weight:700;color:#16a34a;"><?= number_format($prog['budget']) ?> ₫</div>
            </div>
            <div style="background:#f8fafc;border-radius:8px;padding:10px 12px;">
              <div style="font-size:10px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.06em;">Slots</div>
              <div style="font-size:14px;font-weight:700;color:#2563eb;"><?= $prog['slots'] ?> positions</div>
            </div>
            <?php if ($prog['start_date']): ?>
            <div style="background:#f8fafc;border-radius:8px;padding:10px 12px;">
              <div style="font-size:10px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.06em;">Opens</div>
              <div style="font-size:13px;font-weight:600;color:#334155;"><?= date('M d, Y', strtotime($prog['start_date'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($prog['end_date']): ?>
            <div style="background:<?= $daysLeft !== null && $daysLeft <= 7 && $daysLeft >= 0 ? '#fef2f2' : '#f8fafc' ?>;border-radius:8px;padding:10px 12px;">
              <div style="font-size:10px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.06em;">Deadline</div>
              <div style="font-size:13px;font-weight:600;color:<?= $daysLeft !== null && $daysLeft <= 7 && $daysLeft >= 0 ? '#dc2626' : '#334155' ?>;">
                <?= date('M d, Y', strtotime($prog['end_date'])) ?>
                <?php if ($daysLeft !== null && $daysLeft >= 0 && $isOpen): ?>
                  <span style="font-size:11px;color:#dc2626;display:block;"><?= $daysLeft ?> days left</span>
                <?php elseif ($daysLeft !== null && $daysLeft < 0): ?>
                  <span style="font-size:11px;color:#dc2626;display:block;">Expired</span>
                <?php endif; ?>
              </div>
            </div>
            <?php endif; ?>
          </div>

          <!-- Eligibility requirements -->
          <?php if (!empty($rules)): ?>
          <div style="margin-bottom:16px;">
            <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">
              Requirements
            </div>
            <?php foreach (array_slice($rules, 0, 3) as $rule): ?>
              <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:#475569;margin-bottom:4px;">
                <i class="bi bi-check2 text-primary" style="font-size:13px;"></i>
                <?php
                $labels = [
                    'gpa'             => 'GPA',
                    'activities'      => 'Activities',
                    'activities_count'=> 'Activities',
                    'failed_subjects' => 'Failed Subjects',
                    'research_projects'=> 'Research Projects',
                    'has_language_cert' => 'Language Certificate',
                ];
                $lbl = $labels[$rule['rule_type']] ?? ucfirst(str_replace('_', ' ', $rule['rule_type']));
                ?>
                <strong><?= $lbl ?></strong> <?= $rule['operator'] ?> <?= $rule['value'] ?>
              </div>
            <?php endforeach; ?>
            <?php if (count($rules) > 3): ?>
              <div style="font-size:12px;color:#94a3b8;">+<?= count($rules) - 3 ?> more requirements</div>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <!-- Action button -->
          <?php if ($applied): ?>
            <a href="application_details.php?id=<?= $prog['my_app_id'] ?>"
               class="btn btn-secondary w-100">
              <i class="bi bi-eye"></i> View My Application
            </a>
          <?php elseif ($canApply): ?>
            <a href="apply.php?program_id=<?= $prog['id'] ?>"
               class="btn btn-primary w-100">
              <i class="bi bi-file-earmark-plus"></i> Apply Now
            </a>
          <?php else: ?>
            <button class="btn btn-secondary w-100" disabled>
              <i class="bi bi-x-circle"></i>
              <?= $deadlinePast ? 'Deadline Passed' : 'Closed' ?>
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>  
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
