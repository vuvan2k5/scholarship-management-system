<?php

$pageTitle = 'Evaluation Scores';

require_once '../config/db.php';

require_once '../includes/auth.php';

requireLogin();

requireRole('council');

require_once '../includes/header.php';

require_once '../includes/navbar.php';

$pdo = getDB();

/* =========================
   FETCH SCORES
========================= */

$sql = "

    SELECT

        evaluation_scores.*,

        users.full_name,

        applications.status

    FROM evaluation_scores

    JOIN applications
        ON evaluation_scores.application_id = applications.id

    JOIN users
        ON applications.student_id = users.id

    WHERE evaluation_scores.council_id = ?

    ORDER BY evaluation_scores.id DESC

";

$stmt = $pdo->prepare($sql);

$stmt->execute([currentUserId()]);

$scores = $stmt->fetchAll();

?>

<div class="container py-4">

    <!-- PAGE HEADER -->

    <div class="mb-4">

        <h1 class="page-title">

            Evaluation Scores

        </h1>

        <p class="page-subtitle">

            View all submitted evaluations

        </p>

    </div>

    <!-- SCORE TABLE -->

    <div class="table-card">

        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Student</th>

                        <th>Score</th>

                        <th>Status</th>

                        <th>Comment</th>

                        <th>Scored At</th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($scores as $score): ?>

                        <tr>

                            <td>

                                #<?= e($score['id']) ?>

                            </td>

                            <td>

                                <?= e($score['full_name']) ?>

                            </td>

                            <td>

                                <span class="badge bg-success">

                                    <?= e($score['score']) ?>

                                </span>

                            </td>

                            <td>

                                <?php

                                $badge = 'bg-secondary';

                                if ($score['status'] === 'approved') {
                                    $badge = 'bg-success';
                                }

                                if ($score['status'] === 'rejected') {
                                    $badge = 'bg-danger';
                                }

                                if ($score['status'] === 'reviewing') {
                                    $badge = 'bg-warning';
                                }

                                ?>

                                <span class="badge <?= $badge ?>">

                                    <?= e($score['status']) ?>

                                </span>

                            </td>

                            <td>

                                <?= e($score['note']) ?>

                            </td>

                            <td>

                                <?= e($score['scored_at']) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>