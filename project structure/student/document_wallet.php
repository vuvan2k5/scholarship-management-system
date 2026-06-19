<?php
// ============================================================
// student/document_wallet.php – Kho tài liệu sinh viên
// Feature: upload, list, delete personal documents
// ============================================================
$pageTitle = 'Document Wallet';

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requireRole('student');

$pdo       = getDB();
$studentId = currentUserId();
$errors    = [];

$uploadDir    = __DIR__ . '/../uploads/wallet/';
$uploadWebDir = 'uploads/wallet/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$allowedMime = [
    'image/jpeg','image/png','image/gif','image/webp',
    'application/pdf','application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$maxSize = 10 * 1024 * 1024; // 10 MB

// ── DELETE ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delId = (int)$_POST['delete_id'];
    $row = $pdo->prepare("SELECT * FROM student_documents WHERE id=? AND student_id=?");
    $row->execute([$delId, $studentId]);
    $doc = $row->fetch();
    if ($doc) {
        // Only delete physical file if not referenced by any application_evidence
        $refCount = (int)$pdo->prepare(
            "SELECT COUNT(*) FROM application_evidence WHERE wallet_doc_id=?"
        )->execute([$delId]) ? $pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;

        $physPath = $uploadDir . $doc['stored_name'];
        if (file_exists($physPath)) @unlink($physPath);

        $pdo->prepare("DELETE FROM student_documents WHERE id=? AND student_id=?"
        )->execute([$delId, $studentId]);
        setFlash('success', 'Deleted document "' . htmlspecialchars($doc['display_name']) . '".');
    }
    header('Location: document_wallet.php');
    exit;
}

// ── UPLOAD ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])
    && $_POST['action'] === 'upload') {

    $displayName  = trim($_POST['display_name']  ?? '');
    $documentType = trim($_POST['document_type'] ?? '');

    if (!$displayName)  $errors[] = 'Please enter a display name for the document.';
    if (!$documentType) $errors[] = 'Please select the document type.';

    if (empty($_FILES['doc_file']['name'])) {
        $errors[] = 'Please select a file to upload.';
    } elseif ($_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Error uploading file (error code: ' . $_FILES['doc_file']['error'] . ').';
    } elseif ($_FILES['doc_file']['size'] > $maxSize) {
        $errors[] = 'File is too large. Maximum size allowed is 10MB.';
    } else {
        $mime = mime_content_type($_FILES['doc_file']['tmp_name']);
        if (!in_array($mime, $allowedMime)) {
            $errors[] = 'File format not allowed. Accepted: PDF, Word, Images.';
        }
    }

    if (empty($errors)) {
        $orig = basename($_FILES['doc_file']['name']);
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $stor = 'wal_' . $studentId . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $uploadDir . $stor)) {
            $pdo->prepare("
                INSERT INTO student_documents
                  (student_id, document_type, display_name, original_name,
                   stored_name, file_path, file_size, file_type)
                VALUES (?,?,?,?,?,?,?,?)
            ")->execute([
                $studentId, $documentType, $displayName, $orig,
                $stor, $uploadWebDir . $stor,
                $_FILES['doc_file']['size'], $mime,
            ]);
            setFlash('success', 'Upload successful: "' . htmlspecialchars($displayName) . '"');
            header('Location: document_wallet.php');
            exit;
        } else {
            $errors[] = 'Could not save file. Please try again.';
        }
    }
}

// ── Load documents ──────────────────────────────────────────
$docs = $pdo->prepare(
    "SELECT * FROM student_documents WHERE student_id=? ORDER BY uploaded_at DESC"
);
$docs->execute([$studentId]);
$docs = $docs->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$docTypeOptions = [
    'Bảng điểm (Transcript)',
    'Chứng chỉ ngoại ngữ (IELTS/TOEIC/B1)',
    'CCCD / Hộ chiếu',
    'Giấy chứng nhận hoạt động',
    'Đề tài NCKH',
    'Giấy chứng nhận giải thưởng',
    'Giấy xác nhận hoàn cảnh gia đình',
    'Khác',
];
$docIcons = [
    'application/pdf'=>'📄','image/jpeg'=>'🖼️','image/png'=>'🖼️',
    'image/webp'=>'🖼️','image/gif'=>'🖼️','application/msword'=>'📝',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'📝',
];
?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">
      <i class="bi bi-folder2-open me-2 text-primary"></i>Kho Tài Liệu
    </h1>
    <p class="page-subtitle">
      Lưu trữ tài liệu cá nhân một lần, dùng lại cho nhiều đơn đăng ký.
    </p>
  </div>
  <a href="apply.php" class="btn btn-secondary">
    <i class="bi bi-file-earmark-plus"></i> Nộp đơn
  </a>
</div>

<?php showFlash(); ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4">
  <i class="bi bi-exclamation-triangle-fill me-2"></i>
  <div><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
</div>
<?php endif; ?>

<div class="row g-4">

  <!-- LEFT: Upload form -->
  <div class="col-lg-4">
    <div class="card" style="position:sticky;top:80px;">
      <div class="card-body">
        <h5 class="card-title mb-4">
          <i class="bi bi-cloud-upload me-2 text-primary"></i>Tải lên tài liệu mới
        </h5>
        <form method="POST" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="action" value="upload">

          <div class="mb-3">
            <label class="form-label">Tên hiển thị <span class="text-danger">*</span></label>
            <input type="text" name="display_name" class="form-control" required
                   placeholder="VD: Bảng điểm HK1 2025-2026"
                   value="<?= e($_POST['display_name'] ?? '') ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Loại tài liệu <span class="text-danger">*</span></label>
            <select name="document_type" class="form-select" required>
              <option value="">— Chọn loại —</option>
              <?php foreach ($docTypeOptions as $opt): ?>
              <option value="<?= e($opt) ?>"
                <?= ($_POST['document_type'] ?? '') === $opt ? 'selected' : '' ?>>
                <?= e($opt) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-4">
            <label class="form-label">File <span class="text-danger">*</span></label>
            <div id="walletDropZone" class="wallet-upload-zone"
                 onclick="document.getElementById('docFileInput').click()"
                 ondragover="wDragOver(event)" ondragleave="wDragLeave(event)"
                 ondrop="wDrop(event)">
              <i class="bi bi-cloud-upload" style="font-size:28px;color:#1D4ED8;display:block;margin-bottom:6px;"></i>
              <div style="font-size:13px;font-weight:600;color:#334155;">Chọn hoặc kéo thả file</div>
              <div style="font-size:11.5px;color:#94a3b8;margin-top:4px;">PDF, Word, Ảnh · Tối đa 10MB</div>
              <div id="walletFileName" style="margin-top:8px;font-size:12.5px;color:#1D4ED8;font-weight:600;"></div>
            </div>
            <input type="file" id="docFileInput" name="doc_file"
                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp"
                   style="display:none;" onchange="showWalletFile(this)">
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-cloud-upload"></i> Tải lên kho
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- RIGHT: Document list -->
  <div class="col-lg-8">
    <?php if (empty($docs)): ?>
    <div class="card">
      <div class="card-body">
        <div class="empty-state">
          <div class="empty-state-icon"><i class="bi bi-folder-x"></i></div>
          <div class="empty-state-title">Empty document repository</div>
          <div class="empty-state-text">
            Upload documents to reuse when submitting multiple scholarship applications.
          </div>
        </div>
      </div>
    </div>
    <?php else: ?>
    <!-- Stats bar -->
    <div class="row g-3 mb-4">
      <div class="col-4">
        <div class="stat-card" style="padding:16px 20px;">
          <div class="stat-icon blue"><i class="bi bi-files"></i></div>
          <div class="stat-body">
            <div class="stat-label">Tổng tài liệu</div>
            <div class="stat-value" style="font-size:22px;"><?= count($docs) ?></div>
          </div>
        </div>
      </div>
      <div class="col-4">
        <div class="stat-card" style="padding:16px 20px;">
          <div class="stat-icon green"><i class="bi bi-hdd"></i></div>
          <div class="stat-body">
            <div class="stat-label">Dung lượng</div>
            <div class="stat-value" style="font-size:22px;">
              <?= round(array_sum(array_column($docs,'file_size'))/1024/1024, 1) ?> MB
            </div>
          </div>
        </div>
      </div>
      <div class="col-4">
        <div class="stat-card" style="padding:16px 20px;">
          <div class="stat-icon cyan"><i class="bi bi-tags"></i></div>
          <div class="stat-body">
            <div class="stat-label">Loại tài liệu</div>
            <div class="stat-value" style="font-size:22px;">
              <?= count(array_unique(array_column($docs,'document_type'))) ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Document grid -->
    <div class="row g-3">
      <?php foreach ($docs as $doc):
        $icon = $docIcons[$doc['file_type']] ?? '📎';
        $sizeKb = round($doc['file_size']/1024, 1);
      ?>
      <div class="col-md-6">
        <div class="wallet-card">
          <div style="display:flex;align-items:flex-start;gap:12px;">
            <span style="font-size:32px;flex-shrink:0;"><?= $icon ?></span>
            <div style="flex:1;min-width:0;">
              <div style="font-size:14px;font-weight:700;color:#0f172a;
                   white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <?= e($doc['display_name']) ?>
              </div>
              <div class="mt-1">
                <span class="badge badge-info" style="font-size:10.5px;">
                  <?= e($doc['document_type']) ?>
                </span>
              </div>
              <div style="font-size:11px;color:#94a3b8;margin-top:6px;">
                <?= e($doc['original_name']) ?> · <?= $sizeKb ?> KB
              </div>
              <div style="font-size:11px;color:#94a3b8;">
                <i class="bi bi-clock me-1"></i>
                <?= date('d/m/Y H:i', strtotime($doc['uploaded_at'])) ?>
              </div>
            </div>
          </div>
          <div style="display:flex;gap:8px;margin-top:14px;padding-top:12px;border-top:1px solid #f1f5f9;">
            <a href="<?= BASE_URL ?>/uploads/wallet/<?= urlencode($doc['stored_name']) ?>"
               target="_blank" class="btn btn-sm btn-outline-primary" style="flex:1;justify-content:center;">
              <i class="bi bi-eye"></i> Xem
            </a>
            <a href="<?= BASE_URL ?>/uploads/wallet/<?= urlencode($doc['stored_name']) ?>"
               download="<?= e($doc['original_name']) ?>"
               class="btn btn-sm btn-secondary" style="flex:1;justify-content:center;">
              <i class="bi bi-download"></i> Tải
            </a>
            <form method="POST" style="flex:1;"
                  onsubmit="return confirm('Xóa tài liệu này khỏi kho?')">
              <input type="hidden" name="delete_id" value="<?= $doc['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger w-100">
                <i class="bi bi-trash3"></i> Xóa
              </button>
            </form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

</div><!-- /row -->

<style>
.wallet-upload-zone {
  border:2px dashed #c7d9f8;border-radius:12px;padding:24px;
  text-align:center;background:#f7f9ff;cursor:pointer;transition:all .2s;
}
.wallet-upload-zone:hover { border-color:#1D4ED8;background:#eff6ff; }
.wallet-card {
  background:#fff;border:1.5px solid #dbeafe;border-radius:14px;
  padding:18px;transition:all .18s;
  box-shadow:0 2px 8px rgba(29,78,216,.06);
}
.wallet-card:hover {
  border-color:#1D4ED8;
  box-shadow:0 4px 16px rgba(29,78,216,.12);
  transform:translateY(-2px);
}
</style>

<script>
function wDragOver(e) {
    e.preventDefault();
    document.getElementById('walletDropZone').style.borderColor='#1D4ED8';
    document.getElementById('walletDropZone').style.background='#eff6ff';
}
function wDragLeave(e) {
    document.getElementById('walletDropZone').style.borderColor='#c7d9f8';
    document.getElementById('walletDropZone').style.background='#f7f9ff';
}
function wDrop(e) {
    e.preventDefault(); wDragLeave(e);
    const files = e.dataTransfer.files;
    if (files.length) {
        document.getElementById('docFileInput').files = files;
        showWalletFile(document.getElementById('docFileInput'));
    }
}
function showWalletFile(input) {
    const fn = document.getElementById('walletFileName');
    if (input.files && input.files[0]) {
        fn.textContent = '✓ ' + input.files[0].name;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
