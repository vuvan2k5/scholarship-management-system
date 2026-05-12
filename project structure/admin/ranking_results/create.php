<?php
require_once "../../config/database.php";

if (isset($_POST['submit'])) {
    $application_id = $_POST['application_id'];
    $total_score = $_POST['total_score'];
    $rank = $_POST['rank'];
    $recommended = $_POST['recommended'];

    $sql = "INSERT INTO ranking_results (application_id, total_score, `rank`, recommended)
            VALUES ('$application_id', '$total_score', '$rank', '$recommended')";

    if (mysqli_query($conn, $sql)) {
        header("Location: index.php");
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<h2>Add Ranking Result</h2>

<form method="POST">
    <label>Application ID</label><br>
    <input type="number" name="application_id" required><br><br>

    <label>Total Score</label><br>
    <input type="number" step="0.01" name="total_score" required><br><br>

    <label>Rank</label><br>
    <input type="number" name="rank" required><br><br>

    <label>Recommended</label><br>
    <select name="recommended">
        <option value="0">No</option>
        <option value="1">Yes</option>
    </select><br><br>

    <button type="submit" name="submit">Save</button>
</form>

<a href="index.php">Back</a>
