<?php

include '../../config/db.php';
include 'helpers.php';

$pdo = getDB();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id) {
    $findStmt = $pdo->prepare('SELECT application_id FROM evaluation_scores WHERE id = ?');
    $findStmt->execute([$id]);
    $row = $findStmt->fetch();
    $applicationId = $row ? intval($row['application_id']) : null;

    $deleteStmt = $pdo->prepare('DELETE FROM evaluation_scores WHERE id = ?');
    $deleteStmt->execute([$id]);

    if ($applicationId) {
        processApplicationScores($pdo, $applicationId);
    }
}

header('Location: index.php');
exit;
?>
