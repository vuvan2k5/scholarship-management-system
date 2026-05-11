<?php
require_once "../../config/database.php";

if (isset($_POST['submit'])) {
    $user_id = $_POST['user_id'];
    $title = $_POST['title'];
    $message = $_POST['message'];
    $is_read = $_POST['is_read'];

    $sql = "INSERT INTO notifications (user_id, title, message, is_read)
            VALUES ('$user_id', '$title', '$message', '$is_read')";

    if (mysqli_query($conn, $sql)) {
        header("Location: index.php");
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<h2>Add Notification</h2>

<form method="POST">
    <label>User ID</label><br>
    <input type="number" name="user_id" required><br><br>

    <label>Title</label><br>
    <input type="text" name="title" required><br><br>

    <label>Message</label><br>
    <textarea name="message" required></textarea><br><br>

    <label>Is Read</label><br>
    <select name="is_read">
        <option value="0">No</option>
        <option value="1">Yes</option>
    </select><br><br>

    <button type="submit" name="submit">Save</button>
</form>

<a href="index.php">Back</a>
