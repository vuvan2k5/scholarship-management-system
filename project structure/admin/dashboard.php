<?php

require_once '../config/db.php';

require_once '../includes/auth.php';

requireLogin();

requireRole('admin');

$pdo = getDB();

$totalApplications = $pdo->query("
    SELECT COUNT(*) FROM applications
")->fetchColumn();

$totalStudents = $pdo->query("
    SELECT COUNT(*) FROM student_profiles
")->fetchColumn();

$totalScores = $pdo->query("
    SELECT COUNT(*) FROM evaluation_scores
")->fetchColumn();

$totalNotifications = $pdo->query("
    SELECT COUNT(*) FROM notifications
")->fetchColumn();

?>

<!DOCTYPE html>
<html>

<head>

    <title>Admin Dashboard</title>

    <style>

        body{
            font-family: Arial;
            padding: 30px;
            background: #f4f6f9;
        }

        h2{
            margin-bottom: 30px;
        }

        .dashboard{

            display: flex;

            gap: 20px;

            flex-wrap: wrap;
        }

        .card{

            background: white;

            padding: 20px;

            width: 200px;

            border-radius: 10px;

            box-shadow: 0 0 10px rgba(0,0,0,0.1);

            text-align: center;
        }

        .card h3{

            font-size: 32px;

            color: #007bff;

            margin-bottom: 10px;
        }

        .logout{

            display: inline-block;

            margin-top: 30px;

            color: red;

            text-decoration: none;
        }

    </style>

</head>

<body>

    <h2>
        Welcome Admin,
        <?= e(currentUserName()) ?>
    </h2>

    <div class="dashboard">

        <div class="card">

            <h3>
                <?= e($totalApplications) ?>
            </h3>

            <p>Applications</p>

        </div>

        <div class="card">

            <h3>
                <?= e($totalStudents) ?>
            </h3>

            <p>Students</p>

        </div>

        <div class="card">

            <h3>
                <?= e($totalScores) ?>
            </h3>

            <p>Scores</p>

        </div>

        <div class="card">

            <h3>
                <?= e($totalNotifications) ?>
            </h3>

            <p>Notifications</p>

        </div>

    </div>

    <a class="logout" href="../logout.php">
        Logout
    </a>

</body>

</html>