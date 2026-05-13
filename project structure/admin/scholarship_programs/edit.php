<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../config/db.php';

$pdo = getDB();

$id = $_GET['id'];

// Get current scholarship program

$sql = "SELECT * FROM scholarship_programs WHERE id = :id";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':id' => $id
]);

$program = $stmt->fetch();

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

        $update = "UPDATE scholarship_programs SET

            name = :name,
            description = :description,
            budget = :budget,
            slots = :slots,
            start_date = :start_date,
            end_date = :end_date,
            status = :status

            WHERE id = :id
        ";

        $stmt = $pdo->prepare($update);

        $stmt->execute([

            ':name' => $name,
            ':description' => $description,
            ':budget' => $budget,
            ':slots' => $slots,
            ':start_date' => $start_date,
            ':end_date' => $end_date,
            ':status' => $status,
            ':id' => $id

        ]);

        header("Location: index.php");
        exit;
    }
}

?>

<!DOCTYPE html>
<html>

<head>

    <title>Edit Scholarship Program</title>

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

<h1>Edit Scholarship Program</h1>

<p class="error">
    <?= $error ?>
</p>

<form method="POST">

    <label>Scholarship Name</label>
    <input
        type="text"
        name="name"
        value="<?= $program['name'] ?>"
        required
    >

    <label>Description</label>

    <textarea
        name="description"
        rows="5"
    ><?= $program['description'] ?></textarea>

    <label>Budget (VND)</label>

    <input
        type="number"
        name="budget"
        value="<?= $program['budget'] ?>"
        required
    >

    <label>Slots</label>

    <input
        type="number"
        name="slots"
        value="<?= $program['slots'] ?>"
        required
    >

    <label>Start Date</label>

    <input
        type="date"
        name="start_date"
        value="<?= $program['start_date'] ?>"
    >

    <label>End Date</label>

    <input
        type="date"
        name="end_date"
        value="<?= $program['end_date'] ?>"
    >

    <label>Status</label>

    <select name="status">

        <option
            value="open"
            <?= $program['status'] == 'open' ? 'selected' : '' ?>
        >
            Open
        </option>

        <option
            value="closed"
            <?= $program['status'] == 'closed' ? 'selected' : '' ?>
        >
            Closed
        </option>

    </select>

    <button type="submit" name="submit">
        Update Program
    </button>

</form>

</body>
</html>