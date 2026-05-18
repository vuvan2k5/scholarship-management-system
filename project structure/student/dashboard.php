<?php

require_once '../includes/auth.php';

requireLogin();

requireRole('student');

?>

<!DOCTYPE html>
<html>

<head>

    <title>Student Dashboard</title>

    <style>

        body{
            font-family: Arial;
            padding: 30px;
        }

        .box{

            background: #f4f6f9;

            padding: 20px;

            border-radius: 10px;
        }

        h1{
            color: #007bff;
        }

        a{

            display: inline-block;

            margin-top: 20px;

            text-decoration: none;

            color: red;
        }

    </style>

</head>

<body>

    <div class="box">

        <h1>
            Welcome,
            <?= e(currentUserName()) ?>
        </h1>

        <p>
            Role:
            <?= e(currentRole()) ?>
        </p>

        <p>
            Student Dashboard is working successfully.
        </p>

        <a href="../logout.php">
            Logout
        </a>

    </div>

</body>

</html>