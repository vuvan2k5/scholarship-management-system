<?php
require_once "../../config/database.php";

$id = $_GET['id'];

$sql = "SELECT * FROM notifications WHERE id = $id";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);

if (isset($_POST['submit'])) {
    $user_id = $_POST['user_id'];
    $title = $_POST['title'];
    $message = $_POST['message'];
    $is_read = $_POST['is_read'];

    $update = "UPDATE notifications 
               SET user_id = '$user_id',
                   title = '$title',
                   message = '$message',
                   is_read = '$is_read'
               WHERE id = $id";

    if (mysqli_query($conn, $update)) {
        header("Location: index.php");
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<h2>Edit Notification</h2>

<form method="POST">
    <label>User ID</label><br>
    <input type="number" name="user_id" value="<?= $row['user_id'] ?>" required><br><br>

    <label>Title</label><br>
    <input type="text" name="title" value="<?= $row['title'] ?>" required><br><br>

    <label>Message</label><br>
    <textarea name="message" required><?= $row['message'] ?></textarea><br><br>

    <label>Is Read</label><br>
    <select name="is_read">
        <option value="0" <?= $row['is_read'] == 0 ? 'selected' : '' ?>>No</option>
        <option value="1" <?= $row['is_read'] == 1 ? 'selected' : '' ?>>Yes</option>
    </select><br><br>

    <button type="submit" name="submit">Update</button>
</form>

<a href="index.php">Back</a>
