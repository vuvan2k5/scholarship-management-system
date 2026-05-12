<?php
require_once "../../config/database.php";

$id = $_GET['id'];

$sql = "SELECT * FROM award_certificates WHERE id = $id";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);

$datetimeValue = "";
if (!empty($row['issued_at'])) {
    $datetimeValue = date("Y-m-d\TH:i", strtotime($row['issued_at']));
}

if (isset($_POST['submit'])) {
    $application_id = $_POST['application_id'];
    $certificate_code = $_POST['certificate_code'];
    $issued_at = $_POST['issued_at'];
    $file_path = $_POST['file_path'];

    $update = "UPDATE award_certificates
               SET application_id = '$application_id',
                   certificate_code = '$certificate_code',
                   issued_at = '$issued_at',
                   file_path = '$file_path'
               WHERE id = $id";

    if (mysqli_query($conn, $update)) {
        header("Location: index.php");
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<h2>Edit Award Certificate</h2>

<form method="POST">
    <label>Application ID</label><br>
    <input type="number" name="application_id" value="<?= $row['application_id'] ?>" required><br><br>

    <label>Certificate Code</label><br>
    <input type="text" name="certificate_code" value="<?= $row['certificate_code'] ?>" required><br><br>

    <label>Issued At</label><br>
    <input type="datetime-local" name="issued_at" value="<?= $datetimeValue ?>"><br><br>

    <label>File Path</label><br>
    <input type="text" name="file_path" value="<?= $row['file_path'] ?>"><br><br>

    <button type="submit" name="submit">Update</button>
</form>

<a href="index.php">Back</a>
