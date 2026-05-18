<?php

include '../../config/db.php';

$pdo = getDB();

if (!isset($_GET['id'])) {
    die("ID not found.");
}

$id = $_GET['id'];

$sql = "SELECT * FROM eligibility_results WHERE id = ?";

$stmt = $pdo->prepare($sql);

$stmt->execute([$id]);

$result = $stmt->fetch();

if (!$result) {
    die("Eligibility result not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $application_id = $_POST['application_id'];
    $is_passed      = $_POST['is_passed'];
    $reason         = $_POST['reason'];

    $updateSql = "
        UPDATE eligibility_results
        SET
            application_id = ?,
            is_passed = ?,
            reason = ?
        WHERE id = ?
    ";

    $updateStmt = $pdo->prepare($updateSql);

    $updateStmt->execute([
        $application_id,
        $is_passed,
        $reason,
        $id
    ]);

    header("Location: index.php");

    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Eligibility Result</title>

    <style>

        body{
            font-family: Arial;
            padding: 20px;
        }

        form{
            width: 400px;
        }

        input,
        textarea,
        select{
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
        }

        button{
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }

    </style>

</head>

<body>

    <h2>Edit Eligibility Result</h2>

    <form method="POST">

        <label>Application ID</label>

        <input
            type="number"
            name="application_id"
            value="<?= $result['application_id'] ?>"
            required
        >

        <label>Status</label>

        <select name="is_passed">

            <option value="1"
                <?= $result['is_passed'] == 1 ? 'selected' : '' ?>>
                Passed
            </option>

            <option value="0"
                <?= $result['is_passed'] == 0 ? 'selected' : '' ?>>
                Failed
            </option>

        </select>

        <label>Reason</label>

        <textarea name="reason"><?= $result['reason'] ?></textarea>

        <button type="submit">
            Update
        </button>

    </form>

</body>
</html>