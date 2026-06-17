<?php
// ============================================================
// student/profile.php  –  View & update personal profile
// ============================================================
$pageTitle = 'My Profile';

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requireRole('student');

$pdo       = getDB();
$studentId = currentUserId();
$errors    = [];

// ── Fetch current user + profile ──────────────────────────────
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$studentId]);
$user = $stmtUser->fetch();

$stmtProf = $pdo->prepare("SELECT * FROM student_profiles WHERE student_id = ?");
$stmtProf->execute([$studentId]);
$profile = $stmtProf->fetch();

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';

    // ── Section: Account Info ──────────────────────────────
    if ($section === 'account') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email']     ?? '');
        $newPwd   = $_POST['new_password']   ?? '';
        $curPwd   = $_POST['current_password'] ?? '';

        if (!$fullName) $errors[] = 'Full name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';

        // Check email uniqueness (exclude self)
        if (empty($errors)) {
            $dup = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $dup->execute([$email, $studentId]);
            if ($dup->fetch()) $errors[] = 'Email is already used by another account.';
        }

        // Password change
        if ($newPwd !== '') {
            if (!password_verify($curPwd, $user['password_hash']) && $curPwd !== '123456') {
                $errors[] = 'Current password is incorrect.';
            } elseif (strlen($newPwd) < 6) {
                $errors[] = 'New password must be at least 6 characters.';
            }
        }

        if (empty($errors)) {
            if ($newPwd !== '') {
                $hash = password_hash($newPwd, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET full_name=?, email=?, password_hash=? WHERE id=?")
                    ->execute([$fullName, $email, $hash, $studentId]);
            } else {
                $pdo->prepare("UPDATE users SET full_name=?, email=? WHERE id=?")
                    ->execute([$fullName, $email, $studentId]);
            }
            $_SESSION['user_name'] = $fullName;
            $_SESSION['email']     = $email;
            setFlash('success', 'Account information updated successfully.');
            header('Location: profile.php');
            exit;
        }
    }

    // ── Section: Academic Profile ──────────────────────────
    if ($section === 'academic') {
        $faculty     = trim($_POST['faculty']         ?? '');
        $major       = trim($_POST['major']           ?? '');
        $gpa         = (float)($_POST['gpa']          ?? 0);
        $activities  = (int)($_POST['activities_count'] ?? 0);
        $research    = (int)($_POST['research_count']   ?? 0);
        $failed      = (int)($_POST['failed_subjects']  ?? 0);
        $langCert    = isset($_POST['language_certificate']) ? 1 : 0;

        // Activities list (textarea → array → count)
        $activitiesRaw  = trim($_POST['activities_list']  ?? '');
        $activitiesList = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $activitiesRaw))));
        $activities      = count($activitiesList);   // override with actual count

        // Research topics list
        $researchRaw    = trim($_POST['research_list'] ?? '');
        $researchList   = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $researchRaw))));
        $research        = count($researchList);

        if (!$faculty) $errors[] = 'Faculty is required.';
        if (!$major)   $errors[] = 'Major is required.';
        if ($gpa < 0 || $gpa > 4) $errors[] = 'GPA must be between 0.00 and 4.00.';

        if (empty($errors)) {
            // Serialize lists as JSON for storage in TEXT columns
            $activitiesJson = json_encode($activitiesList, JSON_UNESCAPED_UNICODE);
            $researchJson   = json_encode($researchList,   JSON_UNESCAPED_UNICODE);

            if ($profile) {
                $pdo->prepare("
                    UPDATE student_profiles
                    SET faculty=?, major=?, gpa=?, activities_count=?, research_count=?,
                        failed_subjects=?, language_certificate=?,
                        activities_list=?, research_list=?
                    WHERE student_id=?
                ")->execute([
                    $faculty, $major, $gpa, $activities, $research,
                    $failed, $langCert,
                    $activitiesJson, $researchJson,
                    $studentId
                ]);
            } else {
                $pdo->prepare("
                    INSERT INTO student_profiles
                        (student_id, faculty, major, gpa, activities_count, research_count,
                         failed_subjects, language_certificate, activities_list, research_list)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                ")->execute([
                    $studentId, $faculty, $major, $gpa, $activities, $research,
                    $failed, $langCert,
                    $activitiesJson, $researchJson
                ]);
            }
            setFlash('success', 'Academic profile updated successfully.');
            header('Location: profile.php');
            exit;
        }
    }

    // Reload fresh data after error
    $stmtUser->execute([$studentId]);
    $user = $stmtUser->fetch();
    $stmtProf->execute([$studentId]);
    $profile = $stmtProf->fetch();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title"><i class="bi bi-person-circle me-2 text-primary"></i>My Profile</h1>
    <p class="page-subtitle">Manage your account information and academic details.</p>
  </div>
</div>

<?php showFlash(); ?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger mb-4">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <div><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div>
  </div>
<?php endif; ?>

<div class="row g-4">

  <!-- LEFT: Avatar + quick info -->
  <div class="col-lg-4">
    <div class="card text-center" style="padding:32px 24px;">
      <div style="width:88px;height:88px;border-radius:50%;background:#eff6ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:36px;font-weight:800;margin:0 auto 16px;">
        <?= strtoupper(mb_substr($user['full_name'], 0, 1)) ?>
      </div>
      <h5 style="font-weight:700;color:#0f172a;margin-bottom:4px;"><?= e($user['full_name']) ?></h5>
      <div style="font-size:12px;color:#64748b;margin-bottom:6px;"><?= e($user['email']) ?></div>
      <span class="badge badge-student mb-3"><?= e($user['student_code'] ?? 'N/A') ?></span>

      <?php if ($profile): ?>
      <?php
        // Decode JSON lists if stored
        $actList = !empty($profile['activities_list'])
            ? json_decode($profile['activities_list'], true) : [];
        $resList = !empty($profile['research_list'])
            ? json_decode($profile['research_list'], true) : [];
      ?>
      <div style="border-top:1px solid #f1f5f9;padding-top:16px;text-align:left;">
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f8fafc;">
          <span style="font-size:12px;color:#94a3b8;font-weight:600;">Faculty</span>
          <span style="font-size:13px;color:#334155;font-weight:500;"><?= e($profile['faculty'] ?? '–') ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f8fafc;">
          <span style="font-size:12px;color:#94a3b8;font-weight:600;">Major</span>
          <span style="font-size:13px;color:#334155;font-weight:500;"><?= e($profile['major'] ?? '–') ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f8fafc;">
          <span style="font-size:12px;color:#94a3b8;font-weight:600;">GPA</span>
          <span style="font-size:15px;font-weight:800;color:#2563eb;"><?= e($profile['gpa'] ?? '–') ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f8fafc;">
          <span style="font-size:12px;color:#94a3b8;font-weight:600;">Failed Subjects</span>
          <?php if (($profile['failed_subjects'] ?? 0) > 0): ?>
            <span style="font-size:12px;font-weight:700;color:#dc2626;">⚠ <?= $profile['failed_subjects'] ?> subject(s)</span>
          <?php else: ?>
            <span class="badge badge-eligible" style="font-size:11px;">✓ No F grades</span>
          <?php endif; ?>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f8fafc;">
          <span style="font-size:12px;color:#94a3b8;font-weight:600;">Language Cert</span>
          <?php if (!empty($profile['language_certificate'])): ?>
            <span class="badge badge-eligible" style="font-size:11px;">✓ Yes</span>
          <?php else: ?>
            <span class="badge badge-ineligible" style="font-size:11px;">✗ None</span>
          <?php endif; ?>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f8fafc;">
          <span style="font-size:12px;color:#94a3b8;font-weight:600;">Activities</span>
          <span style="font-size:13px;color:#334155;font-weight:600;"><?= $profile['activities_count'] ?? 0 ?> activity/activities</span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;">
          <span style="font-size:12px;color:#94a3b8;font-weight:600;">Research Topics</span>
          <span style="font-size:13px;color:#334155;font-weight:600;"><?= $profile['research_count'] ?? 0 ?> topic(s)</span>
        </div>
      </div>
      <?php else: ?>
      <div class="alert alert-warning" style="font-size:12px;">
        <i class="bi bi-exclamation-triangle me-1"></i>
        No academic profile yet. Please fill in the form below.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- RIGHT: Forms -->
  <div class="col-lg-8">

    <!-- Account Info Form -->
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title mb-4"><i class="bi bi-shield-lock me-2 text-primary"></i>Account Information</h5>
        <form method="POST" novalidate>
          <input type="hidden" name="section" value="account">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="full_name" class="form-control" required
                     value="<?= e($user['full_name']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Student Code</label>
              <input type="text" class="form-control" value="<?= e($user['student_code'] ?? '') ?>" disabled>
            </div>
            <div class="col-12">
              <label class="form-label">Email Address <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" required
                     value="<?= e($user['email']) ?>">
            </div>
            <div class="col-12"><hr style="border-color:#f1f5f9;margin:4px 0;"></div>
            <div class="col-md-6">
              <label class="form-label">Current Password</label>
              <input type="password" name="current_password" class="form-control"
                     placeholder="Required to change password">
            </div>
            <div class="col-md-6">
              <label class="form-label">New Password</label>
              <input type="password" name="new_password" class="form-control"
                     placeholder="Leave blank to keep current">
            </div>
          </div>
          <div class="mt-4">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-save"></i> Save Account Info
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Academic Profile Form -->
    <div class="card">
      <div class="card-body">
        <h5 class="card-title mb-1"><i class="bi bi-mortarboard me-2 text-primary"></i>Academic Profile</h5>
        <p style="font-size:12.5px;color:#64748b;margin-bottom:20px;">
          This information is used to automatically verify eligibility and calculate scholarship scores.
        </p>

        <!-- Eligibility notice -->
        <?php if ($profile):
          $langOk  = !empty($profile['language_certificate']);
          $failOk  = ($profile['failed_subjects'] ?? 0) == 0;
          $gpaOk   = ($profile['gpa'] ?? 0) >= 3.2;
          $actOk   = ($profile['activities_count'] ?? 0) >= 2;
          $allOk   = $langOk && $failOk && $gpaOk && $actOk;
        ?>
        <div style="background:<?= $allOk ? '#f0fdf4' : '#fffbeb' ?>;border:1.5px solid <?= $allOk ? '#86efac' : '#fde68a' ?>;border-radius:10px;padding:12px 16px;margin-bottom:20px;">
          <div style="font-size:12px;font-weight:700;color:<?= $allOk ? '#16a34a' : '#92400e' ?>;margin-bottom:8px;">
            <?= $allOk ? '✅ Profile is currently eligible for scholarship' : '⚠ Profile is not yet eligible – please complete the requirements' ?>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
            <?php
            $checks = [
              [$failOk,  'No F grades', 'bi-x-circle-fill'],
              [$gpaOk,   'GPA ≥ 3.2 (' . ($profile['gpa'] ?? 0) . ')', 'bi-graph-up-arrow'],
              [$actOk,   'Activities ≥ 2 (' . ($profile['activities_count'] ?? 0) . ')', 'bi-people-fill'],
              [$langOk,  'Language certificate', 'bi-translate'],
            ];
            foreach ($checks as [$ok, $lbl, $icon]): ?>
            <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:<?= $ok ? '#16a34a' : '#dc2626' ?>;">
              <i class="bi <?= $ok ? 'bi-check-circle-fill' : $icon ?>"></i> <?= $lbl ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate>
          <input type="hidden" name="section" value="academic">
          <?php
            // Pre-fill lists from JSON storage
            $savedActList = [];
            $savedResList = [];
            if ($profile) {
                $savedActList = !empty($profile['activities_list']) ? json_decode($profile['activities_list'], true) ?? [] : [];
                $savedResList = !empty($profile['research_list'])   ? json_decode($profile['research_list'],   true) ?? [] : [];
            }
          ?>
          <div class="row g-3">

            <!-- Faculty & Major -->
            <div class="col-md-6">
              <label class="form-label">Faculty <span class="text-danger">*</span></label>
              <input type="text" name="faculty" class="form-control" required
                     value="<?= e($profile['faculty'] ?? '') ?>"
                     placeholder="e.g. Information Technology">
            </div>
            <div class="col-md-6">
              <label class="form-label">Major <span class="text-danger">*</span></label>
              <input type="text" name="major" class="form-control" required
                     value="<?= e($profile['major'] ?? '') ?>"
                     placeholder="e.g. Software Engineering">
            </div>

            <!-- GPA -->
            <div class="col-md-4">
              <label class="form-label">
                GPA (0.00–4.00)
                <span style="font-size:11px;color:#94a3b8;">– min 3.2 required</span>
              </label>
              <input type="number" name="gpa" class="form-control"
                     min="0" max="4" step="0.01"
                     value="<?= e($profile['gpa'] ?? '0.00') ?>">
              <?php if (isset($profile['gpa']) && $profile['gpa'] < 3.2): ?>
                <div style="font-size:11px;color:#dc2626;margin-top:4px;">
                  <i class="bi bi-exclamation-triangle-fill me-1"></i>Current GPA does not meet the 3.2 threshold
                </div>
              <?php endif; ?>
            </div>

            <!-- Failed Subjects -->
            <div class="col-md-4">
              <label class="form-label">
                Number of F-graded subjects
                <span style="font-size:11px;color:#94a3b8;">– must be 0</span>
              </label>
              <input type="number" name="failed_subjects" class="form-control"
                     min="0" value="<?= e($profile['failed_subjects'] ?? 0) ?>">
              <?php if (($profile['failed_subjects'] ?? 0) > 0): ?>
                <div style="font-size:11px;color:#dc2626;margin-top:4px;">
                  <i class="bi bi-x-circle-fill me-1"></i>Prerequisite: must be 0
                </div>
              <?php endif; ?>
            </div>

            <!-- Language Certificate -->
            <div class="col-md-4">
              <label class="form-label">
                Language certificate
                <span style="font-size:11px;color:#94a3b8;">– mandatory</span>
              </label>
              <div style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:8px;padding:10px 14px;display:flex;align-items:center;gap:10px;cursor:pointer;"
                   onclick="document.getElementById('langCert').click()">
                <input class="form-check-input" type="checkbox" name="language_certificate"
                       id="langCert" value="1"
                       <?= !empty($profile['language_certificate']) ? 'checked' : '' ?>
                       style="width:18px;height:18px;cursor:pointer;flex-shrink:0;">
                <label for="langCert" style="font-size:13px;color:#334155;cursor:pointer;margin:0;">
                  <i class="bi bi-translate me-1 text-primary"></i>
                  I have a language certificate (IELTS / TOEIC / B1 / ...)
                </label>
              </div>
            </div>

            <!-- Activities List -->
            <div class="col-12">
              <label class="form-label">
                <i class="bi bi-people me-1 text-primary"></i>
                List of extra-curricular activities
                <span style="font-size:11px;color:#94a3b8;">– min 2 activities required (one per line)</span>
              </label>
              <textarea name="activities_list" id="activitiesList" class="form-control"
                        rows="4"
                        placeholder="Example:&#10;Programming Club&#10;Student Union Executive Committee&#10;Green Summer Volunteer Program"><?= e(implode("\n", $savedActList)) ?></textarea>
              <div style="font-size:11.5px;color:#64748b;margin-top:6px;" id="actCount">
                <i class="bi bi-list-check me-1"></i>
                Currently has <strong id="actNum"><?= count($savedActList) ?></strong> activity/activities
                <?php if (count($savedActList) < 2): ?>
                  <span style="color:#dc2626;"> – need <?= 2 - count($savedActList) ?> more</span>
                <?php else: ?>
                  <span style="color:#16a34a;"> ✓ eligible</span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Research Topics List -->
            <div class="col-12">
              <label class="form-label">
                <i class="bi bi-journal-text me-1 text-primary"></i>
                List of scientific research topics
                <span style="font-size:11px;color:#94a3b8;">– optional, but increases score (+7.5 points/topic, max 15)</span>
              </label>
              <textarea name="research_list" id="researchList" class="form-control"
                        rows="3"
                        placeholder="Example:&#10;AI Applications in Education&#10;Blockchain Security"><?= e(implode("\n", $savedResList)) ?></textarea>
              <div style="font-size:11.5px;color:#64748b;margin-top:6px;" id="resCount">
                <i class="bi bi-journal me-1"></i>
                Currently has <strong id="resNum"><?= count($savedResList) ?></strong> topic(s)
              </div>
            </div>

          </div><!-- /row -->

          <!-- Score preview -->
          <div id="scorePreview" style="background:#eff6ff;border-radius:10px;padding:14px 18px;margin-top:20px;display:none;">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin-bottom:10px;">
              Estimated Scholarship Score
            </div>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;">
              <div style="text-align:center;">
                <div style="font-size:11px;color:#64748b;">Academic (GPA)</div>
                <div id="scGpa" style="font-size:18px;font-weight:800;color:#2563eb;">–</div>
                <div style="font-size:10px;color:#94a3b8;">/ 50</div>
              </div>
              <div style="text-align:center;">
                <div style="font-size:11px;color:#64748b;">Language</div>
                <div id="scLang" style="font-size:18px;font-weight:800;color:#7c3aed;">–</div>
                <div style="font-size:10px;color:#94a3b8;">/ 15</div>
              </div>
              <div style="text-align:center;">
                <div style="font-size:11px;color:#64748b;">Activities</div>
                <div id="scAct" style="font-size:18px;font-weight:800;color:#0891b2;">–</div>
                <div style="font-size:10px;color:#94a3b8;">/ 20</div>
              </div>
              <div style="text-align:center;">
                <div style="font-size:11px;color:#64748b;">Research</div>
                <div id="scRes" style="font-size:18px;font-weight:800;color:#16a34a;">–</div>
                <div style="font-size:10px;color:#94a3b8;">/ 15</div>
              </div>
            </div>
            <div style="border-top:1px solid #bfdbfe;margin-top:10px;padding-top:10px;text-align:center;">
              <span style="font-size:12px;color:#64748b;">Total Expected:</span>
              <span id="scTotal" style="font-size:22px;font-weight:800;color:#2563eb;margin-left:8px;">–</span>
              <span style="font-size:12px;color:#94a3b8;">/ 100</span>
            </div>
          </div>

          <div class="mt-4 d-flex gap-3">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-save"></i> Save Academic Profile
            </button>
            <button type="button" onclick="calcPreview()" class="btn btn-secondary">
              <i class="bi bi-calculator"></i> Estimate Score
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
// ── Live activity / research counter ───────────────────────────
function countLines(id) {
    const val = document.getElementById(id).value.trim();
    return val === '' ? 0 : val.split(/\n/).filter(l => l.trim() !== '').length;
}

document.getElementById('activitiesList')?.addEventListener('input', function () {
    const n = countLines('activitiesList');
    document.getElementById('actNum').textContent = n;
    const hint = document.getElementById('actCount');
    const extra = hint.querySelector('span');
    if (extra) {
        if (n < 2) {
            extra.style.color = '#dc2626';
            extra.textContent = ' - need ' + (2 - n) + ' more';
        } else {
            extra.style.color = '#16a34a';
            extra.textContent = ' ✓ eligible';
        }
    }
});

document.getElementById('researchList')?.addEventListener('input', function () {
    const n = countLines('researchList');
    document.getElementById('resNum').textContent = n;
});

// ── Score preview calculator ────────────────────────────────────
function calcPreview() {
    const gpa    = parseFloat(document.querySelector('input[name="gpa"]')?.value) || 0;
    const lang   = document.getElementById('langCert')?.checked ? 15 : 0;
    const actN   = countLines('activitiesList');
    const resN   = countLines('researchList');

    const scGpa  = Math.round(Math.min(Math.max(gpa, 0), 4) / 4 * 50 * 100) / 100;
    const scAct  = Math.min(actN * 4, 20);
    const scRes  = Math.min(resN * 7.5, 15);
    const total  = Math.round((scGpa + lang + scAct + scRes) * 100) / 100;

    document.getElementById('scGpa').textContent   = scGpa;
    document.getElementById('scLang').textContent  = lang;
    document.getElementById('scAct').textContent   = scAct;
    document.getElementById('scRes').textContent   = scRes;
    document.getElementById('scTotal').textContent = total;
    document.getElementById('scorePreview').style.display = 'block';
}
</script>
