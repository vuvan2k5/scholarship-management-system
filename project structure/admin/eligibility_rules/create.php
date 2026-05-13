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

if(isset($_POST['submit'])) {

    $program_id = $_POST['program_id'];
    $rule_type = trim($_POST['rule_type']);
    $operator = trim($_POST['operator']);
    $value = trim($_POST['value']);

    // Validation

    if(
        empty($program_id) ||
        empty($rule_type) ||
        empty($operator) ||
        empty($value)
    ) {

        $error = "Please fill in all required fields.";

    } else {

        $sql = "
            INSERT INTO eligibility_rules
            (
                program_id,
                rule_type,
                operator,
                value
            )
            VALUES
            (
                :program_id,
                :rule_type,
                :operator,
                :value
            )
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([

            ':program_id' => $program_id,
            ':rule_type' => $rule_type,
            ':operator' => $operator,
            ':value' => $value

        ]);

        header("Location: index.php");
        exit;
    }
}

?>

<!DOCTYPE html>
<html>

<head>

    <title>Create Eligibility Rule</title>

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

<h1>Create Eligibility Rule</h1>

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

    <label>Rule Type</label>

    <input
        type="text"
        name="rule_type"
        placeholder="Example: GPA"
        required
    >

    <label>Operator</label>

    <select name="operator" required>

        <option value=">=">>=</option>

        <option value="<="><=</option>

        <option value="=">=</option>

        <option value=">">></option>

        <option value="<"><</option>

    </select>

    <label>Value</label>

    <input
        type="text"
        name="value"
        placeholder="Example: 3.5"
        required
    >

    <button type="submit" name="submit">
        Create Rule
    </button>

</form>

</body>
</html>