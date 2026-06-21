<?php
// ============================================================
// admin/eligibility_results/create.php
// Removed: Eligibility results are created exclusively by the
// Eligibility Engine. Admins cannot create them manually.
// ============================================================
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('admin');

setFlash('warning', 'Eligibility results are created exclusively by the Eligibility Engine. Use the Engine module to run evaluations.');
header('Location: ../eligibility_engine/index.php');
exit;