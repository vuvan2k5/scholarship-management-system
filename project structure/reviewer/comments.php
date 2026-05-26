<?php

$pageTitle = 'Reviewer Comments';

require_once '../config/db.php';

require_once '../includes/auth.php';

requireLogin();

requireRole('council', 'reviewer');

require_once '../includes/header.php';

require_once '../includes/navbar.php';

$pdo = getDB();

/* =========================
   FETCH REVIEW COMMENTS
========================= */

$sql = "

    SELECT

        evaluation_scores.*,

        users.full_name,

        scholarship_programs.name AS program_name

    FROM evaluation_scores

    JOIN applications
        ON evaluation_scores.application_id = applications.id

    JOIN users
        ON applications.student_id = users.id

    JOIN scholarship_programs
        ON applications.program_id = scholarship_programs.id

    WHERE evaluation_scores.council_id = ?

    ORDER BY evaluation_scores.id DESC

";

$stmt = $pdo->prepare($sql);

$stmt->execute([currentUserId()]);

$comments = $stmt->fetchAll();

?>

<div class="container py-4">

    <!-- PAGE HEADER -->

    <div class="mb-4">

        <h1 class="page-title">

            Reviewer Comments

        </h1>

        <p class="page-subtitle">

            View all comments and feedback history

        </p>

    </div>

    <!-- COMMENTS TABLE -->

    <div class="table-card">

        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Student</th>

                        <th>Scholarship</th>

                        <th>Score</th>

                        <th>Comment</th>

                        <th>Date</th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($comments as $comment): ?>

                        <tr>

                            <td>

                                #<?= e($comment['id']) ?>

                            </td>

                            <td>

                                <?= e($comment['full_name']) ?>

                            </td>

                            <td>

                                <?= e($comment['program_name']) ?>

                            </td>

                            <td>

                                <span class="badge bg-success">

                                    <?= e($comment['score']) ?>

                                </span>

                            </td>

                            <td style="max-width:300px;">

                                <?= e($comment['note']) ?>

                            </td>

                            <td>

                                <?= e($comment['scored_at']) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>