<?php

include '../config/db.php';

$pdo = getDB();

$totalApplications = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM student_profiles")->fetchColumn();
$totalScores = $pdo->query("SELECT COUNT(*) FROM evaluation_scores")->fetchColumn();
$totalNotifications = $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
?>

<h2>Member 2 Dashboard</h2>

<div style="display:flex; gap:20px;">

<div>
<h3><?= htmlspecialchars($totalApplications) ?></h3>
<p>Applications</p>
</div>

<div>
<h3><?= htmlspecialchars($totalStudents) ?></h3>
<p>Students</p>
</div>

<div>
<h3><?= htmlspecialchars($totalScores) ?></h3>
<p>Scores</p>
</div>

<div>
<h3><?= htmlspecialchars($totalNotifications) ?></h3>
<p>Notifications</p>
</div>

</div>