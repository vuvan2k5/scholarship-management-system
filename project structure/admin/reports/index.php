<?php
require_once "../../config/database.php";

$sql = "SELECT * FROM reports ORDER BY id DESC";
$result = mysqli_query($conn, $sql);
?>

<h2>Reports Management</h2>

<a href="create.php">Add Report</a>

<table border="1" cellpadding="10" cellspacing="0">
    <tr>
        <th>ID</th>
        <th>Title</th>
        <th>Content</th>
        <th>Created At</th>
        <th>Action</th>
    </tr>

    <?php while ($row = mysqli_fetch_assoc($result)) { ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= $row['title'] ?></td>
            <td><?= $row['content'] ?></td>
            <td><?= $row['created_at'] ?></td>
            <td>
                <a href="edit.php?id=<?= $row['id'] ?>">Edit</a> |
                <a href="delete.php?id=<?= $row['id'] ?>" onclick="return confirm('Delete this report?')">Delete</a>
            </td>
        </tr>
    <?php } ?>
</table>
