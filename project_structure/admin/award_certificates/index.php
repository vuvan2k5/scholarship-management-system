<?php
// ============================================================
// admin/award_certificates/index.php
// ============================================================

$pageTitle = 'Award Certificates';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// ── BATCH GENERATE CERTIFICATES ──────────────────────────────
if (isset($_GET['generate_all'])) {
    $issuedBy = $_SESSION['user_id'];
    // Find all recommended (awarded) applications that don't yet have a certificate
    $stmt = $pdo->prepare("
        SELECT rr.application_id
        FROM ranking_results rr
        WHERE rr.recommended = 1
          AND rr.application_id NOT IN (SELECT application_id FROM award_certificates)
    ");
    $stmt->execute();
    $pendingAwards = $stmt->fetchAll();

    $count = 0;
    foreach ($pendingAwards as $row) {
        $appId = $row['application_id'];
        $code  = 'CERT-' . strtoupper(substr(md5($appId . time()), 0, 8));
        $insert = $pdo->prepare("
            INSERT INTO award_certificates (application_id, certificate_code, issued_at, issued_by)
            VALUES (?, ?, NOW(), ?)
        ");
        $insert->execute([$appId, $code, $issuedBy]);
        $count++;
    }
    setFlash('success', "Generated $count new certificate(s) for all recommended candidates.");
    header('Location: index.php');
    exit;
}

$sql = "
    SELECT ac.*, a.id AS application_number, u.full_name AS student_name,
           u.student_code, sp.name AS program_name, sp.start_date, sp.end_date
    FROM award_certificates ac
    INNER JOIN applications a ON ac.application_id = a.id
    INNER JOIN users u ON a.student_id = u.id
    INNER JOIN scholarship_programs sp ON a.program_id = sp.id
    ORDER BY ac.id DESC
";
$certificates = $pdo->query($sql)->fetchAll();

// Count pending certificates (recommended but not yet issued)
$pending = $pdo->query("
    SELECT COUNT(*) FROM ranking_results rr
    WHERE rr.recommended = 1
      AND rr.application_id NOT IN (SELECT application_id FROM award_certificates)
")->fetchColumn();
?>

<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/navbar.php'; ?>
<div class="container py-4">
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Award Certificates</h1>
    <p class="page-subtitle">Issue and download official scholarship certificates for awarded students.</p>
  </div>
  <div class="d-flex gap-2">
    <?php if ($pending > 0): ?>
      <a href="index.php?generate_all=1" class="btn btn-primary"
         onclick="return confirm('Generate <?= $pending ?> certificate(s) for all recommended candidates?');">
        <i class="bi bi-award me-1"></i> Generate Certificates (<?= $pending ?> pending)
      </a>
    <?php else: ?>
      <button class="btn btn-outline-secondary" disabled>
        <i class="bi bi-check-all me-1"></i> All Issued
      </button>
    <?php endif; ?>
    <a href="create.php" class="btn btn-outline-primary">
      <i class="bi bi-plus-lg me-1"></i> Issue Manually
    </a>
  </div>
</div>

<div class="table-card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Cert Code</th>
          <th>Student</th>
          <th>Program</th>
          <th>Issued At</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($certificates as $row): ?>
          <tr>
            <td><span class="text-muted">#<?= e($row['id']) ?></span></td>
            <td><code style="font-size:12px;background:#f1f5f9;padding:3px 10px;border-radius:4px;">
              <?= e($row['certificate_code']) ?>
            </code></td>
            <td>
              <strong><?= e($row['student_name']) ?></strong><br>
              <small class="text-muted"><?= e($row['student_code']) ?></small>
            </td>
            <td><?= e($row['program_name']) ?></td>
            <td class="text-muted"><?= e($row['issued_at']) ?></td>
            <td>
              <div class="d-flex gap-2">
                <a href="view_certificate.php?id=<?= $row['id'] ?>" target="_blank"
                   class="btn btn-sm btn-success btn-action">
                  <i class="bi bi-file-earmark-pdf me-1"></i> View / Print PDF
                </a>
                <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger btn-action"
                   onclick="return confirm('Revoke this certificate?')">
                  <i class="bi bi-trash"></i>
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($certificates)): ?>
          <tr><td colspan="6" class="text-center py-4 text-muted">No certificates issued yet. Run "Generate Certificates" first.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

