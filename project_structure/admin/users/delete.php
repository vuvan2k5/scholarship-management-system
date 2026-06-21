<?php
// ============================================================
// admin/users/delete.php  –  DISABLED BY POLICY
// Admin is not permitted to delete user accounts.
// ============================================================
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('admin');

// Hard redirect — this page is disabled by business policy
header('Location: index.php?policy=no_delete');
exit;