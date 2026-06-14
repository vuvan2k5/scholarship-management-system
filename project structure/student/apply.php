<?php

$pageTitle = 'Apply Scholarship';

require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../includes/auth.php';

requireLogin();

requireRole('student');

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/navbar.php';

$pdo = getDB();

$error = '';

$success = '';

/* =========================
   FETCH SCHOLARSHIP PROGRAMS
========================= */

$programs = $pdo->query("

    SELECT

        id,
        name

    FROM scholarship_programs

    ORDER BY name

")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   HANDLE FORM SUBMISSION
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $programId = isset($_POST['program_id'])
        ? intval($_POST['program_id'])
        : 0;

    /* VALIDATION */

    if ($programId <= 0) {

        $error = 'Please select a scholarship program.';

    } else {

        $studentId = currentUserId();

        /* CHECK DUPLICATE */

        $check = $pdo->prepare("

            SELECT id

            FROM applications

            WHERE student_id = ?
            AND program_id = ?

        ");

        $check->execute([

            $studentId,
            $programId

        ]);

        if ($check->rowCount() > 0) {

            $error = 'You have already applied for this scholarship program.';

        } else {

            /* INSERT APPLICATION */

            $insert = $pdo->prepare("

                INSERT INTO applications (

                    student_id,
                    program_id,
                    status,
                    submitted_at

                )

                VALUES (?, ?, ?, NOW())

            ");

            $insert->execute([

                $studentId,
                $programId,
                'submitted'

            ]);

            $newAppId = $pdo->lastInsertId();
            require_once __DIR__ . '/../includes/eligibility.php';
            checkEligibility($pdo, (int)$newAppId);

            /* CREATE NOTIFICATION */

            $notify = $pdo->prepare("

                INSERT INTO notifications (

                    user_id,
                    title,
                    message,
                    type

                )

                VALUES (?, ?, ?, ?)

            ");

            $notify->execute([

                $studentId,

                'Application Submitted',

                'Your scholarship application was submitted successfully.',

                'success'

            ]);

            $success = 'Application submitted successfully.';
        }
    }
}

?>

<div class="container py-4">

    <!-- PAGE HEADER -->

    <div class="mb-4">

        <h1 class="page-title">

            Apply Scholarship

        </h1>

        <p class="page-subtitle">

            Submit your scholarship application

        </p>

    </div>

    <!-- ALERTS -->

    <?php if ($error): ?>

        <div class="alert alert-danger alert-dismissible fade show">

            <?= e($error) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>

    <?php if ($success): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <?= e($success) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>

    <!-- APPLICATION FORM -->

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="card">

                <div class="card-body">

                    <form method="POST">

                        <div class="mb-4">

                            <label class="form-label">

                                Scholarship Program

                            </label>

                            <select
                                name="program_id"
                                class="form-select"
                                required
                            >

                                <option value="">

                                    Select Program

                                </option>

                                <?php foreach ($programs as $program): ?>

                                    <option
                                        value="<?= $program['id'] ?>"
                                        <?= isset($_POST['program_id'])
                                            && intval($_POST['program_id'])
                                            === intval($program['id'])
                                                ? 'selected'
                                                : ''
                                        ?>
                                    >

                                        <?= e($program['name']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="d-flex gap-2">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >

                                Submit Application

                            </button>

                            <a
                                href="dashboard.php"
                                class="btn btn-secondary"
                            >

                                Back

                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>