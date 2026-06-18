<?php
// ============================================================
// admin/users/create.php  –  DISABLED BY POLICY
// User accounts are created through the registration system.
// Admin is not permitted to create users manually.
// ============================================================
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('admin');

// Hard redirect — this page is disabled by business policy
header('Location: index.php?policy=no_create');
exit;