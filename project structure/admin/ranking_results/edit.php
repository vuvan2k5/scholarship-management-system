<?php
require_once "../../config/database.php";

$id = $_GET['id'];

$sql = "SELECT * FROM ranking_results WHERE id = $id";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);

if (isset($_POST['submit'])) {
    $application_id = $_POST['application_id'];
    $total_score = $_POST['total_score'];
    $rank = $_POST['rank'];
    $recommended = $_POST['recommended'];

    $update = "UPDATE ranking_results
               SET application_id = '$application_id',
                   total_score = '$total_score',
                   `rank` = '$rank',
                   recommended = '$recommended'
               WHERE id = $id";

    if (mysqli_query($conn, $update)) {
        header("Location: index.php");
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<h2>Edit Ranking Result</h2>

<form method="POST">
    <label>Application ID</label><br>
    <input type="number" name="application_id" value="<?= $row['application_id'] ?>" required><br><br>

    <label>Total Score</label><br>
    <input type="number" step="0.01" name="total_score" value="<?= $row['total_score'] ?>" required><br><br>

    <label>Rank</label><br>
    <input type="number" name="rank" value="<?= $row['rank'] ?>" required><br><br>

    <label>Recommended</label><br>
    <select name="recommended">
        <option value="0" <?= $row['recommended'] == 0 ? 'selected' : '' ?>>No</option>
        <option value="1" <?= $row['recommended'] == 1 ? 'selected' : '' ?>>Yes</option>
    </select><br><br>

    <button type="submit" name="submit">Update</button>
</form>

<a href="index.php">Back</a>
