<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/header.php';

$pageTitle = 'Evaluation Scores';
$pdo = getDB();
$sql = "SELECT es.*, c.criterion_name AS criteria_name, u.full_name AS reviewer_name,
               a.student_id, su.full_name AS student_name, sp.name AS program_name
        FROM evaluation_scores es
        JOIN scoring_criteria c ON es.criteria_id = c.id
        JOIN users u ON es.council_id = u.id
        JOIN applications a ON es.application_id = a.id
        JOIN users su ON a.student_id = su.id
        JOIN scholarship_programs sp ON a.program_id = sp.id
        ORDER BY es.id DESC";
$stmt = $pdo->query($sql);
$scores = $stmt->fetchAll();
?>

<h2 class="mb-4">Evaluation Scores</h2>

<a href="create.php" class="btn btn-primary mb-3">Add Score</a>

<table class="table table-bordered table-hover">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Application</th>
            <th>Student</th>
            <th>Program</th>
            <th>Criteria</th>
            <th>Reviewer</th>
            <th>Score</th>
            <th>Note</th>
            <th>Scored At</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($scores as $score): ?>
            <tr>
                <td><?= $score['id'] ?></td>
                <td><?= $score['application_id'] ?></td>
                <td><?= htmlspecialchars($score['student_name']) ?></td>
                <td><?= htmlspecialchars($score['program_name']) ?></td>
                <td><?= htmlspecialchars($score['criteria_name']) ?></td>
                <td><?= htmlspecialchars($score['reviewer_name']) ?></td>
                <td><?= number_format($score['score'], 2) ?></td>
                <td><?= htmlspecialchars($score['note']) ?></td>
                <td><?= $score['scored_at'] ?></td>
                <td>
                    <a href="edit.php?id=<?= $score['id'] ?>" class="btn btn-sm btn-success">Edit</a>
                    <a href="delete.php?id=<?= $score['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this score?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

