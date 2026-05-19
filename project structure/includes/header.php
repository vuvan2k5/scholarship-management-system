<?php
// includes/header.php – Header HTML dùng chung
// Yêu cầu: $pageTitle đã được set trước khi include
if (!defined('BASE_URL')) define('BASE_URL', '');
require_once __DIR__ . '/auth.php';
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

body{

    background:#f4f6f9;

    font-family: 'Segoe UI', sans-serif;

    color:#111827;
}

.navbar{

    background:#1f2937 !important;
}

.navbar-brand{

    font-weight:700;

    font-size:1.4rem;
}

.card{

    border:none;

    border-radius:12px;

    box-shadow:0 2px 8px rgba(0,0,0,.05);
}

.table{

    background:white;
}

.table th{

    background:#f9fafb;

    border-bottom:2px solid #e5e7eb;
}

.btn-primary{

    background:#2563eb;

    border:none;
}

.btn-primary:hover{

    background:#1d4ed8;
}

.badge{

    padding:6px 10px;

    border-radius:8px;
}

.container{

    max-width:1400px;
}

.sidebar{

    background:#111827;
}

.sidebar .nav-link{

    color:#d1d5db;
}

.sidebar .nav-link:hover{

    background:#1f2937;

    color:white;
}

h1,h2,h3,h4{

    font-weight:700;
}

</style>
</head>
<body>
