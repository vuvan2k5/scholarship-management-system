<?php
// ============================================================
// admin/evaluation_scores/edit.php
// Disabled for Admin: scoring is performed by Reviewers only.
// Admin is redirected with an informational message.
// ============================================================
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('admin');

setFlash('warning',
    'Evaluation scores can only be modified by Reviewers. '
    . 'Administrators have view-only access to scores.'
);
header('Location: index.php');
exit;
