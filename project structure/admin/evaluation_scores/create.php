<?php
// ============================================================
// admin/evaluation_scores/create.php
// Disabled for Admin: scoring is performed by Reviewers only.
// Admin is redirected with an informational message.
// ============================================================
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('admin');

setFlash('warning',
    'Evaluation scores are submitted by Reviewers only. '
    . 'Administrators can view scores but cannot create or modify them.'
);
header('Location: index.php');
exit;
