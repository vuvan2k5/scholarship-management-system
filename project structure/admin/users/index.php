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
</head>
<body>

<h1>Users Management</h1>

<a href="create.php">
    Add New User
</a>

<br><br>

<table border="1" cellpadding="10">

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

        <a href="edit.php?id=<?= $user['id'] ?>">
            Edit
        </a>

        |

        <a href="delete.php?id=<?= $user['id'] ?>">
            Delete
        </a>

    </td>

</tr>

<?php } ?>

</table>

</body>
</html>
