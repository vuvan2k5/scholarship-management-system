<?php

function processApplicationScores(PDO $pdo, int $applicationId): void {
    $statsSql = "SELECT COUNT(*) AS scored_count, SUM(es.score) AS total_score, SUM(sc.max_score) AS max_score
                 FROM evaluation_scores es
                 JOIN scoring_criteria sc ON es.criteria_id = sc.id
                 WHERE es.application_id = ?";
    $stmt = $pdo->prepare($statsSql);
    $stmt->execute([$applicationId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $criteriaTotal = (int) $pdo->query('SELECT COUNT(*) FROM scoring_criteria')->fetchColumn();

    if (!$stats || $stats['scored_count'] == 0) {
        $updateApp = $pdo->prepare('UPDATE applications SET status = ?, eligible = ?, updated_at = NOW() WHERE id = ?');
        $updateApp->execute(['reviewing', null, $applicationId]);
        return;
    }
    $applicationStmt = $pdo->prepare('SELECT student_id FROM applications WHERE id = ?');
    $applicationStmt->execute([$applicationId]);
    $application = $applicationStmt->fetch(PDO::FETCH_ASSOC);
    if (!$application) {
        return;
    }

    $status = 'reviewing';
    $eligible = null;
    $notificationType = 'info';
    $title = 'Review in progress';
    $message = sprintf('Application #%d has received a new score. Current total: %.2f / %.2f.',
        $applicationId,
        (float) $stats['total_score'],
        (float) $stats['max_score']
    );

    if ($criteriaTotal > 0 && $stats['scored_count'] >= $criteriaTotal) {
        $threshold = 0.6;
        if ((float) $stats['total_score'] >= (float) $stats['max_score'] * $threshold) {
            $status = 'eligible';
            $eligible = 1;
            $notificationType = 'success';
            $title = 'PASS: Scholarship Result';
            $message = sprintf('Application #%d passes with %.2f / %.2f total points.',
                $applicationId,
                (float) $stats['total_score'],
                (float) $stats['max_score']
            );
        } else {
            $status = 'ineligible';
            $eligible = 0;
            $notificationType = 'warning';
            $title = 'FAIL: Scholarship Result';
            $message = sprintf('Application #%d did not pass with %.2f / %.2f total points.',
                $applicationId,
                (float) $stats['total_score'],
                (float) $stats['max_score']
            );
        }
    }

    $updateApp = $pdo->prepare('UPDATE applications SET status = ?, eligible = ?, updated_at = NOW() WHERE id = ?');
    $updateApp->execute([$status, $eligible, $applicationId]);

    $insertNotification = $pdo->prepare(
        'INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, ?, 0)'
    );
    $insertNotification->execute([
        $application['student_id'],
        $title,
        $message,
        $notificationType,
    ]);
}
