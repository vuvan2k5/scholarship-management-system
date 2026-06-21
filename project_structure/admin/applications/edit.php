<?php
// ============================================================
// admin/applications/edit.php  –  DISABLED BY POLICY
// Admin is not permitted to edit application data.
// Applications are student-owned records.
// ============================================================
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('admin');

header('Location: index.php?policy=no_edit');
exit;