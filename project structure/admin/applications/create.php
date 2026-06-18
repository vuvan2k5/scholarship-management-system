<?php
// ============================================================
// admin/applications/create.php  –  DISABLED BY POLICY
// Applications are submitted by students only.
// ============================================================
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('admin');

header('Location: index.php?policy=no_create');
exit;
