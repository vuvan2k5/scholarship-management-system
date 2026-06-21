<?php
// ============================================================
// admin/users/edit.php  –  DISABLED BY POLICY
// User account editing is not permitted from User Management.
// Accounts are managed through the authentication system.
// ============================================================
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('admin');

// Hard redirect — this page is disabled by business policy
header('Location: index.php?policy=no_edit');
exit;