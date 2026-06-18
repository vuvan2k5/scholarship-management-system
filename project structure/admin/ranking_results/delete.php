<?php
// ============================================================
// admin/ranking_results/delete.php
// Removed: Ranking records are official audit records.
// They are replaced entirely when rankings are re-generated.
// ============================================================
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('admin');

setFlash('warning',
    'Ranking records cannot be individually deleted. '
    . 'Re-generating the ranking for a program replaces all records for that program.'
);
header('Location: index.php');
exit;
