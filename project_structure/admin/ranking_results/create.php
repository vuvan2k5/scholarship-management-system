<?php
// ============================================================
// admin/ranking_results/create.php
// Removed: Rankings are auto-generated from evaluation scores.
// Admins cannot create ranking records manually.
// ============================================================
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('admin');

setFlash('warning',
    'Rankings are auto-generated from reviewer evaluation scores. '
    . 'Use the "Generate Ranking" panel on the Rankings page instead.'
);
header('Location: index.php');
exit;
