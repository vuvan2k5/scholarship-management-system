<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();
requireRole('student');

$pdo = getDB();
$studentId = currentUserId();
$applications = $pdo->prepare(
    'SELECT a.*, sp.name AS program_name
     FROM applications a
     JOIN scholarship_programs sp ON a.program_id = sp.id
     WHERE a.student_id = ?
     ORDER BY a.submitted_at DESC'
);
$applications->execute([$studentId]);
$applications = $applications->fetchAll();
?>

<h2 class="mb-4">Hồ sơ của tôi</h2>
<?php if (empty($applications)): ?>
    <div class="alert alert-info">Bạn chưa nộp hồ sơ nào.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Chương trình</th>
                    <th>Trạng thái</th>
                    <th>Đủ điều kiện</th>
                    <th>Ngày nộp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $app): ?>
                    <tr>
                        <td><?= e($app['id']) ?></td>
                        <td><?= e($app['program_name']) ?></td>
                        <td><span class="badge badge-status-<?= e($app['status']) ?>"><?= e(ucfirst($app['status'])) ?></span></td>
                        <td><?= $app['eligible'] === null ? 'Chưa xác định' : ($app['eligible'] ? 'Có' : 'Không') ?></td>
                        <td><?= e($app['submitted_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
