<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/header.php';

$pageTitle = 'Applications Management';
$pdo = getDB();

$sql = "
SELECT applications.*,
users.full_name,
scholarship_programs.name AS program_name

FROM applications

JOIN users
ON applications.student_id = users.id

JOIN scholarship_programs
ON applications.program_id = scholarship_programs.id

ORDER BY applications.id DESC
";

$stmt = $pdo->query($sql);
$applications = $stmt->fetchAll();
?>

<h2 class="mb-4">Applications Management</h2>

<a href="create.php" class="btn btn-primary mb-3">
    Add Application
</a>

<table class="table table-bordered table-hover">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Student</th>
            <th>Program</th>
            <th>Status</th>
            <th>Eligible</th>
            <th>Submitted</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($applications as $app): ?>
            <tr>
                <td><?= htmlspecialchars($app['id']) ?></td>
                <td><?= htmlspecialchars($app['full_name']) ?></td>
                <td><?= htmlspecialchars($app['program_name']) ?></td>
                <td><span class="badge bg-warning"><?= htmlspecialchars($app['status']) ?></span></td>
                <td><?= $app['eligible'] === null ? 'Unknown' : ($app['eligible'] ? 'YES' : 'NO') ?></td>
                <td><?= htmlspecialchars($app['submitted_at']) ?></td>
                <td>
                    <a href="edit.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-success">Edit</a>
                    <a href="delete.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this application?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

