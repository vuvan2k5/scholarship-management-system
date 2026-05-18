<?php

include '../../config/db.php';

$pdo = getDB();

if (!isset($_GET['id'])) {
    die("ID not found.");
}

$id = $_GET['id'];

$sql = "DELETE FROM eligibility_results WHERE id = ?";

$stmt = $pdo->prepare($sql);

$stmt->execute([$id]);

header("Location: index.php");

exit;
?>