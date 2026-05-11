<?php

include '../../config/db.php';
include '../../includes/header.php';

$pageTitle = 'Student Profiles';

$pdo = getDB();
$sql = "SELECT sp.*, u.full_name, u.email FROM student_profiles sp JOIN users u ON sp.user_id = u.id ORDER BY sp.id DESC";
$stmt = $pdo->query($sql);
$profiles = $stmt->fetchAll();
?>

<h2 class="mb-4">Student Profiles</h2>

<a href="create.php" class="btn btn-primary mb-3">Add Profile</a>

<table class="table table-bordered table-hover">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Student</th>
            <th>Faculty</th>
            <th>Major</th>
            <th>GPA</th>
            <th>Activities</th>
            <th>Income</th>
            <th>Disadvantaged</th>
            <th>Updated</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($profiles as $profile): ?>
            <tr>
                <td><?= $profile['id'] ?></td>
                <td><?= htmlspecialchars($profile['full_name']) ?></td>
                <td><?= htmlspecialchars($profile['faculty']) ?></td>
                <td><?= htmlspecialchars($profile['major']) ?></td>
                <td><?= number_format($profile['gpa'], 2) ?></td>
                <td><?= $profile['activities_count'] ?></td>
                <td><?= $profile['family_income'] ?></td>
                <td><?= $profile['is_disadvantaged'] ? 'Yes' : 'No' ?></td>
                <td><?= $profile['updated_at'] ?></td>
                <td>
                    <a href="edit.php?id=<?= $profile['id'] ?>" class="btn btn-sm btn-success">Edit</a>
                    <a href="delete.php?id=<?= $profile['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this profile?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include '../../includes/footer.php'; ?>
