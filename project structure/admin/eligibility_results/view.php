<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../config/db.php';

$pdo = getDB();

// Check ID

if(!isset($_GET['id'])) {

    die("Invalid request.");

}

$id = $_GET['id'];

// Join applications

$sql = "

    SELECT

        eligibility_results.*,

        applications.id AS application_number

    FROM eligibility_results

    INNER JOIN applications
    ON eligibility_results.application_id = applications.id

    WHERE eligibility_results.id = :id
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':id' => $id
]);

$result = $stmt->fetch();

// Nếu không tìm thấy

if(!$result) {

    die("Eligibility result not found.");

}

?>

<!DOCTYPE html>
<html>

<head>

    <title>View Eligibility Result</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            margin: 30px;
        }

        h1 {
            color: #2563EB;
        }

        .card {

            width: 600px;

            border: 1px solid #ccc;

            padding: 20px;

            border-radius: 10px;

            background-color: #f9f9f9;
        }

        p {
            margin-bottom: 15px;
        }

        strong {
            color: #2563EB;
        }

        .pass {
            color: green;
            font-weight: bold;
        }

        .fail {
            color: red;
            font-weight: bold;
        }

        a {

            display: inline-block;

            margin-top: 20px;

            text-decoration: none;

            background-color: #2563EB;

            color: white;

            padding: 10px 15px;

            border-radius: 5px;
        }

    </style>

</head>

<body>

<h1>Eligibility Result Detail</h1>

<div class="card">

    <p>

        <strong>ID:</strong>

        <?= $result['id'] ?>

    </p>

    <p>

        <strong>Application ID:</strong>

        <?= $result['application_number'] ?>

    </p>

    <p>

        <strong>Status:</strong>

        <?php if($result['is_passed'] == 1) { ?>

            <span class="pass">
                PASS
            </span>

        <?php } else { ?>

            <span class="fail">
                FAIL
            </span>

        <?php } ?>

    </p>

    <p>

        <strong>Reason:</strong>

        <?= $result['reason'] ?>

    </p>

    <p>

        <strong>Checked At:</strong>

        <?= $result['checked_at'] ?>

    </p>

</div>

<a href="index.php">
    Back
</a>

</body>
</html>