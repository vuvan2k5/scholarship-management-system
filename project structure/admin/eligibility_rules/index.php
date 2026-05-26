<?php
// ============================================================
// admin/eligibility_rules/index.php
// ============================================================

$pageTitle = 'Eligibility Rules';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

$pdo = getDB();
$sql = "
    SELECT r.*, p.name AS program_name
    FROM eligibility_rules r
    INNER JOIN scholarship_programs p ON r.program_id = p.id
    ORDER BY r.id DESC
";
$rules = $pdo->query($sql)->fetchAll();
?>

<div class="container py-4">
    <!-- PAGE TITLE -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">Eligibility Rules</h1>
            <p class="page-subtitle">Configure filter rules for candidate automatic filtering</p>
        </div>
        <a class="btn btn-primary" href="create.php">
            <i class="bi bi-plus-lg me-2"></i> Add Rule
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
                        <th>Criterion / Type</th>
                        <th>Operator</th>
                        <th>Threshold Value</th>
                        <th width="180">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($rules as $rule): ?>
                        <tr>
                            <td>#<?= e($rule['id']) ?></td>
                            <td><strong><?= e($rule['program_name']) ?></strong></td>
                            <td><span class="badge bg-secondary text-uppercase"><?= e($rule['rule_type']) ?></span></td>
                            <td class="font-monospace fw-bold"><?= e($rule['operator']) ?></td>
                            <td><?= e($rule['value']) ?></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a class="btn btn-warning btn-sm btn-action" href="edit.php?id=<?= $rule['id'] ?>">
                                        Edit
                                    </a>
                                    <a class="btn btn-danger btn-sm btn-action" href="delete.php?id=<?= $rule['id'] ?>" onclick="return confirm('Delete this rule?')">
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