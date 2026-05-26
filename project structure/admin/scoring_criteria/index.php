<?php
// ============================================================
// admin/scoring_criteria/index.php
// ============================================================

$pageTitle = 'Scoring Criteria';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

$pdo = getDB();
$sql = "
    SELECT c.*, p.name AS program_name
    FROM scoring_criteria c
    INNER JOIN scholarship_programs p ON c.program_id = p.id
    ORDER BY c.id DESC
";
$criteria = $pdo->query($sql)->fetchAll();
?>

<div class="container py-4">
    <!-- PAGE TITLE -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">Scoring Criteria</h1>
            <p class="page-subtitle">Configure weights and rules for reviewer grading</p>
        </div>
        <a class="btn btn-primary" href="create.php">
            <i class="bi bi-plus-lg me-2"></i> Add Criterion
        </a>
    </div>

    <!-- TABLE -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Program</th>
                        <th>Criterion Name</th>
                        <th>Weight (%)</th>
                        <th>Max Score</th>
                        <th width="180">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($criteria as $criterion): ?>
                        <tr>
                            <td>#<?= e($criterion['id']) ?></td>
                            <td><strong><?= e($criterion['program_name']) ?></strong></td>
                            <td><?= e($criterion['criterion_name']) ?></td>
                            <td><span class="badge bg-primary fs-6"><?= e($criterion['weight']) ?>%</span></td>
                            <td><span class="badge bg-secondary fs-6"><?= e($criterion['max_score']) ?></span></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a class="btn btn-warning btn-sm btn-action" href="edit.php?id=<?= $criterion['id'] ?>">
                                        Edit
                                    </a>
                                    <a class="btn btn-danger btn-sm btn-action" href="delete.php?id=<?= $criterion['id'] ?>" onclick="return confirm('Delete this scoring criterion?')">
                                        Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>