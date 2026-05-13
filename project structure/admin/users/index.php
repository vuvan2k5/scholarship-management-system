<?php

include '../../config/db.php';

$pdo = getDB();

$sql = "SELECT * FROM users";

$stmt = $pdo->query($sql);

$users = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html>

<head>
    <title>Users Management</title>

    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: center;
        }

        a {
            text-decoration: none;
        }

        .btn-add {
            background: green;
            color: white;
            padding: 8px 12px;
        }

        .btn-edit {
            color: orange;
        }

        .btn-delete {
            color: red;
        }
    </style>

</head>

<body>

<h1>Users Management</h1>

<a href="create.php" class="btn-add">
    Add New User
</a>

<br><br>

<table>

    <tr>
        <th>ID</th>
        <th>Full Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Student Code</th>
        <th>Actions</th>
    </tr>

<?php foreach($users as $user) { ?>

<tr>

    <td><?= $user['id'] ?></td>

    <td><?= $user['full_name'] ?></td>

    <td><?= $user['email'] ?></td>

    <td><?= $user['role'] ?></td>

    <td><?= $user['student_code'] ?></td>

    <td>

        <a class="btn-edit"
           href="edit.php?id=<?= $user['id'] ?>">
            Edit
        </a>

        <a class="btn-delete"
           href="delete.php?id=<?= $user['id'] ?>"
           onclick="return confirm('Are you sure?')">
            Delete
        </a>

    </td>

</tr>

<?php } ?>

</table>

</body>
</html>