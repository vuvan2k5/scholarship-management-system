<?php
require_once "../../config/database.php";

if (isset($_POST['submit'])) {
    $application_id = $_POST['application_id'];
    $certificate_code = $_POST['certificate_code'];
    $issued_at = $_POST['issued_at'];
    $file_path = $_POST['file_path'];

    $sql = "INSERT INTO award_certificates 
            (application_id, certificate_code, issued_at, file_path)
            VALUES 
            ('$application_id', '$certificate_code', '$issued_at', '$file_path')";

    if (mysqli_query($conn, $sql)) {
        header("Location: index.php");
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<h2>Add Award Certificate</h2>

<form method="POST">
    <label>Application ID</label><br>
    <input type="number" name="application_id" required><br><br>

    <label>Certificate Code</label><br>
    <input type="text" name="certificate_code" required><br><br>

    <label>Issued At</label><br>
    <input type="datetime-local" name="issued_at"><br><br>

    <label>File Path</label><br>
    <input type="text" name="file_path"><br><br>

    <button type="submit" name="submit">Save</button>
</form>

<a href="index.php">Back</a>
