<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../config/db.php';

$pdo = getDB();

// Join với scholarship_programs để lấy tên chương trình

$sql = "
    SELECT
        eligibility_rules.*,
        scholarship_programs.name AS program_name

    FROM eligibility_rules

    INNER JOIN scholarship_programs
    ON eligibility_rules.program_id = scholarship_programs.id
";

$stmt = $pdo->query($sql);

$rules = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html>

<head>

    <title>Eligibility Rules</title>

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

<h1>Eligibility Rules Management</h1>

<a class="add-btn" href="create.php">
    Add New Rule
</a>

<table>

    <tr>

        <th>ID</th>

        <th>Scholarship Program</th>

        <th>Rule Type</th>

        <th>Operator</th>

        <th>Value</th>

        <th>Actions</th>

    </tr>

<?php foreach($rules as $rule) { ?>

<tr>

    <td><?= $rule['id'] ?></td>

    <td><?= $rule['program_name'] ?></td>

    <td><?= $rule['rule_type'] ?></td>

    <td><?= $rule['operator'] ?></td>

    <td><?= $rule['value'] ?></td>

    <td>

        <a href="edit.php?id=<?= $rule['id'] ?>">
            Edit
        </a>

        |

        <a
            href="delete.php?id=<?= $rule['id'] ?>"
            onclick="return confirm('Delete this rule?')"
        >
            Delete
        </a>

    </td>

</tr>

<?php } ?>

</table>

</body>
</html>