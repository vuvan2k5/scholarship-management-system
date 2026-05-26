<?php

$pageTitle = 'My Notifications';

require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../includes/auth.php';

requireLogin();

requireRole('student');

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/navbar.php';

$pdo = getDB();

$userId = currentUserId();

/* =========================
   MARK ALL AS READ
========================= */

if (isset($_GET['mark_all'])) {

    $update = $pdo->prepare("

        UPDATE notifications

        SET is_read = 1

        WHERE user_id = ?

    ");

    $update->execute([$userId]);

    redirect('notifications.php');
}

/* =========================
   FETCH NOTIFICATIONS
========================= */

$sql = "

    SELECT *

    FROM notifications

    WHERE user_id = ?

    ORDER BY created_at DESC

";

$stmt = $pdo->prepare($sql);

$stmt->execute([$userId]);

$notifications = $stmt->fetchAll();

?>

<div class="container py-4">

    <!-- PAGE HEADER -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h1 class="page-title">

                My Notifications

            </h1>

            <p class="page-subtitle">

                View all system notifications and updates

            </p>

        </div>

        <a
            href="?mark_all=1"
            class="btn btn-secondary"
        >

            <i class="bi bi-check2-all me-2"></i>

            Mark All as Read

        </a>

    </div>

    <!-- EMPTY STATE -->

    <?php if (empty($notifications)): ?>

        <div class="card text-center py-5">

            <div class="card-body">

                <h4 class="mb-3">

                    No Notifications Found

                </h4>

                <p class="text-muted">

                    You currently have no notifications.

                </p>

            </div>

        </div>

    <?php else: ?>

        <!-- NOTIFICATION TABLE -->

        <div class="card">

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>

                            <tr>

                                <th>ID</th>

                                <th>Title</th>

                                <th>Message</th>

                                <th>Type</th>

                                <th>Status</th>

                                <th>Date</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($notifications as $note): ?>

                                <tr
                                    class="<?= !$note['is_read']
                                        ? 'table-warning'
                                        : ''
                                    ?>"
                                >

                                    <td>

                                        #<?= e($note['id']) ?>

                                    </td>

                                    <td>

                                        <?= e($note['title']) ?>

                                    </td>

                                    <td style="max-width:350px;">

                                        <?= e($note['message']) ?>

                                    </td>

                                    <td>

                                        <?php

                                        $badge = 'bg-secondary';

                                        if ($note['type'] === 'success') {
                                            $badge = 'bg-success';
                                        }

                                        if ($note['type'] === 'warning') {
                                            $badge = 'bg-warning';
                                        }

                                        if ($note['type'] === 'error') {
                                            $badge = 'bg-danger';
                                        }

                                        ?>

                                        <span class="badge <?= $badge ?>">

                                            <?= e(
                                                ucfirst($note['type'])
                                            ) ?>

                                        </span>

                                    </td>

                                    <td>

                                        <?php if ($note['is_read']): ?>

                                            <span class="badge bg-success">

                                                Read

                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-warning">

                                                Unread

                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?= e($note['created_at']) ?>

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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>