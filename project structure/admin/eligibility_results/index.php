<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../config/db.php';

$pdo = getDB();

// Join với applications để lấy application_id dễ nhìn hơn

$sql = "
    SELECT
        eligibility_results.*,
        applications.id AS application_number

    FROM eligibility_results

    INNER JOIN applications
    ON eligibility_results.application_id = applications.id
";

$stmt = $pdo->query($sql);

$results = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html>

<head>

    <title>Eligibility Results</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            margin: 30px;
        }

        h1 {
            color: #2563EB;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table th,
        table td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: center;
        }

        table th {
            background-color: #2563EB;
            color: white;
        }

        .pass {
            color: green;
            font-weight: bold;
        }

        .fail {
            color: red;
            font-weight: bold;
        }

    </style>

</head>

<body>

<h1>Eligibility Results</h1>

<table>

    <tr>

        <th>ID</th>

        <th>Application ID</th>

        <th>Eligibility Status</th>

        <th>Reason</th>

        <th>Checked At</th>

    </tr>

<?php foreach($results as $result) { ?>

<tr>

    <td><?= $result['id'] ?></td>

    <td><?= $result['application_number'] ?></td>

    <td>

        <?php if($result['is_passed'] == 1) { ?>

            <span class="pass">
                PASS
            </span>

        <?php } else { ?>

            <span class="fail">
                FAIL
            </span>

        <?php } ?>

    </td>

    <td><?= $result['reason'] ?></td>

    <td><?= $result['checked_at'] ?></td>

</tr>

<?php } ?>

</table>

</body>
</html>