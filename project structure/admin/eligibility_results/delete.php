<?php
// ============================================================
// admin/eligibility_results/delete.php
// Removed: Eligibility results must not be manually deleted.
// Results are engine-generated audit records.
// ============================================================
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('admin');

setFlash('warning', 'Eligibility results cannot be deleted. They are engine-generated audit records.');
header('Location: index.php');
exit;