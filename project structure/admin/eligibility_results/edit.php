<?php
// ============================================================
// admin/eligibility_results/edit.php
// Removed: Eligibility results are engine-generated, read-only.
// Admin cannot manually change PASS/FAIL status.
// ============================================================
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('admin');

setFlash('warning', 'Eligibility results are read-only. PASS / FAIL status is set exclusively by the Eligibility Engine.');
header('Location: index.php');
exit;