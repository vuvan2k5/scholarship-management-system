<?php
require_once "../../config/database.php";

if (isset($_POST['submit'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $created_at = $_POST['created_at'];

    $sql = "INSERT INTO reports (title, content, created_at)
            VALUES ('$title', '$content', '$created_at')";

    if (mysqli_query($conn, $sql)) {
        header("Location: index.php");
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<h2>Add Report</h2>

<form method="POST">
    <label>Title</label><br>
    <input type="text" name="title" required><br><br>

    <label>Content</label><br>
    <textarea name="content" required></textarea><br><br>

    <label>Created At</label><br>
    <input type="datetime-local" name="created_at"><br><br>

    <button type="submit" name="submit">Save</button>
</form>

<a href="index.php">Back</a>
