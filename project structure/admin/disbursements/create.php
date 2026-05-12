<?php
require_once "../../config/database.php";

if (isset($_POST['submit'])) {
    $application_id = $_POST['application_id'];
    $amount = $_POST['amount'];
    $status = $_POST['status'];
    $disbursed_at = $_POST['disbursed_at'];
    $note = $_POST['note'];

    $sql = "INSERT INTO disbursements (application_id, amount, status, disbursed_at, note)
            VALUES ('$application_id', '$amount', '$status', '$disbursed_at', '$note')";

    if (mysqli_query($conn, $sql)) {
        header("Location: index.php");
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<h2>Add Disbursement</h2>

<form method="POST">
    <label>Application ID</label><br>
    <input type="number" name="application_id" required><br><br>

    <label>Amount</label><br>
    <input type="number" step="0.01" name="amount" required><br><br>

    <label>Status</label><br>
    <select name="status" required>
        <option value="pending">Pending</option>
        <option value="paid">Paid</option>
        <option value="failed">Failed</option>
    </select><br><br>

    <label>Disbursed At</label><br>
    <input type="datetime-local" name="disbursed_at"><br><br>

    <label>Note</label><br>
    <textarea name="note"></textarea><br><br>

    <button type="submit" name="submit">Save</button>
</form>

<a href="index.php">Back</a>
