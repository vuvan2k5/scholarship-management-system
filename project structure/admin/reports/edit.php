<?php
require_once "../../config/database.php";

$id = $_GET['id'];

$sql = "SELECT * FROM reports WHERE id = $id";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);

$datetimeValue = "";
if (!empty($row['created_at'])) {
    $datetimeValue = date("Y-m-d\TH:i", strtotime($row['created_at']));
}

if (isset($_POST['submit'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $created_at = $_POST['created_at'];

    $update = "UPDATE reports
               SET title = '$title',
                   content = '$content',
                   created_at = '$created_at'
               WHERE id = $id";

    if (mysqli_query($conn, $update)) {
        header("Location: index.php");
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<h2>Edit Report</h2>

<form method="POST">
    <label>Title</label><br>
    <input type="text" name="title" value="<?= $row['title'] ?>" required><br><br>

    <label>Content</label><br>
    <textarea name="content" required><?= $row['content'] ?></textarea><br><br>

    <label>Created At</label><br>
    <input type="datetime-local" name="created_at" value="<?= $datetimeValue ?>"><br><br>

    <button type="submit" name="submit">Update</button>
</form>

<a href="index.php">Back</a>
