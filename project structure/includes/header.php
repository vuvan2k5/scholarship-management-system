<?php
// ============================================================
// includes/header.php  –  Global HTML head + body open
// $pageTitle must be set before including this file
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? e($pageTitle) . ' – ' : '' ?>Scholarship System</title>

  <!-- Bootstrap 5 (local fallback first, CDN second) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet" crossorigin="anonymous"
        onerror="this.onerror=null;">

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
        rel="stylesheet" crossorigin="anonymous"
        onerror="this.onerror=null;">

  <!-- Design System -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

  <!-- Admin helpers -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">

  <!-- Student role theme (blue + white) -->
  <?php if (function_exists('currentRole') && currentRole() === 'student'): ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/student.css">
  <?php endif; ?>
</head>
<body class="ABC-TEST-123">
