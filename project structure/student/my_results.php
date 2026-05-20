<?php

$pageTitle = 'Scholarship Results';

require_once __DIR__ . '/../../config/db.php';

require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

requireRole('student');

require_once __DIR__ . '/../../includes/header.php';

require_once __DIR__ . '/../../includes/navbar.php';

$pdo = getDB();

$studentId = currentUserId();

/* =========================
   FETCH RESULTS
========================= */

$sql = "

    SELECT

        applications.id,

        applications.status,

        applications.eligible,

        applications.submitted_at,

        scholarship_programs.name AS program_name,

        COALESCE(SUM(evaluation_scores.score), 0)
            AS total_score,

        COUNT(DISTINCT evaluation_scores.criteria_id)
            AS scored_criteria

    FROM applications

    JOIN scholarship_programs
        ON applications.program_id = scholarship_programs.id

    LEFT JOIN evaluation_scores
        ON evaluation_scores.application_id = applications.id

    WHERE applications.student_id = ?

    GROUP BY applications.id

    ORDER BY applications.submitted_at DESC

";

$stmt = $pdo->prepare($sql);

$stmt->execute([$studentId]);

$results = $stmt->fetchAll();

?>

<div class="container py-4">

    <!-- PAGE HEADER -->

    <div class="mb-4">

        <h1 class="page-title">

            Scholarship Results

        </h1>

        <p class="page-subtitle">

            View your scholarship evaluation results

        </p>

    </div>

    <!-- EMPTY STATE -->

    <?php if (empty($results)): ?>

        <div class="card text-center py-5">

            <div class="card-body">

                <h4 class="mb-3">

                    No Results Found

                </h4>

                <p class="text-muted mb-4">

                    Your scholarship results will appear here after evaluation.

                </p>

                <a
                    href="apply.php"
                    class="btn btn-primary"
                >

                    Apply Scholarship

                </a>

            </div>

        </div>

    <?php else: ?>

        <!-- RESULTS TABLE -->

        <div class="card">

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>

                            <tr>

                                <th>ID</th>

                                <th>Scholarship Program</th>

                                <th>Status</th>

                                <th>Total Score</th>

                                <th>Eligibility</th>

                                <th>Submitted At</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($results as $row): ?>

                                <tr>

                                    <td>

                                        #<?= e($row['id']) ?>

                                    </td>

                                    <td>

                                        <?= e($row['program_name']) ?>

                                    </td>

                                    <td>

                                        <span
                                            class="
                                                badge
                                                badge-status-<?= e($row['status']) ?>
                                            "
                                        >

                                            <?= e(
                                                ucfirst($row['status'])
                                            ) ?>

                                        </span>

                                    </td>

                                    <td>

                                        <span class="badge bg-primary">

                                            <?= e(
                                                number_format(
                                                    (float)
                                                    $row['total_score'],
                                                    2
                                                )
                                            ) ?>

                                        </span>

                                    </td>

                                    <td>

                                        <?php if ($row['eligible'] === null): ?>

                                            <span class="badge bg-secondary">

                                                Pending

                                            </span>

                                        <?php elseif ($row['eligible']): ?>

                                            <span class="badge bg-success">

                                                Eligible

                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-danger">

                                                Not Eligible

                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?= e($row['submitted_at']) ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>