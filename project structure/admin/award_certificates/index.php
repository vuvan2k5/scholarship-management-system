<?php
require_once "../../config/database.php";

$sql = "SELECT * FROM award_certificates ORDER BY id DESC";
$result = mysqli_query($conn, $sql);
?>

<h2>Award Certificates Management</h2>

<a href="create.php">Add Award Certificate</a>

<table border="1" cellpadding="10" cellspacing="0">
    <tr>
        <th>ID</th>
        <th>Application ID</th>
        <th>Certificate Code</th>
        <th>Issued At</th>
        <th>File Path</th>
        <th>Action</th>
    </tr>

    <?php while ($row = mysqli_fetch_assoc($result)) { ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= $row['application_id'] ?></td>
            <td><?= $row['certificate_code'] ?></td>
            <td><?= $row['issued_at'] ?></td>
            <td><?= $row['file_path'] ?></td>
            <td>
                <a href="edit.php?id=<?= $row['id'] ?>">Edit</a> |
                <a href="delete.php?id=<?= $row['id'] ?>" onclick="return confirm('Delete this certificate?')">Delete</a>
            </td>
        </tr>
    <?php } ?>
</table>
