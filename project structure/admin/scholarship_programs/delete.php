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

// Delete scholarship program

$sql = "DELETE FROM scholarship_programs WHERE id = :id";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':id' => $id
]);

// Redirect back to index

header("Location: index.php");
exit;

?>