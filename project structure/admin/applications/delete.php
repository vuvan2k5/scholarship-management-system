<?php
// ============================================================
// admin/applications/delete.php  –  DISABLED BY POLICY
// Admin is not permitted to delete application records.
// ============================================================
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('admin');

// Hard redirect — SQL DELETE intentionally removed
header('Location: index.php?policy=no_delete');
exit;
