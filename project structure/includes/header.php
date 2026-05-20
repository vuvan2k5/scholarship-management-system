<?php
// ============================================================
// includes/header.php
// Global HTML Header
// $pageTitle should be set before including this file
// ============================================================

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>

        <?= isset($pageTitle)
            ? e($pageTitle) . ' – '
            : '' ?>

        Scholarship System

    </title>

    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <!-- Custom Admin CSS -->

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>/assets/css/admin.css"
    >

    <!-- Global Styles -->

    <style>

        body{

            background: #f1f5f9;

            font-family: 'Segoe UI', sans-serif;

            color: #1e293b;
        }

        /* NAVBAR */

        .navbar{

            background: linear-gradient(
                90deg,
                #0f172a,
                #1e293b
            ) !important;

            padding: 14px 24px;

            box-shadow:
                0 2px 10px rgba(0,0,0,0.08);
        }

        .navbar-brand{

            font-weight: 700;

            font-size: 30px;

            color: white !important;
        }

        .navbar .nav-link{

            color: rgba(255,255,255,0.8) !important;

            transition: .2s;
        }

        .navbar .nav-link:hover{

            color: white !important;
        }

        /* CONTAINER */

        .container{

            margin-top: 30px;
        }

        /* CARDS */

        .card{

            border: none;

            border-radius: 18px;

            background: white;

            box-shadow:
                0 4px 14px rgba(0,0,0,0.06);

            transition: .2s;
        }

        .card:hover{

            transform: translateY(-2px);
        }

        .card-body{

            padding: 28px;
        }

        /* TABLE */

        .table{

            margin-bottom: 0;
        }

        .table thead{

            background: #f8fafc;
        }

        .table th{

            border-bottom:
                2px solid #e2e8f0;

            color: #334155;

            font-weight: 600;
        }

        .table td{

            vertical-align: middle;

            padding: 16px 10px;
        }

        /* BUTTONS */

        .btn{

            border-radius: 10px;

            padding: 10px 18px;

            font-weight: 500;
        }

        .btn-primary{

            background: #2563eb;

            border: none;
        }

        .btn-primary:hover{

            background: #1d4ed8;
        }

        .btn-danger{

            border: none;
        }

        .btn-warning{

            border: none;

            color: white;
        }

        /* BADGES */

        .badge{

            padding: 8px 12px;

            border-radius: 8px;

            font-size: 12px;
        }

        /* TITLES */

        h1,h2,h3,h4,h5{

            font-weight: 700;
        }

        /* QUICK ACTION */

        .quick-btn{

            min-width: 180px;
        }

    </style>

</head>

<body>