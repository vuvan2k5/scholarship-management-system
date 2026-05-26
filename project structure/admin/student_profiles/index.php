<?php
// ============================================================
// admin/student_profiles/index.php
// ============================================================

$pageTitle = 'Student Profiles';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

$pdo = getDB();
$sql = "
    SELECT sp.*, u.full_name, u.email
    FROM student_profiles sp
    JOIN users u ON sp.student_id = u.id
    ORDER BY sp.id DESC
";
$stmt = $pdo->query($sql);
$profiles = $stmt->fetchAll();
?>

<div class="container py-4">
    <!-- PAGE TITLE -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">Student Profiles</h1>
            <p class="page-subtitle">Configure academic & financial status records for automated rule checks</p>
        </div>
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i> Add Profile
        </a>
    </div>

    <!-- TABLE -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student Name</th>
                        <th>Email</th>
                        <th>Faculty / Major</th>
                        <th>GPA</th>
                        <th>Activities</th>
                        <th>Family Income</th>
                        <th>Disadvantaged</th>
                        <th>Updated At</th>
                        <th width="180">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($profiles as $profile): ?>
                        <tr>
                            <td>#<?= e($profile['id']) ?></td>
                            <td><strong><?= e($profile['full_name']) ?></strong></td>
                            <td><span class="small text-muted"><?= e($profile['email']) ?></span></td>
                            <td><?= e($profile['faculty']) ?> <br> <span class="small text-muted"><?= e($profile['major']) ?></span></td>
                            <td><span class="badge bg-primary fs-6"><?= number_format($profile['gpa'], 2) ?></span></td>
                            <td><?= e($profile['activities_count']) ?></td>
                            <td><?= number_format($profile['family_income']) ?> VND</td>
                            <td>
                                <?php if($profile['is_disadvantaged']): ?>
                                    <span class="badge bg-warning text-dark">Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted">No</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($profile['updated_at']) ?></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="edit.php?id=<?= $profile['id'] ?>" class="btn btn-warning btn-sm btn-action">Edit</a>
                                    <a href="delete.php?id=<?= $profile['id'] ?>" class="btn btn-danger btn-sm btn-action" onclick="return confirm('Delete this student profile?')">Delete</a>
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
