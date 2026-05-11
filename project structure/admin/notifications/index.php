<?php
require_once "../../config/database.php";

$sql = "SELECT * FROM notifications ORDER BY id DESC";
$result = mysqli_query($conn, $sql);
?>

<h2>Notifications Management</h2>

<a href="create.php">Add Notification</a>

<table border="1" cellpadding="10" cellspacing="0">
    <tr>
        <th>ID</th>
        <th>User ID</th>
        <th>Title</th>
        <th>Message</th>
        <th>Is Read</th>
        <th>Created At</th>
        <th>Action</th>
    </tr>

    <?php while ($row = mysqli_fetch_assoc($result)) { ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= $row['user_id'] ?></td>
            <td><?= $row['title'] ?></td>
            <td><?= $row['message'] ?></td>
            <td><?= $row['is_read'] ? 'Yes' : 'No' ?></td>
            <td><?= $row['created_at'] ?></td>
            <td>
                <a href="edit.php?id=<?= $row['id'] ?>">Edit</a> |
                <a href="delete.php?id=<?= $row['id'] ?>" onclick="return confirm('Delete this notification?')">Delete</a>
            </td>
        </tr>
    <?php } ?>
</table>
