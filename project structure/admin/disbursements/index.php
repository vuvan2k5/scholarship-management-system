<?php
require_once "../../config/database.php";

$sql = "SELECT * FROM disbursements ORDER BY id DESC";
$result = mysqli_query($conn, $sql);
?>

<h2>Disbursements Management</h2>

<a href="create.php">Add Disbursement</a>

<table border="1" cellpadding="10" cellspacing="0">
    <tr>
        <th>ID</th>
        <th>Application ID</th>
        <th>Amount</th>
        <th>Status</th>
        <th>Disbursed At</th>
        <th>Note</th>
        <th>Action</th>
    </tr>

    <?php while ($row = mysqli_fetch_assoc($result)) { ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= $row['application_id'] ?></td>
            <td><?= $row['amount'] ?></td>
            <td><?= $row['status'] ?></td>
            <td><?= $row['disbursed_at'] ?></td>
            <td><?= $row['note'] ?></td>
            <td>
                <a href="edit.php?id=<?= $row['id'] ?>">Edit</a> |
                <a href="delete.php?id=<?= $row['id'] ?>" onclick="return confirm('Delete this disbursement?')">Delete</a>
            </td>
        </tr>
    <?php } ?>
</table>
