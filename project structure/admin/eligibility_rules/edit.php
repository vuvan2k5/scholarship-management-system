<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../config/db.php';

$pdo = getDB();

$id = $_GET['id'];

$error = "";

// Lấy rule hiện tại

$sql = "
    SELECT * FROM eligibility_rules
    WHERE id = :id
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':id' => $id
]);

$rule = $stmt->fetch();

// Lấy danh sách scholarship programs

$program_sql = "SELECT * FROM scholarship_programs";

$program_stmt = $pdo->query($program_sql);

$programs = $program_stmt->fetchAll();

// Update rule

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

        $update = "
            UPDATE eligibility_rules SET

                program_id = :program_id,
                rule_type = :rule_type,
                operator = :operator,
                value = :value

            WHERE id = :id
        ";

        $stmt = $pdo->prepare($update);

        $stmt->execute([

            ':program_id' => $program_id,
            ':rule_type' => $rule_type,
            ':operator' => $operator,
            ':value' => $value,
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

    <title>Edit Eligibility Rule</title>

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

<h1>Edit Eligibility Rule</h1>

<p class="error">
    <?= $error ?>
</p>

<form method="POST">

    <label>Scholarship Program</label>

    <select name="program_id" required>

        <?php foreach($programs as $program) { ?>

            <option
                value="<?= $program['id'] ?>"
                <?= $rule['program_id'] == $program['id'] ? 'selected' : '' ?>
            >

                <?= $program['name'] ?>

            </option>

        <?php } ?>

    </select>

    <label>Rule Type</label>

    <input
        type="text"
        name="rule_type"
        value="<?= $rule['rule_type'] ?>"
        required
    >

    <label>Operator</label>

    <select name="operator" required>

        <option
            value=">="
            <?= $rule['operator'] == '>=' ? 'selected' : '' ?>
        >
            >=
        </option>

        <option
            value="<="
            <?= $rule['operator'] == '<=' ? 'selected' : '' ?>
        >
            <=
        </option>

        <option
            value="="
            <?= $rule['operator'] == '=' ? 'selected' : '' ?>
        >
            =
        </option>

        <option
            value=">"
            <?= $rule['operator'] == '>' ? 'selected' : '' ?>
        >
            >
        </option>

        <option
            value="<"
            <?= $rule['operator'] == '<' ? 'selected' : '' ?>
        >
            <
        </option>

    </select>

    <label>Value</label>

    <input
        type="text"
        name="value"
        value="<?= $rule['value'] ?>"
        required
    >

    <button type="submit" name="submit">
        Update Rule
    </button>

</form>

</body>
</html>