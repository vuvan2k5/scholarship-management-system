<?php
require_once "../../config/database.php";

$id = $_GET['id'];

$sql = "DELETE FROM notifications WHERE id = $id";

if (mysqli_query($conn, $sql)) {
    header("Location: index.php");
    exit();
} else {
    echo "Error: " . mysqli_error($conn);
}
?>
