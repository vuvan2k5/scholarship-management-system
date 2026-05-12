<?php
require_once "../../config/database.php";

$sql = "SELECT * FROM ranking_results ORDER BY `rank` ASC";
$result = mysqli_query($conn, $sql);
?>

<h2>Ranking Results Management</h2>

<a href="create.php">Add Ranking Result</a>

<table border="1" cellpadding="10" cellspacing="0">
    <tr>
        <th>ID</th>
        <th>Application ID</th>
        <th>Total Score</th>
        <th>Rank</th>
        <th>Recommended</th>
        <th>Action</th>
    </tr>

    <?php while ($row = mysqli_fetch_assoc($result)) { ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= $row['application_id'] ?></td>
            <td><?= $row['total_score'] ?></td>
            <td><?= $row['rank'] ?></td>
            <td><?= $row['recommended'] ? 'Yes' : 'No' ?></td>
            <td>
                <a href="edit.php?id=<?= $row['id'] ?>">Edit</a> |
                <a href="delete.php?id=<?= $row['id'] ?>" onclick="return confirm('Delete this ranking result?')">Delete</a>
            </td>
        </tr>
    <?php } ?>
</table>
