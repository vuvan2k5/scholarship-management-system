<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../config/db.php';

$pdo = getDB();

$error = "";

// Lấy danh sách scholarship programs

$program_sql = "SELECT * FROM scholarship_programs";

$program_stmt = $pdo->query($program_sql);

$programs = $program_stmt->fetchAll();

// Create criterion

if(isset($_POST['submit'])) {

    $program_id = $_POST['program_id'];
    $criterion_name = trim($_POST['criterion_name']);
    $weight = trim($_POST['weight']);
    $max_score = trim($_POST['max_score']);

    // Validation

    if(
        empty($program_id) ||
        empty($criterion_name) ||
        empty($weight) ||
        empty($max_score)
    ) {

        $error = "Please fill in all required fields.";

    } else {

        $sql = "
            INSERT INTO scoring_criteria
            (
                program_id,
                criterion_name,
                weight,
                max_score
            )
            VALUES
            (
                :program_id,
                :criterion_name,
                :weight,
                :max_score
            )
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([

            ':program_id' => $program_id,
            ':criterion_name' => $criterion_name,
            ':weight' => $weight,
            ':max_score' => $max_score

        ]);

        header("Location: index.php");
        exit;
    }
}

?>

<!DOCTYPE html>
<html>

<head>

    <title>Create Scoring Criterion</title>

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

<h1>Create Scoring Criterion</h1>

<p class="error">
    <?= $error ?>
</p>

<form method="POST">

    <label>Scholarship Program</label>

    <select name="program_id" required>

        <option value="">
            -- Select Program --
        </option>

        <?php foreach($programs as $program) { ?>

            <option value="<?= $program['id'] ?>">

                <?= $program['name'] ?>

            </option>

        <?php } ?>

    </select>

    <label>Criterion Name</label>

    <input
        type="text"
        name="criterion_name"
        placeholder="Example: GPA"
        required
    >

    <label>Weight (%)</label>

    <input
        type="number"
        step="0.01"
        name="weight"
        placeholder="Example: 40"
        required
    >

    <label>Max Score</label>

    <input
        type="number"
        step="0.01"
        name="max_score"
        value="100"
        required
    >

    <button type="submit" name="submit">
        Create Criterion
    </button>

</form>

</body>
</html>