<?php
$pageTitle = 'Mock Scholarship Application';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/mock_services.php';

requireLogin();
requireRole('student');

$students = require __DIR__ . '/mock_data.php';

$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : $students[0]['id'];
$current = null;
foreach ($students as $s) {
    if ($s['id'] === $studentId) { $current = $s; break; }
}
if (!$current) $current = $students[0];

// start session for drafts/applications stored in session
if (session_status() === PHP_SESSION_NONE) session_start();

$drafts = &$_SESSION['mock_drafts'];
if (!is_array($drafts)) $drafts = [];

$applications = &$_SESSION['mock_applications'];
if (!is_array($applications)) $applications = [];

$notifications = &$_SESSION['mock_notifications'];
if (!is_array($notifications)) $notifications = [];

$message = '';

// Load existing draft if any
$existingDraft = $drafts[$current['id']] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $form = [
        'full_name' => post('full_name', $current['full_name']),
        'email' => post('email', $current['email']),
        'mailing_address' => post('mailing_address', ''),
        'major' => post('major', ''),
        'gpa' => (float)post('gpa', $current['gpa']),
        'activities' => array_filter(array_map('trim', explode("\n", post('activities', implode("\n", $current['activities']))))),
        'research_topics' => array_filter(array_map('trim', explode("\n", post('research_topics', implode("\n", $current['research_topics']))))),
        'has_language_cert' => isset($_POST['has_language_cert']) ? 1 : 0,
        'files' => [],
        'saved_at' => date('Y-m-d H:i:s'),
    ];

    // Handle uploaded files (do not persist to disk for mock)
    if (!empty($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
        for ($i=0;$i<count($_FILES['documents']['name']);$i++) {
            if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_OK) {
                $form['files'][] = [
                    'name' => $_FILES['documents']['name'][$i],
                    'size' => $_FILES['documents']['size'][$i]
                ];
            }
        }
    }

    if ($action === 'save') {
        $drafts[$current['id']] = $form;
        $message = 'Draft saved.';
    } elseif ($action === 'submit') {
        // Create mock application entry
        $app = [
            'id' => count($applications) + 1,
            'student_id' => $current['id'],
            'submitted_at' => date('Y-m-d H:i:s'),
            'data' => $form,
            'status' => 'submitted',
        ];

        // Run eligibility & scoring
        $elig = checkEligibilityMock(array_merge($current, $form));
        $score = scoreStudentMock(array_merge($current, $form));
        $app['eligible'] = $elig['passed'];
        $app['eligibility_reasons'] = $elig['reasons'];
        $app['score'] = $score;

        $applications[] = $app;

        // Add notification
        $notifications[] = [
            'user_id' => $current['id'],
            'title' => $elig['passed'] ? 'Eligibility Passed' : 'Eligibility Failed',
            'message' => $elig['passed'] ? 'Your application passed initial eligibility.' : 'Your application did not meet eligibility: ' . implode('; ', $elig['reasons']),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // Clear draft
        unset($drafts[$current['id']]);

        $message = 'Application submitted. ' . ($elig['passed'] ? 'You are eligible.' : 'You are not eligible.');
    }

    // reload existing draft
    $existingDraft = $drafts[$current['id']] ?? null;
}

require_once __DIR__ . '/../includes/header.php';
//require_once __DIR__ . '/../includes/navbar.php';
?>
<?php require_once __DIR__ . '/../includes/student_header.php'; ?>

<div class="container py-4">
  <?php showFlash(); if ($message): ?>
    <div class="alert alert-info"><?= e($message) ?></div>
  <?php endif; ?>

  <div class="row">
    <div class="col-lg-8">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save">

        <div class="card mb-3">
          <div class="card-header">Personal Information</div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Full Name</label>
              <input name="full_name" class="form-control" value="<?= e($existingDraft['full_name'] ?? $current['full_name']) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Email Address</label>
              <input name="email" class="form-control" value="<?= e($existingDraft['email'] ?? $current['email']) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Mailing Address</label>
              <input name="mailing_address" class="form-control" value="<?= e($existingDraft['mailing_address'] ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header">Academic Background</div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Current Major</label>
                <input name="major" class="form-control" value="<?= e($existingDraft['major'] ?? '') ?>">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Current GPA</label>
                <input name="gpa" type="number" step="0.01" min="0" max="4" class="form-control" value="<?= e($existingDraft['gpa'] ?? $current['gpa']) ?>">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Statement of Purpose (optional)</label>
              <textarea name="sop" class="form-control" rows="4"></textarea>
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header">Required Documents</div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Upload Official Transcript</label>
              <input type="file" name="documents[]" multiple class="form-control mb-2">
              <small class="text-muted">PDF, JPG, PNG. Files are not saved in mock mode.</small>
            </div>
            <?php if (!empty($existingDraft['files'])): ?>
              <ul>
                <?php foreach ($existingDraft['files'] as $f): ?>
                  <li><?= e($f['name']) ?> (<?= round($f['size']/1024,1) ?> KB)</li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>

        <div class="d-flex gap-2 mb-4">
          <button class="btn btn-primary" onclick="this.form.action.value='save'">Save Draft</button>
          <button class="btn btn-success" type="submit" onclick="this.form.action.value='submit'">Submit Application</button>
          <a href="mock_results.php" class="btn btn-outline-secondary">View Mock Results</a>
        </div>
      </form>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header">Preview Student</div>
        <div class="card-body">
          <form method="get">
            <label class="form-label">Select Mock Student</label>
            <select name="student_id" class="form-select mb-2" onchange="this.form.submit()">
              <?php foreach ($students as $s): ?>
                <option value="<?= e($s['id']) ?>" <?= $s['id'] === $current['id'] ? 'selected' : '' ?>><?= e($s['full_name']) ?> (GPA <?= e($s['gpa']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </form>

          <dl class="row mt-3 small">
            <dt class="col-5">GPA</dt><dd class="col-7"><?= e($current['gpa']) ?></dd>
            <dt class="col-5">Failed Subjects</dt><dd class="col-7"><?= e($current['failed_subjects']) ?></dd>
            <dt class="col-5">Activities</dt><dd class="col-7"><?= e(implode(', ', $current['activities'])) ?></dd>
            <dt class="col-5">Language Cert</dt><dd class="col-7"><?= $current['has_language_cert'] ? 'Yes' : 'No' ?></dd>
          </dl>

          <div class="mt-3">
            <strong>Draft saved at:</strong>
            <div class="small text-muted"><?= e($existingDraft['saved_at'] ?? 'Never') ?></div>
          </div>

          <div class="mt-3 p-3 bg-warning bg-opacity-10 border rounded">
            <strong>Important Deadline</strong>
            <div class="small text-muted">Applications close on 2026-12-15 23:59</div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">Notifications</div>
        <div class="card-body small">
          <?php if (empty($notifications)): ?>
            <div class="text-muted">No notifications yet.</div>
          <?php else: ?>
            <ul class="list-unstyled mb-0">
              <?php foreach (array_reverse($notifications) as $n): ?>
                <li class="mb-2"><strong><?= e($n['title']) ?></strong><br><span class="text-muted"><?= e($n['message']) ?></span><br><small class="text-muted"><?= e($n['created_at']) ?></small></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
