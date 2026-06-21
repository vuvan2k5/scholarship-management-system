<?php
// ============================================================
// admin/management_mail/index.php
// Management Mail — dashboard: compose, bulk send, history.
// Official communication channel: scholarship system → students.
// ============================================================
$pageTitle = 'Management Mail';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo     = getDB();
$adminId = currentUserId();

// ── Auto-migrate tables ───────────────────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS mail_templates (
        id            INT(11)      AUTO_INCREMENT PRIMARY KEY,
        name          VARCHAR(150) NOT NULL,
        template_type ENUM('award_notification','rejection_notification','document_request','interview_invitation','custom') NOT NULL DEFAULT 'custom',
        subject       VARCHAR(255) NOT NULL,
        body_html     TEXT         NOT NULL,
        is_active     TINYINT(1)   NOT NULL DEFAULT 1,
        created_by    INT(11)      DEFAULT NULL,
        created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS mail_log (
        id             INT(11)      AUTO_INCREMENT PRIMARY KEY,
        template_id    INT(11)      DEFAULT NULL,
        recipient_id   INT(11)      NOT NULL,
        application_id INT(11)      DEFAULT NULL,
        program_id     INT(11)      DEFAULT NULL,
        subject        VARCHAR(255) NOT NULL,
        body_html      TEXT         NOT NULL,
        status         ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
        error_message  TEXT         DEFAULT NULL,
        has_attachment TINYINT(1)   NOT NULL DEFAULT 0,
        attachment_type ENUM('certificate','document','none') DEFAULT 'none',
        sent_by        INT(11)      DEFAULT NULL,
        sent_at        DATETIME     DEFAULT NULL,
        created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (recipient_id)   REFERENCES users(id)                ON DELETE CASCADE,
        FOREIGN KEY (application_id) REFERENCES applications(id)         ON DELETE SET NULL,
        FOREIGN KEY (program_id)     REFERENCES scholarship_programs(id) ON DELETE SET NULL
    )
");

// ── Seed templates if empty ───────────────────────────────────
$tplCount = (int)$pdo->query("SELECT COUNT(*) FROM mail_templates")->fetchColumn();
if ($tplCount === 0) {
    $seedSql = "INSERT INTO mail_templates (name, template_type, subject, body_html) VALUES
        ('Award Notification','award_notification','🎉 Scholarship Award — {{program_name}}','<p>Dear <strong>{{student_name}}</strong>,</p><p>We are delighted to inform you that your application for the <strong>{{program_name}}</strong> scholarship has been <strong>approved</strong>.</p><ul><li><strong>Rank:</strong> #{{rank}}</li><li><strong>Final Score:</strong> {{score}}</li><li><strong>Student ID:</strong> {{student_id}}</li></ul><p>Please log in to the portal to view your award certificate. Congratulations!</p><p>— Scholarship Management Office</p>'),
        ('Rejection Notification','rejection_notification','Scholarship Result — {{program_name}}','<p>Dear <strong>{{student_name}}</strong>,</p><p>Thank you for applying to the <strong>{{program_name}}</strong> scholarship. After careful review, your application was not selected this round. Rank: #{{rank}} · Score: {{score}}.</p><p>We encourage you to apply again next cycle.</p><p>— Scholarship Management Office</p>'),
        ('Document Request','document_request','Additional Documents Required — {{program_name}}','<p>Dear <strong>{{student_name}}</strong>,</p><p>To proceed with the evaluation of your <strong>{{program_name}}</strong> application (ID: {{student_id}}), we need additional documents. Please upload them via the portal within 7 business days.</p><p>— Scholarship Management Office</p>'),
        ('Interview Invitation','interview_invitation','Interview Invitation — {{program_name}}','<p>Dear <strong>{{student_name}}</strong>,</p><p>Congratulations! You are invited to an interview for the <strong>{{program_name}}</strong> scholarship. Details will be shared separately.</p><p>— Scholarship Management Office</p>')
    ";
    $pdo->exec($seedSql);
}

// ── Handle: Send individual / bulk email ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_action'])) {
    $action         = $_POST['send_action'];
    $tplId          = (int)($_POST['template_id'] ?? 0);
    $programId      = (int)($_POST['program_id']  ?? 0);
    $customSubject  = trim($_POST['subject']       ?? '');
    $customBody     = trim($_POST['body_html']     ?? '');
    $attachType     = in_array($_POST['attachment_type'] ?? '', ['certificate','document','none'])
                      ? $_POST['attachment_type'] : 'none';
    $hasAttach      = $attachType !== 'none' ? 1 : 0;

    // Load template body if selected
    $tplSubject = $customSubject;
    $tplBody    = $customBody;
    if ($tplId > 0) {
        $tStmt = $pdo->prepare("SELECT subject, body_html FROM mail_templates WHERE id = ?");
        $tStmt->execute([$tplId]);
        $tpl = $tStmt->fetch();
        if ($tpl) {
            if (!$tplSubject) $tplSubject = $tpl['subject'];
            if (!$tplBody)    $tplBody    = $tpl['body_html'];
        }
    }

    // Determine recipients
    $recipients = [];

    if ($action === 'send_awarded' && $programId > 0) {
        // All awarded students in program
        $rStmt = $pdo->prepare("
            SELECT a.id AS app_id, a.student_id, a.program_id,
                   u.full_name, u.student_code, u.email,
                   sp.name AS program_name,
                   rr.rank AS rank_no, rr.total_score,
                   COALESCE(ac.certificate_code,'') AS cert_code
            FROM ranking_results rr
            JOIN applications a         ON rr.application_id = a.id
            JOIN users u                ON a.student_id = u.id
            JOIN scholarship_programs sp ON a.program_id = sp.id
            LEFT JOIN award_certificates ac ON ac.application_id = a.id
            WHERE a.program_id = ? AND rr.awarded = 1
            ORDER BY rr.rank ASC
        ");
        $rStmt->execute([$programId]);
        $recipients = $rStmt->fetchAll();
    } elseif ($action === 'send_all_program' && $programId > 0) {
        // All ranked applicants in program
        $rStmt = $pdo->prepare("
            SELECT a.id AS app_id, a.student_id, a.program_id,
                   u.full_name, u.student_code, u.email,
                   sp.name AS program_name,
                   rr.rank AS rank_no, rr.total_score,
                   COALESCE(ac.certificate_code,'') AS cert_code
            FROM ranking_results rr
            JOIN applications a         ON rr.application_id = a.id
            JOIN users u                ON a.student_id = u.id
            JOIN scholarship_programs sp ON a.program_id = sp.id
            LEFT JOIN award_certificates ac ON ac.application_id = a.id
            WHERE a.program_id = ?
            ORDER BY rr.rank ASC
        ");
        $rStmt->execute([$programId]);
        $recipients = $rStmt->fetchAll();
    } elseif ($action === 'send_single') {
        $singleId = (int)($_POST['recipient_id'] ?? 0);
        $appId    = (int)($_POST['application_id'] ?? 0);
        if ($singleId > 0) {
            $rStmt = $pdo->prepare("
                SELECT a.id AS app_id, a.student_id, a.program_id,
                       u.full_name, u.student_code, u.email,
                       sp.name AS program_name,
                       rr.rank AS rank_no, rr.total_score,
                       COALESCE(ac.certificate_code,'') AS cert_code
                FROM users u
                JOIN applications a         ON a.student_id = u.id
                JOIN scholarship_programs sp ON a.program_id = sp.id
                LEFT JOIN ranking_results rr ON rr.application_id = a.id
                LEFT JOIN award_certificates ac ON ac.application_id = a.id
                WHERE u.id = ? AND (? = 0 OR a.id = ?)
                ORDER BY a.id DESC LIMIT 1
            ");
            $rStmt->execute([$singleId, $appId, $appId]);
            $r = $rStmt->fetch();
            if ($r) $recipients = [$r];
        }
    }

    $sentCount   = 0;
    $failedCount = 0;

    foreach ($recipients as $rec) {
        // Render variables
        $vars = [
            '{{student_name}}' => $rec['full_name']      ?? '',
            '{{student_id}}'   => $rec['student_code']   ?? '',
            '{{program_name}}' => $rec['program_name']   ?? '',
            '{{rank}}'         => $rec['rank_no']         ?? '—',
            '{{score}}'        => number_format((float)($rec['total_score'] ?? 0), 2),
        ];
        $renderedSubject = str_replace(array_keys($vars), array_values($vars), $tplSubject);
        $renderedBody    = str_replace(array_keys($vars), array_values($vars), $tplBody);

        // Simulate send (in production replace with mail() / PHPMailer / SMTP)
        $mailSent  = true; // Simulated — see note below
        $mailError = null;

        // In XAMPP/dev: use PHP mail() if configured, else log as 'sent' (simulated)
        if ($rec['email'] && function_exists('mail')) {
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: Scholarship System <no-reply@scholarship.local>\r\n";
            $mailSent = @mail($rec['email'], $renderedSubject, $renderedBody, $headers);
            if (!$mailSent) $mailError = 'mail() returned false — check SMTP config.';
        }

        $pdo->prepare("
            INSERT INTO mail_log
                (template_id, recipient_id, application_id, program_id,
                 subject, body_html, status, error_message,
                 has_attachment, attachment_type, sent_by, sent_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $tplId ?: null,
            $rec['student_id'],
            $rec['app_id']    ?? null,
            $rec['program_id'] ?? null,
            $renderedSubject,
            $renderedBody,
            $mailSent ? 'sent' : 'failed',
            $mailError,
            $hasAttach,
            $attachType,
            $adminId,
        ]);

        if ($mailSent) $sentCount++; else $failedCount++;
    }

    if ($sentCount + $failedCount === 0) {
        setFlash('warning', 'No recipients found for the selected action/program.');
    } else {
        $msg = "{$sentCount} email(s) sent successfully.";
        if ($failedCount) $msg .= " {$failedCount} failed.";
        setFlash($failedCount ? 'warning' : 'success', $msg);
    }

    header('Location: index.php');
    exit;
}

// ── Fetch data for page ───────────────────────────────────────
$templates = $pdo->query("SELECT * FROM mail_templates WHERE is_active = 1 ORDER BY template_type ASC")->fetchAll();
$programs  = $pdo->query("SELECT id, name FROM scholarship_programs ORDER BY name ASC")->fetchAll();
$students  = $pdo->query("
    SELECT u.id, u.full_name, u.student_code
    FROM users u
    WHERE u.role = 'student'
    ORDER BY u.full_name ASC
")->fetchAll();

// ── Email history ─────────────────────────────────────────────
$filterStatus  = trim($_GET['status']  ?? '');
$filterProgram = (int)($_GET['program_id'] ?? 0);
$searchLog     = trim($_GET['search']  ?? '');

$logWhere  = [];
$logParams = [];
if ($filterStatus)  { $logWhere[] = "ml.status = ?";     $logParams[] = $filterStatus; }
if ($filterProgram) { $logWhere[] = "ml.program_id = ?"; $logParams[] = $filterProgram; }
if ($searchLog) {
    $like = "%$searchLog%";
    $logWhere[]  = "(u.full_name LIKE ? OR ml.subject LIKE ?)";
    $logParams[] = $like; $logParams[] = $like;
}
$logWhereSql = $logWhere ? 'WHERE ' . implode(' AND ', $logWhere) : '';

$mailLog = $pdo->prepare("
    SELECT ml.*, u.full_name AS recipient_name, u.student_code,
           sp.name AS program_name, mt.name AS template_name,
           adm.full_name AS sent_by_name
    FROM mail_log ml
    JOIN users u              ON ml.recipient_id = u.id
    LEFT JOIN scholarship_programs sp ON ml.program_id = sp.id
    LEFT JOIN mail_templates mt ON ml.template_id = mt.id
    LEFT JOIN users adm       ON ml.sent_by = adm.id
    $logWhereSql
    ORDER BY ml.created_at DESC
    LIMIT 200
");
$mailLog->execute($logParams);
$mailLog = $mailLog->fetchAll();

// ── Stats ─────────────────────────────────────────────────────
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'sent')    AS sent,
        SUM(status = 'failed')  AS failed,
        SUM(status = 'pending') AS pending
    FROM mail_log
")->fetch();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>
<div class="container py-4">
<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">
      <i class="bi bi-envelope-paper me-2" style="color:var(--primary);"></i>Management Mail
    </h1>
    <p class="page-subtitle">
      Official scholarship communication channel — compose, bulk-send, track delivery.
    </p>
  </div>
  <div class="d-flex gap-2">
    <a href="templates.php" class="btn btn-outline-primary" id="btn-templates">
      <i class="bi bi-layout-text-window me-1"></i>Templates
    </a>
    <button class="btn btn-primary" id="btn-compose-open"
            onclick="document.getElementById('compose-panel').scrollIntoView({behavior:'smooth'})">
      <i class="bi bi-pencil-square me-1"></i>Compose
    </button>
  </div>
</div>

<?php showFlash(); ?>

<!-- ── Workflow Banner ───────────────────────────────────────── -->
<div style="background:linear-gradient(135deg,rgba(37,99,235,.05),rgba(124,58,237,.05));
            border:1px solid rgba(37,99,235,.12);border-radius:var(--radius-md);
            padding:12px 20px;margin-bottom:24px;overflow-x:auto;">
  <div style="display:flex;align-items:center;min-width:560px;">
    <?php
    $wf = [
        ['bi-bar-chart-steps','Ranking Results',    false],
        ['bi-megaphone',      'Publish Results',    false],
        ['bi-envelope-paper', 'Send Emails',        true ],
        ['bi-award',          'Certificate Delivery',false],
    ];
    foreach ($wf as $i => [$icon, $label, $active]):
      $c  = $active ? 'var(--primary)' : 'var(--gray-300)';
      $bg = $active ? 'rgba(37,99,235,.12)' : 'transparent';
    ?>
      <?php if ($i > 0): ?><div style="flex:1;height:2px;background:var(--gray-200);margin:0 4px;"></div><?php endif; ?>
      <div style="display:flex;flex-direction:column;align-items:center;gap:3px;min-width:90px;">
        <div style="width:36px;height:36px;border-radius:50%;background:<?= $bg ?>;
                    border:2px solid <?= $c ?>;display:flex;align-items:center;justify-content:center;">
          <i class="bi <?= $icon ?>" style="color:<?= $c ?>;font-size:14px;"></i>
        </div>
        <span style="font-size:10px;font-weight:<?= $active ? '700' : '500' ?>;color:<?= $c ?>;text-align:center;"><?= $label ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── Stats Row ─────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <?php
  $statItems = [
      ['Total Sent',   (int)($stats['total']   ?? 0), 'bi-envelope',          'blue'],
      ['Delivered',    (int)($stats['sent']     ?? 0), 'bi-envelope-check',    'green'],
      ['Failed',       (int)($stats['failed']   ?? 0), 'bi-envelope-x',        'red'],
      ['Pending',      (int)($stats['pending']  ?? 0), 'bi-hourglass-split',   'yellow'],
  ];
  foreach ($statItems as [$label, $val, $icon, $color]): ?>
    <div class="col-6 col-lg-3">
      <div class="stat-card">
        <div class="stat-icon <?= $color ?>"><i class="bi <?= $icon ?>"></i></div>
        <div class="stat-body">
          <div class="stat-label"><?= $label ?></div>
          <div class="stat-value"><?= $val ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-4">

  <!-- ── LEFT: Compose Panel ──────────────────────────────── -->
  <div class="col-lg-5" id="compose-panel">
    <div class="card" style="position:sticky;top:80px;">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-pencil-square me-2" style="color:var(--primary);"></i>Compose Email
        </div>

        <form method="POST" id="compose-form">

          <!-- Template selection -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Email Template</label>
            <select name="template_id" id="tpl-select" class="form-select form-select-sm">
              <option value="">— Custom (no template) —</option>
              <?php foreach ($templates as $t): ?>
                <option value="<?= $t['id'] ?>"
                        data-subject="<?= htmlspecialchars($t['subject']) ?>"
                        data-body="<?= htmlspecialchars($t['body_html']) ?>"
                        data-type="<?= e($t['template_type']) ?>">
                  <?= e($t['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Variable legend -->
          <div style="background:rgba(37,99,235,.04);border:1px solid rgba(37,99,235,.1);
                       border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:12px;">
            <div style="font-weight:700;margin-bottom:4px;color:var(--gray-700);">
              <i class="bi bi-braces me-1" style="color:var(--primary);"></i>Dynamic Variables
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:4px;">
              <?php foreach (['{{student_name}}','{{student_id}}','{{program_name}}','{{rank}}','{{score}}'] as $v): ?>
                <code style="background:rgba(37,99,235,.08);border-radius:4px;padding:1px 6px;
                             font-size:11px;cursor:pointer;user-select:all;"
                      onclick="insertVar('<?= $v ?>')" title="Click to insert"><?= $v ?></code>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Subject -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
            <input type="text" name="subject" id="mail-subject" class="form-control form-control-sm"
                   placeholder="Email subject…" required>
          </div>

          <!-- Body -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Message Body <span class="text-danger">*</span></label>
            <textarea name="body_html" id="mail-body" class="form-control"
                      rows="8" placeholder="HTML or plain text email body…" required></textarea>
            <div class="form-text">
              Variables will be replaced automatically per recipient.
              <a href="#" onclick="previewModal(); return false;" style="color:var(--primary);">Preview →</a>
            </div>
          </div>

          <!-- Attachment -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Attachment</label>
            <select name="attachment_type" id="attach-select" class="form-select form-select-sm">
              <option value="none">No attachment</option>
              <option value="certificate">Award Certificate (PDF)</option>
              <option value="document">Supporting Documents</option>
            </select>
          </div>

          <!-- Horizontal rule -->
          <hr style="border-color:var(--gray-100);margin:14px 0;">

          <!-- Send Action -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Send To</label>
            <select name="send_action" id="send-action" class="form-select form-select-sm"
                    onchange="toggleSendFields()">
              <option value="send_awarded">All Awarded Students (Program)</option>
              <option value="send_all_program">All Applicants in Program</option>
              <option value="send_single">Specific Student</option>
            </select>
          </div>

          <!-- Program (for bulk) -->
          <div class="mb-3" id="field-program">
            <label class="form-label fw-semibold">Scholarship Program</label>
            <select name="program_id" class="form-select form-select-sm">
              <option value="">— All Programs —</option>
              <?php foreach ($programs as $p): ?>
                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Single recipient -->
          <div class="mb-3 d-none" id="field-student">
            <label class="form-label fw-semibold">Recipient Student</label>
            <select name="recipient_id" class="form-select form-select-sm">
              <option value="">— Select Student —</option>
              <?php foreach ($students as $s): ?>
                <option value="<?= $s['id'] ?>">
                  <?= e($s['full_name']) ?><?= $s['student_code'] ? ' ('.$s['student_code'].')' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="d-flex gap-2 mt-3">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="previewModal()">
              <i class="bi bi-eye me-1"></i>Preview
            </button>
            <button type="submit" class="btn btn-primary flex-fill" id="btn-send"
                    onclick="return confirm('Send emails to selected recipients?')">
              <i class="bi bi-send me-1"></i>Send Email
            </button>
          </div>
        </form>
      </div>
    </div>
  </div><!-- /col-lg-5 -->

  <!-- ── RIGHT: Email History ─────────────────────────────── -->
  <div class="col-lg-7">

    <!-- History filter bar -->
    <div class="table-card mb-3" style="padding:12px 16px;">
      <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
        <div style="flex:1;min-width:160px;">
          <label class="form-label" style="margin-bottom:3px;font-size:12px;">Search</label>
          <input type="text" name="search" class="form-control form-control-sm"
                 placeholder="Recipient, subject…" value="<?= e($searchLog) ?>">
        </div>
        <div style="min-width:130px;">
          <label class="form-label" style="margin-bottom:3px;font-size:12px;">Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="sent"    <?= $filterStatus === 'sent'    ? 'selected' : '' ?>>Sent</option>
            <option value="failed"  <?= $filterStatus === 'failed'  ? 'selected' : '' ?>>Failed</option>
            <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
          </select>
        </div>
        <div style="min-width:160px;">
          <label class="form-label" style="margin-bottom:3px;font-size:12px;">Program</label>
          <select name="program_id" class="form-select form-select-sm">
            <option value="">All Programs</option>
            <?php foreach ($programs as $p): ?>
              <option value="<?= $p['id'] ?>" <?= $filterProgram == $p['id'] ? 'selected' : '' ?>>
                <?= e($p['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="padding-top:18px;">
          <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i></button>
          <?php if ($filterStatus || $filterProgram || $searchLog): ?>
            <a href="index.php" class="btn btn-sm btn-secondary"><i class="bi bi-x-lg"></i></a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- History table -->
    <div class="table-card">
      <div class="table-card-header">
        <span class="table-card-title">
          <i class="bi bi-clock-history me-1"></i>Email History
        </span>
        <div class="d-flex gap-2 align-items-center">
          <span style="font-size:12px;color:var(--gray-400);"><?= count($mailLog) ?> records</span>
          <a href="export.php?format=excel<?= $filterStatus ? '&status='.$filterStatus : '' ?><?= $filterProgram ? '&program_id='.$filterProgram : '' ?><?= $searchLog ? '&search='.urlencode($searchLog) : '' ?>"
             class="btn btn-success" style="padding:4px 10px;font-size:12px;" id="btn-export-excel-hist">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel
          </a>
          <a href="export.php?format=pdf<?= $filterStatus ? '&status='.$filterStatus : '' ?><?= $filterProgram ? '&program_id='.$filterProgram : '' ?><?= $searchLog ? '&search='.urlencode($searchLog) : '' ?>"
             class="btn btn-danger" style="padding:4px 10px;font-size:12px;" id="btn-export-pdf-hist">
            <i class="bi bi-file-earmark-pdf me-1"></i>PDF
          </a>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table" style="font-size:12.5px;" id="mail-history-table">
          <thead>
            <tr>
              <th>Recipient</th>
              <th>Subject</th>
              <th>Template</th>
              <th style="text-align:center;">Attach</th>
              <th>Status</th>
              <th>Sent At</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($mailLog)): ?>
              <tr>
                <td colspan="7">
                  <div class="empty-state" style="padding:40px 16px;">
                    <span class="empty-state-icon"><i class="bi bi-envelope-open"></i></span>
                    <div class="empty-state-title">No emails sent yet</div>
                    <div class="empty-state-text">Compose an email in the left panel to get started.</div>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($mailLog as $m):
                $statusColor = match($m['status']) {
                    'sent'    => 'badge-eligible',
                    'failed'  => 'badge-ineligible',
                    default   => 'badge-warning',
                };
                $statusIcon  = match($m['status']) {
                    'sent'    => 'bi-check-circle-fill',
                    'failed'  => 'bi-x-circle-fill',
                    default   => 'bi-hourglass-split',
                };
              ?>
                <tr>
                  <td>
                    <strong style="font-size:12.5px;"><?= e($m['recipient_name']) ?></strong>
                    <?php if ($m['student_code']): ?>
                      <div style="font-size:10.5px;color:var(--gray-400);"><?= e($m['student_code']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                      title="<?= e($m['subject']) ?>">
                    <?= e($m['subject']) ?>
                  </td>
                  <td style="font-size:11.5px;color:var(--gray-400);">
                    <?= $m['template_name'] ? e($m['template_name']) : '<em>Custom</em>' ?>
                  </td>
                  <td style="text-align:center;">
                    <?php if ($m['has_attachment']): ?>
                      <i class="bi bi-paperclip" style="color:var(--info);"
                         title="<?= ucfirst($m['attachment_type']) ?>"></i>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td>
                    <span class="badge <?= $statusColor ?>" style="font-size:10.5px;">
                      <i class="bi <?= $statusIcon ?> me-1"></i><?= ucfirst($m['status']) ?>
                    </span>
                    <?php if ($m['error_message']): ?>
                      <div style="font-size:10px;color:var(--danger);margin-top:2px;"
                           title="<?= e($m['error_message']) ?>">
                        <i class="bi bi-exclamation-triangle"></i> <?= e(substr($m['error_message'], 0, 30)) ?>…
                      </div>
                    <?php endif; ?>
                  </td>
                  <td style="white-space:nowrap;color:var(--gray-400);font-size:11px;">
                    <?= $m['sent_at'] ? e(date('d M Y, H:i', strtotime($m['sent_at']))) : '—' ?>
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-outline-primary btn-action"
                            onclick="viewEmail(<?= htmlspecialchars(json_encode([
                                'subject'  => $m['subject'],
                                'body'     => $m['body_html'],
                                'to'       => $m['recipient_name'],
                                'status'   => $m['status'],
                                'sent_by'  => $m['sent_by_name'] ?? '—',
                                'sent_at'  => $m['sent_at'] ? date('d M Y H:i', strtotime($m['sent_at'])) : '—',
                                'attach'   => $m['attachment_type'],
                            ])) ?>)"
                            id="view-mail-<?= $m['id'] ?>"
                            title="View email">
                      <i class="bi bi-eye"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /col-lg-7 -->
</div><!-- /row -->

<!-- ── Preview Modal ─────────────────────────────────────────── -->
<div id="preview-modal" style="display:none;position:fixed;inset:0;z-index:9999;
     background:rgba(0,0,0,.55);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:14px;width:min(680px,96vw);max-height:88vh;
               overflow-y:auto;padding:28px 28px 20px;box-shadow:0 20px 60px rgba(0,0,0,.3);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <div style="font-weight:800;font-size:15px;">
        <i class="bi bi-eye me-2" style="color:var(--primary);"></i>Email Preview
      </div>
      <button onclick="closePreview()" class="btn btn-sm btn-secondary">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <!-- Source indicator -->
    <div id="preview-source-badge" style="margin-bottom:10px;"></div>
    <div style="background:var(--gray-50);border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12.5px;">
      <strong>Subject:</strong> <span id="preview-subject" style="color:var(--primary);"></span>
    </div>
    <div style="border:1px solid var(--gray-200);border-radius:8px;padding:20px;background:#fff;"
         id="preview-body"></div>
    <div style="margin-top:16px;text-align:right;">
      <button onclick="closePreview()" class="btn btn-secondary btn-sm">Close Preview</button>
    </div>
  </div>
</div>

<!-- ── View Email Modal ───────────────────────────────────────── -->
<div id="view-modal" style="display:none;position:fixed;inset:0;z-index:9999;
     background:rgba(0,0,0,.55);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:14px;width:min(700px,96vw);max-height:88vh;
               overflow-y:auto;padding:28px 28px 20px;box-shadow:0 20px 60px rgba(0,0,0,.3);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <div style="font-weight:800;font-size:15px;">
        <i class="bi bi-envelope me-2" style="color:var(--primary);"></i>Sent Email
      </div>
      <button onclick="document.getElementById('view-modal').style.display='none'"
              class="btn btn-sm btn-secondary"><i class="bi bi-x-lg"></i></button>
    </div>
    <div id="view-meta" style="background:var(--gray-50);border-radius:8px;
                                 padding:12px 14px;font-size:12.5px;margin-bottom:14px;
                                 display:grid;grid-template-columns:auto 1fr;gap:4px 12px;"></div>
    <div id="view-body" style="border:1px solid var(--gray-200);border-radius:8px;
                                 padding:20px;background:#fff;"></div>
  </div>
</div>

<script>
// ── Template auto-fill ────────────────────────────────────────
document.getElementById('tpl-select').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (opt.value) {
        document.getElementById('mail-subject').value = opt.dataset.subject || '';
        document.getElementById('mail-body').value    = opt.dataset.body    || '';
    }
});

// ── Toggle send fields ────────────────────────────────────────
function toggleSendFields() {
    const v = document.getElementById('send-action').value;
    document.getElementById('field-program').classList.toggle('d-none', v === 'send_single');
    document.getElementById('field-student').classList.toggle('d-none', v !== 'send_single');
}

// ── Insert variable at cursor ─────────────────────────────────
function insertVar(v) {
    const ta = document.getElementById('mail-body');
    const start = ta.selectionStart, end = ta.selectionEnd;
    ta.value = ta.value.slice(0, start) + v + ta.value.slice(end);
    ta.selectionStart = ta.selectionEnd = start + v.length;
    ta.focus();
}

// ── Preview email (client-side sample vars + optional AJAX live preview) ─────
const sampleVars = {
    '{{student_name}}': 'Nguyễn Văn An',
    '{{student_id}}':   'SV2024001',
    '{{program_name}}': 'Excellence Scholarship 2024',
    '{{rank}}':         '3',
    '{{score}}':        '87.50',
};

function applyVars(text) {
    Object.entries(sampleVars).forEach(([k, v]) => { text = text.replaceAll(k, v); });
    return text;
}

function showPreviewModal(subject, body, liveMode) {
    document.getElementById('preview-subject').textContent = subject;
    document.getElementById('preview-body').innerHTML      = body;
    const badge = document.getElementById('preview-source-badge');
    badge.innerHTML = liveMode
        ? '<span style="background:rgba(22,163,74,.1);color:#15803d;border-radius:6px;padding:3px 10px;font-size:11.5px;font-weight:700;"><i class="bi bi-database-check me-1"></i>Live preview — real student data</span>'
        : '<span style="background:rgba(234,179,8,.1);color:#92400e;border-radius:6px;padding:3px 10px;font-size:11.5px;font-weight:700;"><i class="bi bi-person-fill me-1"></i>Sample preview — placeholder values</span>';
    document.getElementById('preview-modal').style.display = 'flex';
}

function previewModal() {
    const subj   = document.getElementById('mail-subject').value;
    const body   = document.getElementById('mail-body').value;
    const tplId  = document.getElementById('tpl-select').value;

    // Try live AJAX preview via preview_api.php
    const url = `preview_api.php?template_id=${encodeURIComponent(tplId)}&subject=${encodeURIComponent(subj)}&body_html=${encodeURIComponent(body)}`;
    fetch(url)
        .then(r => r.json())
        .then(data => {
            showPreviewModal(data.subject, data.body_html, true);
        })
        .catch(() => {
            // Fallback to client-side sample substitution
            showPreviewModal(applyVars(subj), applyVars(body), false);
        });
}

function closePreview() {
    document.getElementById('preview-modal').style.display = 'none';
}

document.getElementById('preview-modal').addEventListener('click', function(e) {
    if (e.target === this) closePreview();
});

// ── View sent email ───────────────────────────────────────────
function viewEmail(data) {
    const meta = document.getElementById('view-meta');
    const attachLabel = {certificate:'Certificate PDF', document:'Supporting Docs', none:'None'}[data.attach] || '—';
    meta.innerHTML = [
        ['To', data.to],
        ['Subject', data.subject],
        ['Status', data.status],
        ['Attachment', attachLabel],
        ['Sent By', data.sent_by],
        ['Sent At', data.sent_at],
    ].map(([l,v]) => `<span style="color:var(--gray-500);">${l}</span><span><strong>${v}</strong></span>`).join('');
    document.getElementById('view-body').innerHTML = data.body;
    document.getElementById('view-modal').style.display = 'flex';
}
document.getElementById('view-modal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php require_once '../../includes/footer.php'; ?>
