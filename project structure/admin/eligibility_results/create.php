<?php

include '../../config/db.php';

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $application_id = $_POST['application_id'];
    $is_passed      = $_POST['is_passed'];
    $reason         = $_POST['reason'];

    $sql = "
        INSERT INTO eligibility_results
        (
            application_id,
            is_passed,
            reason
        )
        VALUES
        (
            ?,
            ?,
            ?
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $application_id,
        $is_passed,
        $reason
    ]);

    header("Location: index.php");

    exit;
}
?>

<!DOCTYPE html>
<html>
<head>

    <title>Create Eligibility Result</title>

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
            background: green;
            color: white;
            border: none;
            cursor: pointer;
        }

    </style>

</head>

<body>

    <h2>Create Eligibility Result</h2>

    <form method="POST">

        <label>Application ID</label>

        <input
            type="number"
            name="application_id"
            required
        >

        <label>Status</label>

        <select name="is_passed">

            <option value="1">
                Passed
            </option>

            <option value="0">
                Failed
            </option>

        </select>

        <label>Reason</label>

        <textarea
            name="reason"
            rows="5"
        ></textarea>

        <button type="submit">
            Create
        </button>

    </form>

</body>
</html>