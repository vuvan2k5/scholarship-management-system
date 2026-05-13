<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../config/db.php';

$pdo = getDB();

$error = "";

if(isset($_POST['submit'])) {

    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $budget = trim($_POST['budget']);
    $slots = trim($_POST['slots']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $status = $_POST['status'];

    // Validation

    if(
        empty($name) ||
        empty($budget) ||
        empty($slots)
    ) {

        $error = "Please fill in all required fields.";

    } else {

        $sql = "INSERT INTO scholarship_programs
        (
            name,
            description,
            budget,
            slots,
            start_date,
            end_date,
            status
        )
        VALUES
        (
            :name,
            :description,
            :budget,
            :slots,
            :start_date,
            :end_date,
            :status
        )";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([

            ':name' => $name,
            ':description' => $description,
            ':budget' => $budget,
            ':slots' => $slots,
            ':start_date' => $start_date,
            ':end_date' => $end_date,
            ':status' => $status

        ]);

        header("Location: index.php");
        exit;
    }
}

?>

<!DOCTYPE html>
<html>

<head>

    <title>Create Scholarship Program</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            margin: 30px;
        }

        h1 {
            color: #2563EB;
        }

        form {
            width: 500px;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            margin-bottom: 15px;
        }

        button {
            background-color: #2563EB;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
        }

        .error {
            color: red;
        }

    </style>

</head>

<body>

<h1>Create Scholarship Program</h1>

<p class="error">
    <?= $error ?>
</p>

<form method="POST">

    <label>Scholarship Name</label>
    <input type="text" name="name" required>

    <label>Description</label>
    <textarea name="description" rows="5"></textarea>

    <label>Budget (VND)</label>
    <input type="number" name="budget" required>

    <label>Slots</label>
    <input type="number" name="slots" required>

    <label>Start Date</label>
    <input type="date" name="start_date">

    <label>End Date</label>
    <input type="date" name="end_date">

    <label>Status</label>

    <select name="status">

        <option value="open">
            Open
        </option>

        <option value="closed">
            Closed
        </option>

    </select>

    <button type="submit" name="submit">
        Create Program
    </button>

</form>

</body>
</html>