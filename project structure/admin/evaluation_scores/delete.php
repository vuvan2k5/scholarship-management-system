<?php
// ============================================================
// admin/evaluation_scores/delete.php
// Disabled for Admin: evaluation scores must not be deleted
// from the admin panel — they are official reviewer records.
// ============================================================
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('admin');

setFlash('warning',
    'Evaluation score records cannot be deleted. '
    . 'They are official reviewer submissions used in Ranking Results.'
);
header('Location: index.php');
exit;
