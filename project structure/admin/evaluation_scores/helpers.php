<?php

function processApplicationScores(PDO $pdo, int $applicationId): void {
    $statsSql = "SELECT COUNT(DISTINCT es.criteria_id) AS scored_count, SUM(es.score) AS total_score
                 FROM evaluation_scores es
                 WHERE es.application_id = ?";
    $stmt = $pdo->prepare($statsSql);
    $stmt->execute([$applicationId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $criteriaTotal = (int) $pdo->query('SELECT COUNT(*) FROM scoring_criteria')->fetchColumn();
    $maxScoreTotal = (float) $pdo->query('SELECT COALESCE(SUM(max_score), 0) FROM scoring_criteria')->fetchColumn();

    if (!$stats || $stats['scored_count'] == 0) {
        $updateApp = $pdo->prepare('UPDATE applications SET status = ?, eligible = ?, updated_at = NOW() WHERE id = ?');
        $updateApp->execute(['submitted', null, $applicationId]);
        return;
    }
    $applicationStmt = $pdo->prepare('SELECT student_id FROM applications WHERE id = ?');
    $applicationStmt->execute([$applicationId]);
    $application = $applicationStmt->fetch(PDO::FETCH_ASSOC);
    if (!$application) {
        return;
    }

    $status = 'submitted';
    $eligible = null;
    $notification = null;

    if ($criteriaTotal > 0 && $stats['scored_count'] >= $criteriaTotal) {
        $threshold = 0.6;
        if ((float) $stats['total_score'] >= $maxScoreTotal * $threshold) {
            $status = 'eligible';
            $eligible = 1;
            $notification = [
                'type' => 'success',
                'title' => 'PASS: Scholarship Result',
                'message' => sprintf('Application #%d passes with %.2f / %.2f total points.',
                    $applicationId,
                    (float) $stats['total_score'],
                    $maxScoreTotal
                ),
            ];
        } else {
            $status = 'ineligible';
            $eligible = 0;
            $notification = [
                'type' => 'warning',
                'title' => 'FAIL: Scholarship Result',
                'message' => sprintf('Application #%d did not pass with %.2f / %.2f total points.',
                    $applicationId,
                    (float) $stats['total_score'],
                    $maxScoreTotal
                ),
            ];
        }
    }

    $updateApp = $pdo->prepare('UPDATE applications SET status = ?, eligible = ?, updated_at = NOW() WHERE id = ?');
    $updateApp->execute([$status, $eligible, $applicationId]);

    if ($notification) {
        $insertNotification = $pdo->prepare(
            'INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, ?, 0)'
        );
        $insertNotification->execute([
            $application['student_id'],
            $notification['title'],
            $notification['message'],
            $notification['type'],
        ]);
    }
}
