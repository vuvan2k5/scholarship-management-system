<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();
requireRole('student');

$pdo = getDB();
$studentId = currentUserId();
$sql = 'SELECT a.id, a.status, a.eligible, a.submitted_at, sp.name AS program_name,
               COALESCE(SUM(es.score), 0) AS total_score,
               COUNT(DISTINCT es.criteria_id) AS scored_criteria
        FROM applications a
        JOIN scholarship_programs sp ON a.program_id = sp.id
        LEFT JOIN evaluation_scores es ON es.application_id = a.id
        WHERE a.student_id = ?
        GROUP BY a.id
        ORDER BY a.submitted_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute([$studentId]);
$results = $stmt->fetchAll();
?>

<h2 class="mb-4">Kết quả học bổng</h2>
<?php if (empty($results)): ?>
    <div class="alert alert-info">Bạn chưa có kết quả nào.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Chương trình</th>
                    <th>Trạng thái</th>
                    <th>Điểm tổng</th>
                    <th>Đủ điều kiện</th>
                    <th>Ngày nộp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                    <tr>
                        <td><?= e($row['id']) ?></td>
                        <td><?= e($row['program_name']) ?></td>
                        <td><span class="badge badge-status-<?= e($row['status']) ?>"><?= e(ucfirst($row['status'])) ?></span></td>
                        <td><?= e(number_format((float)$row['total_score'], 2)) ?></td>
                        <td><?= $row['eligible'] === null ? 'Chưa' : ($row['eligible'] ? 'Có' : 'Không') ?></td>
                        <td><?= e($row['submitted_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
