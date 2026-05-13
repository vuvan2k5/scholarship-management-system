<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../config/db.php';

$pdo = getDB();

// Join với scholarship_programs để lấy tên chương trình

$sql = "
    SELECT
        scoring_criteria.*,
        scholarship_programs.name AS program_name

    FROM scoring_criteria

    INNER JOIN scholarship_programs
    ON scoring_criteria.program_id = scholarship_programs.id
";

$stmt = $pdo->query($sql);

$criteria = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html>

<head>

    <title>Scoring Criteria</title>

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
            margin-bottom: 15px;
            padding: 10px 15px;
            background-color: #2563EB;
            color: white;
            border-radius: 5px;
        }

    </style>

</head>

<body>

<h1>Scoring Criteria Management</h1>

<a class="add-btn" href="create.php">
    Add New Criterion
</a>

<table>

    <tr>

        <th>ID</th>

        <th>Scholarship Program</th>

        <th>Criterion Name</th>

        <th>Weight (%)</th>

        <th>Max Score</th>

        <th>Actions</th>

    </tr>

<?php foreach($criteria as $criterion) { ?>

<tr>

    <td><?= $criterion['id'] ?></td>

    <td><?= $criterion['program_name'] ?></td>

    <td><?= $criterion['criterion_name'] ?></td>

    <td><?= $criterion['weight'] ?></td>

    <td><?= $criterion['max_score'] ?></td>

    <td>

        <a href="edit.php?id=<?= $criterion['id'] ?>">
            Edit
        </a>

        |

        <a
            href="delete.php?id=<?= $criterion['id'] ?>"
            onclick="return confirm('Delete this criterion?')"
        >
            Delete
        </a>

    </td>

</tr>

<?php } ?>

</table>

</body>
</html>