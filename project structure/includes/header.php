<?php
// includes/header.php – Header HTML dùng chung
// Yêu cầu: $pageTitle đã được set trước khi include
if (!defined('BASE_URL')) define('BASE_URL', '');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' – ' : '' ?>Scholarship System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .navbar-brand { font-weight: 700; }
        .sidebar { min-height: calc(100vh - 56px); background: #212529; }
        .sidebar .nav-link { color: #adb5bd; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,.1); border-radius: 6px; }
        .sidebar .nav-link i { width: 20px; }
        .card { border: none; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .table th { background: #f1f3f5; font-size: .85rem; }
        .badge-status-draft      { background:#6c757d; }
        .badge-status-submitted  { background:#0d6efd; }
        .badge-status-eligible   { background:#0dcaf0; color:#000; }
        .badge-status-ineligible { background:#dc3545; }
        .badge-status-approved   { background:#198754; }
        .badge-status-rejected   { background:#dc3545; }
        .badge-status-disbursed  { background:#6f42c1; }
    </style>
</head>
<body>
