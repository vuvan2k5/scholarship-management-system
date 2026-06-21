<?php
// ============================================================
// admin/ranking_results/edit.php
// Removed: Ranking records reflect approved scores exclusively.
// Administrators cannot manually modify ranks or scores.
// ============================================================
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('admin');

setFlash('warning',
    'Ranking records cannot be manually edited. '
    . 'Re-generate the ranking to refresh results from current evaluation scores.'
);
header('Location: index.php');
exit;
