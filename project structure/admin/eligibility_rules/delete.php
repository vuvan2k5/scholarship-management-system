<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../config/db.php';

$pdo = getDB();

// Check if ID exists

if(!isset($_GET['id'])) {

    die("Invalid request.");

}

$id = $_GET['id'];

// Delete eligibility rule

$sql = "
    DELETE FROM eligibility_rules
    WHERE id = :id
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':id' => $id
]);

// Redirect back

header("Location: index.php");
exit;

?>