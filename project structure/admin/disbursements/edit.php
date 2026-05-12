<?php
require_once "../../config/database.php";

$id = $_GET['id'];

$sql = "SELECT * FROM disbursements WHERE id = $id";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);

$datetimeValue = "";
if (!empty($row['disbursed_at'])) {
    $datetimeValue = date("Y-m-d\TH:i", strtotime($row['disbursed_at']));
}

if (isset($_POST['submit'])) {
    $application_id = $_POST['application_id'];
    $amount = $_POST['amount'];
    $status = $_POST['status'];
    $disbursed_at = $_POST['disbursed_at'];
    $note = $_POST['note'];

    $update = "UPDATE disbursements
               SET application_id = '$application_id',
                   amount = '$amount',
                   status = '$status',
                   disbursed_at = '$disbursed_at',
                   note = '$note'
               WHERE id = $id";

    if (mysqli_query($conn, $update)) {
        header("Location: index.php");
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<h2>Edit Disbursement</h2>

<form method="POST">
    <label>Application ID</label><br>
    <input type="number" name="application_id" value="<?= $row['application_id'] ?>" required><br><br>

    <label>Amount</label><br>
    <input type="number" step="0.01" name="amount" value="<?= $row['amount'] ?>" required><br><br>

    <label>Status</label><br>
    <select name="status" required>
        <option value="pending" <?= $row['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="paid" <?= $row['status'] == 'paid' ? 'selected' : '' ?>>Paid</option>
        <option value="failed" <?= $row['status'] == 'failed' ? 'selected' : '' ?>>Failed</option>
    </select><br><br>

    <label>Disbursed At</label><br>
    <input type="datetime-local" name="disbursed_at" value="<?= $datetimeValue ?>"><br><br>

    <label>Note</label><br>
    <textarea name="note"><?= $row['note'] ?></textarea><br><br>

    <button type="submit" name="submit">Update</button>
</form>

<a href="index.php">Back</a>
