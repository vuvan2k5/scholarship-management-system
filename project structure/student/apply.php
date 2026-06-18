<?php
// ============================================================
// student/apply.php  –  Scholarship application form
// Features: Save Draft, Document Wallet picker, Eligibility Checker JS
// ============================================================
$pageTitle = 'Apply for Scholarship';

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requireRole('student');

$pdo       = getDB();
$studentId = currentUserId();
$errors    = [];

// ── Load draft if editing one ──────────────────────────────
$draftId   = isset($_GET['draft_id']) ? (int)$_GET['draft_id'] : 0;
$draftData = null;
if ($draftId > 0) {
    $stmtD = $pdo->prepare(
        "SELECT * FROM applications WHERE id=? AND student_id=? AND status='draft'"
    );
    $stmtD->execute([$draftId, $studentId]);
    $draftData = $stmtD->fetch();
    if (!$draftData) $draftId = 0; // tamper protection
}

// ── Fetch open programs (exclude already-submitted, keep own draft slot) ──
$programs = $pdo->prepare("
    SELECT sp.*
    FROM scholarship_programs sp
    WHERE sp.status = 'open'
      AND sp.id NOT IN (
          SELECT program_id FROM applications
          WHERE student_id = ? AND status != 'draft'
      )
    ORDER BY sp.name
");
$programs->execute([$studentId]);
$programs = $programs->fetchAll();

// ── Student profile & user ─────────────────────────────────
$stmtProf = $pdo->prepare("SELECT * FROM student_profiles WHERE student_id = ?");
$stmtProf->execute([$studentId]);
$profile = $stmtProf->fetch();

$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$studentId]);
$user = $stmtUser->fetch();

// ── Document Wallet ────────────────────────────────────────
$walletDocs = $pdo->prepare(
    "SELECT * FROM student_documents WHERE student_id=? ORDER BY uploaded_at DESC"
);
$walletDocs->execute([$studentId]);
$walletDocs = $walletDocs->fetchAll();

// ── Selected program for sidebar ───────────────────────────
$selId = $draftData ? (int)$draftData['program_id'] : (int)($_GET['program_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selId = (int)($_POST['program_id'] ?? $selId);
}

// ── Handle POST actions ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';   // 'draft' or 'submit'
    $programId = (int)($_POST['program_id'] ?? 0);
    $notes     = trim($_POST['draft_notes'] ?? '');

    if ($programId <= 0) $errors[] = 'Please select a scholarship program.';

    // Duplicate check (only for real submissions)
    if ($action === 'submit' && empty($errors)) {
        $dup = $pdo->prepare(
            "SELECT id FROM applications
             WHERE student_id=? AND program_id=? AND status != 'draft'"
        );
        $dup->execute([$studentId, $programId]);
        if ($dup->fetch()) $errors[] = 'You have already submitted an application for this program.';
    }

    if ($action === 'submit' && empty($errors) && !$profile) {
        $errors[] = 'Please complete your academic profile before submitting an application.';
    }

    if (empty($errors)) {
        // ── File upload helper ─────────────────────────────
        $uploadDir    = __DIR__ . '/../uploads/evidence/';
        $uploadWebDir = 'scholarship-management-system/project structure/uploads/evidence/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $allowedMime = [
            'image/jpeg','image/png','image/gif','image/webp',
            'application/pdf','application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        $maxSize = 10 * 1024 * 1024;

        // ── SAVE DRAFT ─────────────────────────────────────
        if ($action === 'draft') {
            if ($draftId > 0) {
                // Update existing draft (allow changing program)
                $pdo->prepare(
                    "UPDATE applications
                     SET program_id=?, draft_notes=?, updated_at=NOW()
                     WHERE id=? AND student_id=?"
                )->execute([$programId, $notes, $draftId, $studentId]);
                $appId = $draftId;
            } else {
                // Check if draft already exists for this program
                $existDraft = $pdo->prepare(
                    "SELECT id FROM applications
                     WHERE student_id=? AND program_id=? AND status='draft'"
                );
                $existDraft->execute([$studentId, $programId]);
                $existing = $existDraft->fetch();
                if ($existing) {
                    $appId = (int)$existing['id'];
                    $pdo->prepare(
                        "UPDATE applications SET draft_notes=?, updated_at=NOW() WHERE id=?"
                    )->execute([$notes, $appId]);
                } else {
                    $pdo->prepare(
                        "INSERT INTO applications (student_id,program_id,status,draft_notes)
                         VALUES (?,?,'draft',?)"
                    )->execute([$studentId, $programId, $notes]);
                    $appId = (int)$pdo->lastInsertId();
                }
            }
            setFlash('success', 'Draft saved! You can continue editing later.');
            header("Location: apply.php?draft_id={$appId}");
            exit;
        }

        // ── SUBMIT ─────────────────────────────────────────
        if ($action === 'submit') {
            require_once __DIR__ . '/../includes/eligibility.php';

            if ($draftId > 0) {
                // Promote draft → submitted
                $pdo->prepare(
                    "UPDATE applications
                     SET status='submitted', submitted_at=NOW(), draft_notes=NULL
                     WHERE id=? AND student_id=?"
                )->execute([$draftId, $studentId]);
                $newAppId = $draftId;
            } else {
                $pdo->prepare(
                    "INSERT INTO applications (student_id,program_id,status,submitted_at)
                     VALUES (?,?,'submitted',NOW())"
                )->execute([$studentId, $programId]);
                $newAppId = (int)$pdo->lastInsertId();
            }

            // ── Upload new evidence files ──────────────────
            if (!empty($_FILES['evidence_files']['name'][0])) {
                $cnt = count($_FILES['evidence_files']['name']);
                for ($i = 0; $i < $cnt; $i++) {
                    if ($_FILES['evidence_files']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    if ($_FILES['evidence_files']['size'][$i]  > $maxSize) continue;
                    $mime = mime_content_type($_FILES['evidence_files']['tmp_name'][$i]);
                    if (!in_array($mime, $allowedMime)) continue;
                    $orig = basename($_FILES['evidence_files']['name'][$i]);
                    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                    $stor = 'ev_' . $newAppId . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['evidence_files']['tmp_name'][$i], $uploadDir.$stor)) {
                        $filePath = 'uploads/evidence/' . $stor;

                        // Update (or insert) evidence status to pending after upload.
                        // NOTE: Current schema has no evidence_type/title columns.
                        // We emulate evidence_type by using file_type (MIME) as a discriminator for the update.
                        $stmtUpdate = $pdo->prepare("
                            UPDATE application_evidence
                            SET file_path = ?, status = 'pending', updated_at = NOW(), file_type = ?
                            WHERE application_id = ? AND file_type = ?
                        ");
                        $stmtUpdate->execute([$filePath, $mime, $newAppId, $mime]);

                        // If no rows were updated (e.g., first upload of this MIME type), insert a new evidence row.
                        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM application_evidence WHERE application_id=? AND file_type=? AND stored_name=?");
                        $stmtCount->execute([$newAppId, $mime, $stor]);
                        $exists = (int)$stmtCount->fetchColumn() > 0;

                        if (!$exists) {
                            $pdo->prepare(
                                "INSERT INTO application_evidence
                                 (application_id,student_id,original_name,stored_name,file_path,file_size,file_type)
                                 VALUES (?,?,?,?,?,?,?)"
                            )->execute([
                                $newAppId, $studentId, $orig, $stor,
                                $uploadWebDir . $stor,
                                $_FILES['evidence_files']['size'][$i], $mime,
                            ]);
                        }

                        // Notify reviewer/admins that new evidence has been uploaded.
                        $reviewers = $pdo->query("
                            SELECT id FROM users
                            WHERE role IN ('reviewer','admin')
                        ")->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($reviewers as $reviewer) {
                            $pdo->prepare(
                                "INSERT INTO notifications
                                 (user_id, title, message, type, is_read, created_at)
                                 VALUES (?, ?, ?, ?, 0, NOW())"
                            )->execute([
                                (int)$reviewer['id'],
                                'New evidence uploaded',
                                'A student has uploaded new evidence for review.',
                                'evidence',
                            ]);
                        }
                    }
                }
            }


            // ── Attach wallet documents ────────────────────
            $walletIds = $_POST['wallet_doc_ids'] ?? [];
            foreach ($walletIds as $wid) {
                $wid = (int)$wid;
                if ($wid <= 0) continue;
                // Verify ownership
                $wdoc = $pdo->prepare(
                    "SELECT * FROM student_documents WHERE id=? AND student_id=?"
                );
                $wdoc->execute([$wid, $studentId]);
                $wdoc = $wdoc->fetch();
                if (!$wdoc) continue;
                $pdo->prepare(
                    "INSERT IGNORE INTO application_evidence
                     (application_id,student_id,original_name,stored_name,file_path,file_size,file_type,wallet_doc_id)
                     VALUES (?,?,?,?,?,?,?,?)"
                )->execute([
                    $newAppId, $studentId,
                    $wdoc['original_name'], $wdoc['stored_name'],
                    $wdoc['file_path'], $wdoc['file_size'],
                    $wdoc['file_type'], $wid,
                ]);
            }

            checkEligibility($pdo, $newAppId);

            $pdo->prepare(
                "INSERT INTO notifications (user_id,title,message,type)
                 VALUES (?,'Application Submitted','Your application has been submitted successfully. Eligibility check is being processed.','info')"
            )->execute([$studentId]);

            setFlash('success', 'Application submitted successfully! The system is automatically checking your eligibility.');
            header('Location: my_applications.php');
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>


<div class="container py-4">

    <!-- PAGE HEADER -->

    <div class="mb-4">

        <h1 class="page-title">

            Apply Scholarship

        </h1>

        <p class="page-subtitle">

            Submit your scholarship application

        </p>

    </div>

    <!-- ALERTS -->

    <?php if ($error): ?>

        <div class="alert alert-danger alert-dismissible fade show">

            <?= e($error) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>

    <?php if ($success): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <?= e($success) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>

    <!-- APPLICATION FORM -->

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="card">

                <div class="card-body">

                    <form method="POST">

                        <div class="mb-4">

                            <label class="form-label">

                                Scholarship Program

                            </label>

                            <select
                                name="program_id"
                                class="form-select"
                                required
                            >

                                <option value="">

                                    Select Program

                                </option>

                                <?php foreach ($programs as $program): ?>
                                    <?php
                                        $isSelected = false;
                                        if (isset($_POST['program_id']) && intval($_POST['program_id']) === intval($program['id'])) {
                                            $isSelected = true;
                                        } elseif (isset($_GET['program_id']) && intval($_GET['program_id']) === intval($program['id'])) {
                                            $isSelected = true;
                                        }
                                    ?>
                                    <option value="<?= $program['id'] ?>" <?= $isSelected ? 'selected' : '' ?>>
                                        <?= e($program['name']) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="d-flex gap-2">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >

                                Submit Application

                            </button>

                            <a
                                href="dashboard.php"
                                class="btn btn-secondary"
                            >

                                Back

                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

=======
=======
>>>>>>> Stashed changes
<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">
      <i class="bi bi-file-earmark-plus me-2 text-primary"></i>
      <?= $draftId ? 'Continue Editing Draft' : 'Apply for Scholarship' ?>
    </h1>
    <p class="page-subtitle">
      <?= $draftId
        ? 'This application is saved as a draft. Complete it and click <strong>Submit Application</strong> to send officially.'
        : 'Fill in the details and submit your scholarship application.' ?>
    </p>
  </div>
  <div class="d-flex gap-2">
    <a href="document_wallet.php" class="btn btn-secondary">
      <i class="bi bi-folder2-open"></i> Document Wallet
    </a>
    <a href="scholarships.php" class="btn btn-secondary">
      <i class="bi bi-grid-3x3-gap"></i> All Scholarships
    </a>
  </div>
<<<<<<< Updated upstream
>>>>>>> Stashed changes
=======
>>>>>>> Stashed changes
</div>

<?php showFlash(); ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4">
  <i class="bi bi-exclamation-triangle-fill me-2"></i>
  <div><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
</div>
<?php endif; ?>

<?php if (!$profile): ?>
<div class="alert alert-warning mb-4">
  <i class="bi bi-exclamation-triangle me-2"></i>
  Your academic profile is incomplete.
  <a href="profile.php" class="fw-bold">Update your profile</a> so the system can check your eligibility.
</div>
<?php endif; ?>

<?php if ($draftId): ?>
<div class="alert mb-4" style="background:#fffbeb;border-left:4px solid #f59e0b;color:#92400e;font-size:13px;">
  <i class="bi bi-pencil-square me-2"></i>
  <strong>You are editing draft #<?= $draftId ?>.</strong>
  Click <strong>Save Draft</strong> to save or <strong>Submit Application</strong> to send officially.
</div>
<?php endif; ?>

<?php if (empty($programs) && !$draftId): ?>
<div class="card">
  <div class="card-body">
    <div class="empty-state">
      <div class="empty-state-icon"><i class="bi bi-check2-circle"></i></div>
      <div class="empty-state-title">All programs applied</div>
      <div class="empty-state-text">You have already submitted applications for all open programs, or no programs are currently available.</div>
      <a href="my_applications.php" class="btn btn-primary">View my applications</a>
    </div>
  </div>
</div>
<?php else: ?>

<?php
// Profile data serialized for JS Eligibility Checker
$profileJson = $profile ? json_encode([
    'gpa'                  => (float)$profile['gpa'],
    'activities_count'     => (int)$profile['activities_count'],
    'failed_subjects'      => (int)$profile['failed_subjects'],
    'research_count'       => (int)($profile['research_count'] ?? 0),
    'language_certificate' => (int)($profile['language_certificate'] ?? 0),
    'family_income'        => (float)($profile['family_income'] ?? 0),
], JSON_UNESCAPED_UNICODE) : 'null';
?>

<div class="row g-4">
<!-- ══════════════════════════════════════════
     LEFT COLUMN – Application Form
════════════════════════════════════════════ -->
<div class="col-lg-7">
<form method="POST" id="applyForm" enctype="multipart/form-data">
  <input type="hidden" name="action" id="formAction" value="submit">
  <?php if ($draftId): ?>
  <input type="hidden" name="draft_id" value="<?= $draftId ?>">
  <?php endif; ?>

  <!-- ── Step 1: Chọn chương trình ────────────────────────── -->
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="card-title mb-4">
        <span class="step-badge">1</span> Select Scholarship Program
      </h5>
      <div class="mb-3">
        <label class="form-label">Program <span class="text-danger">*</span></label>
        <select name="program_id" id="programSelect" class="form-select" required
                onchange="loadProgramDetails(this.value)">
          <option value="">— Select a program —</option>
          <?php foreach ($programs as $prog): ?>
            <option value="<?= $prog['id'] ?>"
              <?= $selId === (int)$prog['id'] ? 'selected' : '' ?>>
              <?= e($prog['name']) ?>
              (<?= $prog['slots'] ?> suất · đến <?= date('d/m/Y', strtotime($prog['end_date'])) ?>)
            </option>
          <?php endforeach; ?>
          <?php if ($draftData && !in_array((int)$draftData['program_id'], array_column($programs,'id'))): ?>
            <?php
              $dp = $pdo->prepare("SELECT * FROM scholarship_programs WHERE id=?");
              $dp->execute([(int)$draftData['program_id']]);
              $dpRow = $dp->fetch();
              if ($dpRow):
            ?>
            <option value="<?= $dpRow['id'] ?>" selected><?= e($dpRow['name']) ?> (draft)</option>
            <?php endif; ?>
          <?php endif; ?>
        </select>
        <div class="mt-2" style="font-size:12px;color:#94a3b8;">
          <i class="bi bi-info-circle me-1"></i>
          Programs you have already submitted an official application for are hidden from this list.
        </div>
      </div>

      <!-- Eligibility Checker Warning Box -->
      <div id="eligibilityWarning" style="display:none;"></div>
    </div>
  </div>

  <!-- ── Step 2: Hồ sơ học thuật (read-only) ──────────────── -->
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="card-title mb-1">
        <span class="step-badge">2</span> Your Academic Profile
      </h5>
      <p style="font-size:12.5px;color:#64748b;margin-bottom:20px;margin-left:38px;">
        This data is used to automatically check eligibility. <a href="profile.php">Update your profile</a> if needed.
      </p>

      <?php if ($profile): ?>
      <div class="row g-3">
        <div class="col-sm-6">
          <div class="info-box"><div class="info-box-label">Full Name</div>
            <div class="info-box-value"><?= e($user['full_name']) ?></div></div>
        </div>
        <div class="col-sm-6">
          <div class="info-box"><div class="info-box-label">Student ID</div>
            <div class="info-box-value"><?= e($user['student_code'] ?? '—') ?></div></div>
        </div>
        <div class="col-sm-6">
          <div class="info-box"><div class="info-box-label">Faculty / Major</div>
            <div class="info-box-value"><?= e($profile['faculty']) ?> · <?= e($profile['major']) ?></div></div>
        </div>
        <div class="col-sm-6">
          <div class="info-box info-box-blue"><div class="info-box-label">GPA</div>
            <div style="font-size:22px;font-weight:800;color:#1D4ED8;"><?= e($profile['gpa']) ?></div></div>
        </div>
        <div class="col-sm-4">
          <div class="info-box text-center"><div class="info-box-label">Activities</div>
            <div class="info-box-value"><?= e($profile['activities_count']) ?></div></div>
        </div>
        <div class="col-sm-4">
          <div class="info-box text-center"><div class="info-box-label">Research</div>
            <div class="info-box-value"><?= e($profile['research_count'] ?? 0) ?></div></div>
        </div>
        <div class="col-sm-4">
          <div class="info-box text-center"
               style="background:<?= ($profile['failed_subjects']??0)>0?'#fef2f2':'#f0fdf4' ?>;">
            <div class="info-box-label">Failed Subjects</div>
            <div style="font-size:20px;font-weight:800;color:<?= ($profile['failed_subjects']??0)>0?'#dc2626':'#16a34a' ?>;">
              <?= e($profile['failed_subjects'] ?? 0) ?>
            </div>
          </div>
        </div>
      </div>
      <?php else: ?>
      <div class="alert alert-warning mb-0">
        <i class="bi bi-person-exclamation me-2"></i>
        No academic profile found. <a href="profile.php" class="fw-bold">Create your profile</a> before submitting an application.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Step 3: Tài liệu minh chứng ─────────────────────── -->
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="card-title mb-1">
        <span class="step-badge">3</span> Supporting Documents
      </h5>
      <p style="font-size:12.5px;color:#64748b;margin-bottom:16px;margin-left:38px;">
        Attach documents (transcripts, certificates, awards, etc.) for the review committee.
      </p>

      <!-- Tab switcher -->
      <div style="display:flex;gap:8px;margin-bottom:16px;">
        <button type="button" class="doc-tab-btn active" onclick="switchDocTab('upload')" id="tabUpload">
          <i class="bi bi-cloud-upload me-1"></i> Upload new file
        </button>
        <button type="button" class="doc-tab-btn" onclick="switchDocTab('wallet')" id="tabWallet">
          <i class="bi bi-folder2-open me-1"></i> My Document Wallet
          <?php if (!empty($walletDocs)): ?>
            <span style="background:#1D4ED8;color:#fff;border-radius:10px;padding:1px 7px;font-size:10px;margin-left:4px;">
              <?= count($walletDocs) ?>
            </span>
          <?php endif; ?>
        </button>
      </div>

      <!-- Tab: Upload mới -->
      <div id="tabPanelUpload">
        <div id="dropZone" class="drop-zone"
             onclick="document.getElementById('evidenceInput').click()"
             ondragover="handleDragOver(event)"
             ondragleave="handleDragLeave(event)"
             ondrop="handleDrop(event)">
          <div style="font-size:36px;margin-bottom:8px;">📎</div>
          <div style="font-size:14px;font-weight:600;color:#334155;margin-bottom:4px;">
            Click to select or drag &amp; drop files here
          </div>
          <div style="font-size:12px;color:#94a3b8;">PDF, Word, Images (JPG, PNG) · Max 10MB per file</div>
        </div>
        <input type="file" id="evidenceInput" name="evidence_files[]" multiple
               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp"
               style="display:none;" onchange="previewFiles(this.files)">
        <div id="filePreviewList" style="margin-top:12px;"></div>
      </div>

      <!-- Tab: Kho tài liệu -->
      <div id="tabPanelWallet" style="display:none;">
        <?php if (empty($walletDocs)): ?>
          <div style="text-align:center;padding:30px;color:#94a3b8;font-size:13px;">
            <i class="bi bi-folder-x" style="font-size:32px;display:block;margin-bottom:10px;"></i>
            Document wallet is empty.
            <a href="document_wallet.php" class="d-block mt-2 btn btn-sm btn-outline-primary">
              <i class="bi bi-plus-lg"></i> Upload documents
            </a>
          </div>
        <?php else: ?>
          <div style="font-size:12px;color:#64748b;margin-bottom:10px;">
            <i class="bi bi-info-circle me-1"></i>
            Tick chọn tài liệu bạn muốn đính kèm vào đơn này.
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($walletDocs as $doc):
              $docIcons = [
                'application/pdf' => '📄',
                'image/jpeg'=>'🖼️','image/png'=>'🖼️','image/webp'=>'🖼️',
                'application/msword'=>'📝',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'📝',
              ];
              $icon = $docIcons[$doc['file_type']] ?? '📎';
            ?>
            <label class="wallet-doc-item">
              <input type="checkbox" name="wallet_doc_ids[]"
                     value="<?= $doc['id'] ?>" class="wallet-checkbox">
              <span style="font-size:20px;"><?= $icon ?></span>
              <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:600;color:#0f172a;">
                  <?= e($doc['display_name']) ?>
                </div>
                <div style="font-size:11px;color:#94a3b8;">
                  <?= e($doc['document_type']) ?> ·
                  <?= round($doc['file_size']/1024,1) ?> KB ·
                  <?= date('d/m/Y', strtotime($doc['uploaded_at'])) ?>
                </div>
              </div>
              <span class="wallet-check-icon bi bi-check-circle-fill"></span>
            </label>
            <?php endforeach; ?>
          </div>
          <div class="mt-3">
            <a href="document_wallet.php" style="font-size:12px;color:#1D4ED8;">
              <i class="bi bi-plus-circle me-1"></i> Tải thêm tài liệu vào kho
            </a>
          </div>
        <?php endif; ?>
      </div>

      <div style="font-size:11.5px;color:#94a3b8;margin-top:12px;">
        <i class="bi bi-shield-check me-1 text-success"></i>
        File được lưu trữ bảo mật, chỉ hội đồng xét duyệt mới có thể xem.
      </div>
    </div>
  </div>

  <!-- ── Step 4: Ghi chú nháp & Nộp đơn ──────────────────── -->
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="card-title mb-3">
        <span class="step-badge">4</span> Xác nhận & Nộp đơn
      </h5>

      <!-- Draft notes -->
      <div class="mb-3">
        <label class="form-label">
          <i class="bi bi-sticky me-1 text-warning"></i>
          Ghi chú cá nhân (chỉ bạn thấy)
        </label>
        <textarea name="draft_notes" class="form-control" rows="2"
                  placeholder="Ví dụ: Cần bổ sung bảng điểm học kỳ 2..."
                  style="font-size:13px;"><?= e($draftData['draft_notes'] ?? '') ?></textarea>
      </div>

      <div class="alert alert-info mb-4" style="font-size:13px;">
        <i class="bi bi-info-circle me-2"></i>
        Khi nhấn <strong>Nộp đơn</strong>, hệ thống sẽ tự động kiểm tra điều kiện dựa trên hồ sơ học thuật của bạn.
      </div>

      <div class="d-flex gap-3 flex-wrap">
        <button type="button" id="submitBtn"
                class="btn btn-primary btn-lg"
                onclick="doSubmit()"
                <?= !$profile ? 'disabled' : '' ?>>
          <i class="bi bi-send-fill"></i> Nộp đơn chính thức
        </button>
        <button type="button" id="draftBtn"
                class="btn btn-lg"
                style="background:#fffbeb;color:#92400e;border:1.5px solid #fde68a;"
                onclick="doDraft()">
          <i class="bi bi-cloud-arrow-up"></i> Lưu nháp
        </button>
        <a href="dashboard.php" class="btn btn-secondary btn-lg">
          <i class="bi bi-arrow-left"></i> Quay lại
        </a>
      </div>
    </div>
  </div>

</form>
</div><!-- /col-lg-7 -->

<!-- ══════════════════════════════════════════
     RIGHT COLUMN – Program Sidebar
════════════════════════════════════════════ -->
<div class="col-lg-5">
  <div id="programSidebar">
    <div class="card">
      <div class="card-body">
        <div class="empty-state" style="padding:40px 20px;">
          <div class="empty-state-icon"><i class="bi bi-award"></i></div>
          <div class="empty-state-title">Chọn chương trình</div>
          <div class="empty-state-text">Chọn một chương trình học bổng để xem chi tiết yêu cầu và tiêu chí chấm điểm.</div>
        </div>
      </div>
    </div>
  </div>
</div>

</div><!-- /row -->
<?php endif; ?>

<style>
/* ── apply.php scoped styles ───────────────────────── */
.step-badge {
  background:#1D4ED8;color:#fff;width:28px;height:28px;border-radius:50%;
  display:inline-flex;align-items:center;justify-content:center;
  font-size:13px;font-weight:800;margin-right:10px;
  box-shadow:0 2px 8px rgba(29,78,216,.3);
}
.info-box {
  background:#f8fafc;border-radius:10px;padding:14px 16px;
}
.info-box-blue { background:#eff6ff; }
.info-box-label {
  font-size:10px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.08em;
}
.info-box-value { font-size:14px;font-weight:600;color:#0f172a; }

.drop-zone {
  border:2px dashed #c7d9f8;border-radius:12px;padding:30px;
  text-align:center;background:#f7f9ff;cursor:pointer;transition:all .2s;
}
.drop-zone:hover { border-color:#1D4ED8;background:#eff6ff; }

.doc-tab-btn {
  padding:8px 16px;border-radius:8px;border:1.5px solid #dbeafe;
  background:#f7f9ff;color:#3b5fa0;font-size:13px;font-weight:600;
  cursor:pointer;transition:all .18s;display:inline-flex;align-items:center;
}
.doc-tab-btn.active {
  background:#1D4ED8;color:#fff;border-color:#1D4ED8;
  box-shadow:0 2px 8px rgba(29,78,216,.3);
}

.wallet-doc-item {
  display:flex;align-items:center;gap:12px;
  padding:12px 16px;border-radius:10px;cursor:pointer;
  border:1.5px solid #e2e8f0;background:#fff;transition:all .18s;
}
.wallet-doc-item:hover { border-color:#1D4ED8;background:#eff6ff; }
.wallet-doc-item:has(.wallet-checkbox:checked) {
  border-color:#1D4ED8;background:#eff6ff;
}
.wallet-check-icon {
  color:#94a3b8;font-size:18px;flex-shrink:0;transition:color .18s;
}
.wallet-doc-item:has(.wallet-checkbox:checked) .wallet-check-icon {
  color:#1D4ED8;
}
.wallet-checkbox { display:none; }

/* Eligibility warning */
.eligibility-warning {
  border-radius:10px;padding:14px 16px;margin-top:12px;
  border-left:4px solid #dc2626;background:#fef2f2;
}
.eligibility-warning .warn-row {
  display:flex;align-items:center;gap:8px;
  font-size:13px;color:#991b1b;padding:3px 0;
}
.eligibility-ok {
  border-left:4px solid #16a34a;background:#f0fdf4;
}
.eligibility-ok .warn-row { color:#166534; }
</style>

<script>
// ═══════════════════════════════════════════════════════════
// FEATURE 4 – Eligibility Checker (JS / Frontend)
// Runs instantly when a program is selected
// ═══════════════════════════════════════════════════════════
const STUDENT_PROFILE = <?= $profileJson ?>;

function checkEligibilityFrontend(rules) {
    const box = document.getElementById('eligibilityWarning');
    const submitBtn = document.getElementById('submitBtn');
    if (!box) return;
    if (!STUDENT_PROFILE || !rules || !rules.length) {
        box.style.display = 'none';
        return;
    }

    const ruleLabels = {
        gpa: 'GPA', activities: 'Hoạt động ngoại khóa',
        activities_count: 'Hoạt động ngoại khóa',
        failed_subjects: 'Môn học trượt/thi lại',
        research_projects: 'Đề tài NCKH', research_count: 'Đề tài NCKH',
        language_certificate: 'Chứng chỉ ngoại ngữ',
        family_income: 'Thu nhập gia đình (VNĐ/tháng)',
    };
    const profileMap = {
        gpa: STUDENT_PROFILE.gpa,
        activities: STUDENT_PROFILE.activities_count,
        activities_count: STUDENT_PROFILE.activities_count,
        failed_subjects: STUDENT_PROFILE.failed_subjects,
        research_projects: STUDENT_PROFILE.research_count,
        research_count: STUDENT_PROFILE.research_count,
        language_certificate: STUDENT_PROFILE.language_certificate,
        family_income: STUDENT_PROFILE.family_income,
    };

    let failedRows = [], passedRows = [];
    rules.forEach(rule => {
        const lbl   = ruleLabels[rule.rule_type] || rule.rule_type;
        const val   = profileMap[rule.rule_type];
        const req   = parseFloat(rule.value);
        const myVal = parseFloat(val);
        if (isNaN(myVal) || val === undefined || val === null) return;

        let ok = false;
        switch (rule.operator) {
            case '>=': ok = myVal >= req; break;
            case '>':  ok = myVal >  req; break;
            case '<=': ok = myVal <= req; break;
            case '<':  ok = myVal <  req; break;
            case '=':  ok = myVal == req; break;
        }
        const row = `<div class="warn-row">
            <i class="bi ${ok?'bi-check-circle-fill':'bi-x-circle-fill'}"></i>
            <span><strong>${lbl}</strong>: yêu cầu ${rule.operator} ${rule.value}
            — của bạn: <strong>${val}</strong></span>
        </div>`;
        if (ok) passedRows.push(row); else failedRows.push(row);
    });

    if (failedRows.length > 0) {
        box.style.display = 'block';
        box.className = 'eligibility-warning';
        box.innerHTML = `<div style="font-size:13px;font-weight:700;color:#991b1b;margin-bottom:8px;">
            <i class="bi bi-shield-exclamation me-2"></i>
            Bạn chưa đủ ${failedRows.length} điều kiện bắt buộc của học bổng này
        </div>${failedRows.join('')}`;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.title = 'Không đủ điều kiện – xem cảnh báo phía trên';
        }
    } else if (passedRows.length > 0) {
        box.style.display = 'block';
        box.className = 'eligibility-warning eligibility-ok';
        box.innerHTML = `<div style="font-size:13px;font-weight:700;color:#166534;margin-bottom:8px;">
            <i class="bi bi-shield-check me-2"></i>
            Bạn đáp ứng tất cả điều kiện của học bổng này ✓
        </div>${passedRows.join('')}`;
        const noProfile = <?= $profile ? 'false' : 'true' ?>;
        if (submitBtn && !noProfile) submitBtn.disabled = false;
    } else {
        box.style.display = 'none';
        const noProfile = <?= $profile ? 'false' : 'true' ?>;
        if (submitBtn && !noProfile) submitBtn.disabled = false;
    }
}

// ═══════════════════════════════════════════════════════════
// Program Details Sidebar (AJAX) + trigger Eligibility Check
// ═══════════════════════════════════════════════════════════
function loadProgramDetails(programId) {
    const submitBtn = document.getElementById('submitBtn');
    const sidebar   = document.getElementById('programSidebar');
    const warnBox   = document.getElementById('eligibilityWarning');

    if (!programId) {
        if (submitBtn) submitBtn.disabled = true;
        if (warnBox)   warnBox.style.display = 'none';
        if (sidebar)   sidebar.innerHTML = `<div class="card"><div class="card-body">
            <div class="empty-state" style="padding:40px 20px;">
              <div class="empty-state-icon"><i class="bi bi-award"></i></div>
              <div class="empty-state-title">Chọn chương trình</div>
              <div class="empty-state-text">Chọn học bổng để xem chi tiết yêu cầu và tiêu chí chấm điểm.</div>
            </div></div></div>`;
        return;
    }
    if (sidebar) sidebar.innerHTML = `<div class="card"><div class="card-body text-center py-5">
        <div class="spinner-border" style="color:#1D4ED8;"></div>
        <div class="mt-2 text-muted">Đang tải thông tin...</div></div></div>`;

    fetch('get_program_details.php?id=' + encodeURIComponent(programId))
        .then(r => r.json())
        .then(data => {
            if (!data || data.error) {
                if (sidebar) sidebar.innerHTML = '<div class="alert alert-warning">Không tải được thông tin chương trình.</div>';
                return;
            }
            renderSidebar(data);
            checkEligibilityFrontend(data.rules || []);
        })
        .catch(() => {
            if (sidebar) sidebar.innerHTML = '<div class="alert alert-danger">Lỗi mạng khi tải thông tin.</div>';
        });
}

// ─── Render sidebar card ──────────────────────────────────
function renderSidebar(data) {
    const sidebar = document.getElementById('programSidebar');
    if (!sidebar) return;
    const ruleLabels = {
        gpa:['GPA','bi-graph-up-arrow','#1D4ED8'],
        activities:['Hoạt động','bi-people','#7c3aed'],
        activities_count:['Hoạt động','bi-people','#7c3aed'],
        failed_subjects:['Không có môn trượt','bi-x-circle','#dc2626'],
        research_projects:['Đề tài NCKH','bi-journal-text','#0891b2'],
        research_count:['Đề tài NCKH','bi-journal-text','#0891b2'],
        language_certificate:['Chứng chỉ ngoại ngữ','bi-translate','#16a34a'],
        family_income:['Thu nhập gia đình','bi-house-heart','#d97706'],
    };
    let rulesHtml = '';
    if (data.rules && data.rules.length) {
        rulesHtml = `<div class="mb-4"><div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px;">
            <i class="bi bi-check2-circle text-primary me-1"></i> Điều kiện xét duyệt</div>`;
        data.rules.forEach(r => {
            const l = ruleLabels[r.rule_type] || [r.rule_type,'bi-check2','#1D4ED8'];
            rulesHtml += `<div style="display:flex;align-items:center;gap:10px;padding:9px 12px;background:#f8fafc;border-radius:8px;margin-bottom:6px;">
                <i class="bi ${l[1]}" style="color:${l[2]};font-size:15px;"></i>
                <span style="font-size:13px;color:#334155;"><strong>${l[0]}</strong> ${r.operator} <strong>${r.value}</strong></span>
            </div>`;
        });
        rulesHtml += '</div>';
    }
    let criteriaHtml = '';
    if (data.criteria && data.criteria.length) {
        criteriaHtml = `<div><div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px;">
            <i class="bi bi-star-half text-warning me-1"></i> Tiêu chí chấm điểm</div>`;
        data.criteria.forEach(c => {
            criteriaHtml += `<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#f8fafc;border-radius:8px;margin-bottom:6px;">
                <span style="font-size:13px;color:#334155;">${escHtml(c.criterion_name)}</span>
                <div style="text-align:right;">
                    <span style="font-size:13px;font-weight:700;color:#1D4ED8;">${c.weight}%</span>
                    <span style="font-size:11px;color:#94a3b8;display:block;">max ${c.max_score} pts</span>
                </div></div>`;
        });
        criteriaHtml += '</div>';
    }
    const budget = data.program.budget
        ? Number(data.program.budget).toLocaleString('vi-VN') + ' ₫' : '—';
    sidebar.innerHTML = `<div class="card mb-4" style="border-top:4px solid #1D4ED8;">
        <div class="card-body">
          <div style="width:48px;height:48px;background:#eff6ff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:14px;">🏆</div>
          <h5 style="font-weight:700;color:#0f172a;margin-bottom:8px;">${escHtml(data.program.name)}</h5>
          <p style="font-size:13px;color:#64748b;margin-bottom:16px;">${escHtml(data.program.description||'')}</p>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px;">
            <div style="background:#f0fdf4;border-radius:8px;padding:12px;">
              <div style="font-size:10px;color:#94a3b8;font-weight:700;text-transform:uppercase;">Ngân sách</div>
              <div style="font-size:15px;font-weight:800;color:#16a34a;">${budget}</div>
            </div>
            <div style="background:#eff6ff;border-radius:8px;padding:12px;">
              <div style="font-size:10px;color:#94a3b8;font-weight:700;text-transform:uppercase;">Suất học bổng</div>
              <div style="font-size:15px;font-weight:800;color:#1D4ED8;">${data.program.slots} suất</div>
            </div>
          </div>
          ${rulesHtml}${criteriaHtml}
        </div></div>`;
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str; return d.innerHTML;
}

// ─── Form action triggers ─────────────────────────────────
function doSubmit() {
    document.getElementById('formAction').value = 'submit';
    document.getElementById('applyForm').requestSubmit();
}
function doDraft() {
    document.getElementById('formAction').value = 'draft';
    document.getElementById('applyForm').requestSubmit();
}

// ─── Document tab switch ──────────────────────────────────
function switchDocTab(tab) {
    document.getElementById('tabPanelUpload').style.display = tab==='upload' ? 'block' : 'none';
    document.getElementById('tabPanelWallet').style.display = tab==='wallet' ? 'block' : 'none';
    document.getElementById('tabUpload').classList.toggle('active', tab==='upload');
    document.getElementById('tabWallet').classList.toggle('active', tab==='wallet');
}

// ─── Drag & Drop Upload ───────────────────────────────────
let selectedFiles = [];
function handleDragOver(e) {
    e.preventDefault();
    document.getElementById('dropZone').style.borderColor='#1D4ED8';
    document.getElementById('dropZone').style.background='#eff6ff';
}
function handleDragLeave(e) {
    document.getElementById('dropZone').style.borderColor='#c7d9f8';
    document.getElementById('dropZone').style.background='#f7f9ff';
}
function handleDrop(e) {
    e.preventDefault(); handleDragLeave(e);
    previewFiles(e.dataTransfer.files);
}
function previewFiles(files) {
    if (!files || !files.length) return;
    const dt = new DataTransfer();
    for (let f of selectedFiles) dt.items.add(f);
    for (let f of files) dt.items.add(f);
    selectedFiles = Array.from(dt.files);
    document.getElementById('evidenceInput').files = dt.files;
    renderFileList();
}
function removeFile(i) {
    selectedFiles.splice(i, 1);
    const dt = new DataTransfer();
    for (let f of selectedFiles) dt.items.add(f);
    document.getElementById('evidenceInput').files = dt.files;
    renderFileList();
}
function renderFileList() {
    const list = document.getElementById('filePreviewList');
    if (!selectedFiles.length) { list.innerHTML=''; return; }
    const icons = {'application/pdf':'📄','image/jpeg':'🖼️','image/png':'🖼️',
        'image/gif':'🖼️','image/webp':'🖼️','application/msword':'📝',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document':'📝'};
    list.innerHTML = `<div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:8px;">
        <i class="bi bi-files me-1"></i>${selectedFiles.length} file đã chọn</div>
        <div style="display:flex;flex-direction:column;gap:6px;">
        ${selectedFiles.map((f,i)=>`<div style="display:flex;align-items:center;gap:10px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:8px 12px;">
            <span style="font-size:20px;">${icons[f.type]||'📎'}</span>
            <div style="flex:1;min-width:0;">
              <div style="font-size:13px;font-weight:600;color:#14532d;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escHtml(f.name)}</div>
              <div style="font-size:11px;color:#6b7280;">${(f.size/1024).toFixed(1)} KB · ${f.type||'unknown'}</div>
            </div>
            <button type="button" onclick="removeFile(${i})"
              style="background:none;border:none;color:#dc2626;font-size:18px;cursor:pointer;padding:0 4px;">×</button>
        </div>`).join('')}</div>`;
}

// ─── On page load ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('programSelect');
    const submitBtn = document.getElementById('submitBtn');
    const noProfile = <?= $profile ? 'false' : 'true' ?>;
    if (submitBtn && !noProfile) submitBtn.disabled = true; // re-enabled after program load

    if (sel && sel.value) loadProgramDetails(sel.value);

    // Validate before form submit
    document.getElementById('applyForm')?.addEventListener('submit', function(e) {
        if (!document.getElementById('programSelect')?.value) {
            e.preventDefault();
            alert('Vui lòng chọn chương trình học bổng!');
        }
    });

    // ── Auto-save draft every 45 seconds ──────────────────
    let currentDraftId = <?= $draftId ?: 'null' ?>;
    let autoSaveTimer  = null;

    function autoSaveDraft() {
        const progId = document.getElementById('programSelect')?.value;
        if (!progId) return;
        const notes = document.querySelector('textarea[name="draft_notes"]')?.value || '';

        fetch('api/save_draft.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify({
                program_id : parseInt(progId),
                draft_id   : currentDraftId,
                draft_notes: notes,
            }),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                currentDraftId = data.draft_id;
                // Update hidden field if present
                const hiddenDraft = document.querySelector('input[name="draft_id"]');
                if (hiddenDraft) hiddenDraft.value = currentDraftId;
                else {
                    const hf = document.createElement('input');
                    hf.type = 'hidden'; hf.name = 'draft_id';
                    hf.value = currentDraftId;
                    document.getElementById('applyForm')?.appendChild(hf);
                }
                showAutoSaveToast(data.saved_at);
            }
        })
        .catch(() => {}); // silent fail – don't disturb the user
    }

    function showAutoSaveToast(time) {
        let toast = document.getElementById('autoSaveToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'autoSaveToast';
            toast.style.cssText =
                'position:fixed;bottom:20px;right:20px;z-index:9999;' +
                'background:#0f172a;color:#fff;padding:10px 18px;border-radius:10px;' +
                'font-size:13px;font-weight:600;opacity:0;transition:opacity .3s;' +
                'box-shadow:0 4px 16px rgba(0,0,0,.25);display:flex;align-items:center;gap:8px;';
            document.body.appendChild(toast);
        }
        toast.innerHTML = `<i class="bi bi-cloud-check" style="color:#4ade80;"></i> Đã lưu nháp tự động lúc ${time}`;
        toast.style.opacity = '1';
        clearTimeout(window._toastTimer);
        window._toastTimer = setTimeout(() => toast.style.opacity = '0', 3000);
    }

    // Trigger auto-save when program changes or notes blur
    document.getElementById('programSelect')?.addEventListener('change', () => {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(autoSaveDraft, 1500);
    });
    document.querySelector('textarea[name="draft_notes"]')?.addEventListener('blur', autoSaveDraft);

    // Periodic auto-save every 45s
    setInterval(autoSaveDraft, 45000);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
