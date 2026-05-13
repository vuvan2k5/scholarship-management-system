<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../config/db.php';

$pdo = getDB();

$sql = "SELECT * FROM scholarship_programs";

$stmt = $pdo->query($sql);

$programs = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html>

<head>
    <title>Scholarship Programs</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            margin: 30px;
        }

        h1 {
            color: #2563EB;
        }

        a {
            text-decoration: none;
            color: blue;
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

        .add-btn {
            display: inline-block;
            margin-top: 10px;
            margin-bottom: 10px;
            padding: 10px 15px;
            background-color: #2563EB;
            color: white;
            border-radius: 5px;
        }

    </style>

</head>

<body>

<h1>Scholarship Programs Management</h1>

<a class="add-btn" href="create.php">
    Add New Scholarship Program
</a>

<table>

    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Budget</th>
        <th>Slots</th>
        <th>Start Date</th>
        <th>End Date</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>

<?php foreach($programs as $program) { ?>

<tr>

    <td><?= $program['id'] ?></td>

    <td><?= $program['name'] ?></td>

    <td><?= number_format($program['budget']) ?> VND</td>

    <td><?= $program['slots'] ?></td>

    <td><?= $program['start_date'] ?></td>

    <td><?= $program['end_date'] ?></td>

    <td><?= $program['status'] ?></td>

    <td>

        <a href="edit.php?id=<?= $program['id'] ?>">
            Edit
        </a>

        |

        <a href="delete.php?id=<?= $program['id'] ?>"
           onclick="return confirm('Delete this scholarship program?')">

           Delete

        </a>

    </td>

</tr>

<?php } ?>

</table>

</body>
</html>