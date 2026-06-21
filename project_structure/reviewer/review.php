<?php

$pageTitle = 'Review Application';

require_once '../config/db.php';

require_once '../includes/auth.php';

requireLogin();

requireRole('council', 'reviewer');

require_once '../includes/header.php';

require_once '../includes/navbar.php';

$pdo = getDB();

/* =========================
   GET APPLICATION ID
========================= */

$id = (int) ($_GET['id'] ?? 0);

/* =========================
   FETCH APPLICATION
========================= */

$sql = "

    SELECT

        applications.*,

        users.full_name,

        users.email,

        scholarship_programs.name AS program_name,

        student_profiles.faculty,

        student_profiles.major,

        student_profiles.gpa,

        student_profiles.activities_count,

        student_profiles.family_income

    FROM applications

    JOIN users
        ON applications.student_id = users.id

    JOIN scholarship_programs
        ON applications.program_id = scholarship_programs.id

    LEFT JOIN student_profiles
        ON student_profiles.student_id = users.id

    WHERE applications.id = ?

    LIMIT 1

";

$stmt = $pdo->prepare($sql);

$stmt->execute([$id]);

$app = $stmt->fetch();

/* =========================
   FETCH EVIDENCE FILES
========================= */
$evidStmt = $pdo->prepare("SELECT * FROM application_evidence WHERE application_id = ? ORDER BY uploaded_at ASC");
$evidStmt->execute([$id]);
$evidenceFiles = $evidStmt->fetchAll();

/* =========================
   APPLICATION NOT FOUND
========================= */

if (!$app) {

    echo "

        <div class='container py-5'>

            <div class='alert alert-danger'>

                Application not found.

            </div>

        </div>

    ";

    require_once '../includes/footer.php';

    exit;
}

/* =========================
   HANDLE REVIEW SUBMISSION
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $score = (float) ($_POST['score'] ?? 0);

    $note = trim($_POST['note'] ?? '');

    $status = $_POST['status'] ?? 'reviewing';

    /* INSERT EVALUATION */

    $insert = "

        INSERT INTO evaluation_scores (

            application_id,
            criteria_id,
            council_id,
            score,
            note

        )

        VALUES (?, ?, ?, ?, ?)

    ";

    $stmt = $pdo->prepare($insert);

    $stmt->execute([

        $id,
        1,
        currentUserId(),
        $score,
        $note

    ]);

    /* UPDATE APPLICATION STATUS */

    $update = "

        UPDATE applications

        SET status = ?

        WHERE id = ?

    ";

    $stmt = $pdo->prepare($update);

    $stmt->execute([$status, $id]);

    require_once __DIR__ . '/../admin/evaluation_scores/helpers.php';
    processApplicationScores($pdo, $id);

    setFlash(
        'success',
        'Application reviewed successfully.'
    );

    redirect('applications.php');
}

?>

<div class="container py-4">

    <!-- PAGE HEADER -->

    <div class="mb-4">

        <h1 class="page-title">

            Review Application

        </h1>

        <p class="page-subtitle">

            Evaluate student scholarship application

        </p>

    </div>

    <div class="row g-4">

        <!-- STUDENT INFO -->

        <div class="col-lg-6">

            <div class="card">

                <div class="card-body">

                    <h4 class="mb-4">

                        Student Information

                    </h4>

                    <table class="table">

                        <tr>

                            <th>Student Name</th>

                            <td>
                                <?= e($app['full_name']) ?>
                            </td>

                        </tr>

                        <tr>

                            <th>Email</th>

                            <td>
                                <?= e($app['email']) ?>
                            </td>

                        </tr>

                        <tr>

                            <th>Faculty</th>

                            <td>
                                <?= e($app['faculty']) ?>
                            </td>

                        </tr>

                        <tr>

                            <th>Major</th>

                            <td>
                                <?= e($app['major']) ?>
                            </td>

                        </tr>

                        <tr>

                            <th>GPA</th>

                            <td>
                                <?= e($app['gpa']) ?>
                            </td>

                        </tr>

                        <tr>

                            <th>Activities</th>

                            <td>
                                <?= e($app['activities_count']) ?>
                            </td>

                        </tr>

                        <tr>

                            <th>Family Income</th>

                            <td>
                                <?= number_format(
                                    $app['family_income']
                                ) ?>
                                VND
                            </td>

                        </tr>

                    </table>

                </div>

            </div>

        </div>

        <!-- EVIDENCE FILES -->
        <div class="col-lg-12">
            <div class="card mb-4" style="border-top:3px solid #2563eb;">
                <div class="card-body">
                    <h4 class="mb-4"><i class="bi bi-paperclip me-2 text-primary"></i> Evidence Documents Submitted</h4>
                    <?php if (empty($evidenceFiles)): ?>
                        <div class="alert alert-secondary">
                            <i class="bi bi-info-circle me-2"></i>This student did not upload any evidence files.
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                        <?php foreach ($evidenceFiles as $ev):
                            $isImage  = strpos($ev['file_type'], 'image/') === 0;
                            $isPdf    = $ev['file_type'] === 'application/pdf';
                            $icon     = $isImage ? '🖼️' : ($isPdf ? '📄' : '📝');
                            // Encode each path segment to handle spaces (e.g. "project structure")
                            $rawPath  = str_replace('\\', '/', $ev['file_path']);
                            $segments = explode('/', trim($rawPath, '/'));
                            $fileUrl  = '/' . implode('/', array_map('rawurlencode', $segments));
                            $statusBg = ['pending'=>'warning','approved'=>'success','rejected'=>'danger'][$ev['status']] ?? 'secondary';
                        ?>
                            <div class="col-md-6">
                                <div style="border:1px solid #e2e8f0;border-radius:10px;padding:14px;background:#fafafa;height:100%;">
                                    <div style="display:flex;gap:12px;align-items:flex-start;">
                                        <span style="font-size:32px;line-height:1;flex-shrink:0;"><?= $icon ?></span>
                                        <div style="flex:1;min-width:0;">
                                            <div style="font-size:13px;font-weight:700;color:#0f172a;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                                <?= e($ev['original_name']) ?>
                                            </div>
                                            <div style="font-size:11.5px;color:#64748b;margin-bottom:10px;">
                                                <?= number_format($ev['file_size']/1024, 1) ?> KB &middot; <?= e($ev['file_type']) ?>
                                            </div>
                                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                                <span class="badge bg-<?= $statusBg ?> text-capitalize"><?= e($ev['status']) ?></span>
                                                <?php if ($isImage): ?>
                                                    <a href="<?= e($fileUrl) ?>" target="_blank" class="btn btn-sm btn-primary" style="font-size:11px;">
                                                        <i class="bi bi-eye me-1"></i>View Image
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?= e($fileUrl) ?>" target="_blank" class="btn btn-sm btn-outline-primary" style="font-size:11px;">
                                                        <i class="bi bi-box-arrow-up-right me-1"></i>Open File
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- REVIEW FORM -->

        <div class="col-lg-6">

            <div class="card">

                <div class="card-body">

                    <h4 class="mb-4">

                        Evaluation Form

                    </h4>

                    <form method="POST">

                        <div class="mb-3">

                            <label class="form-label">

                                Score

                            </label>

                            <input
                                type="number"
                                name="score"
                                class="form-control"
                                min="0"
                                max="100"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label">

                                Status

                            </label>

                            <select
                                name="status"
                                class="form-select"
                            >

                                <option value="reviewing">

                                    Reviewing

                                </option>

                                <option value="approved">

                                    Approved

                                </option>

                                <option value="rejected">

                                    Rejected

                                </option>

                            </select>

                        </div>

                        <div class="mb-4">

                            <label class="form-label">

                                Reviewer Comment

                            </label>

                            <textarea
                                name="note"
                                rows="5"
                                class="form-control"
                            ></textarea>

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >

                            Submit Review

                        </button>

                        <a
                            href="applications.php"
                            class="btn btn-secondary"
                        >

                            Back

                        </a>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>