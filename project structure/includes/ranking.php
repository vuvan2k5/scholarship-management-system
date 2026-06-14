<?php
// ============================================================
// includes/ranking.php  –  Ranking Engine Service
// ============================================================
// Provides:
//   generateRanking(PDO $pdo, int $programId): array
//   generateAllRankings(PDO $pdo): array
//
// Steps for each program:
//   1. Sum weighted evaluation scores per application
//   2. Sort DESC by total_score
//   3. Assign rank (1, 2, 3 …) using ROW_NUMBER
//   4. Mark recommended = 1 for rank <= slots, else 0
//   5. DELETE old results for that program, INSERT new ones
//   6. Return summary statistics
// ============================================================

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/db.php';

/**
 * Generate / refresh ranking for ONE specific scholarship program.
 *
 * @param  PDO $pdo
 * @param  int $programId
 * @return array  Summary: ['program' => name, 'ranked' => N, 'recommended' => N, 'errors' => []]
 */
function generateRanking(PDO $pdo, int $programId): array
{
    $errors = [];

    // ── 1. Validate program exists ────────────────────────────
    $stmtProg = $pdo->prepare("SELECT id, name, slots FROM scholarship_programs WHERE id = ?");
    $stmtProg->execute([$programId]);
    $program = $stmtProg->fetch();

    if (!$program) {
        return [
            'program'     => "Program #$programId",
            'ranked'      => 0,
            'recommended' => 0,
            'errors'      => ["Program #$programId not found."],
        ];
    }

    $programName = $program['name'];
    $slots       = (int)$program['slots'];

    // ── 2. Compute weighted total_score per application ───────
    //    total_score = SUM(evaluation_score.score * scoring_criteria.weight / 100)
    //    Grouped by application, only for eligible applications.
    $stmtScores = $pdo->prepare("
        SELECT
            a.id   AS application_id,
            ROUND(
                SUM(es.score * sc.weight / 100),
                2
            )      AS total_score
        FROM applications a
        JOIN evaluation_scores es
            ON es.application_id = a.id
        JOIN scoring_criteria sc
            ON sc.id           = es.criteria_id
           AND sc.program_id   = a.program_id
        WHERE a.program_id = ?
          AND (a.eligible = 1 OR a.eligible IS NULL)
        GROUP BY a.id
        ORDER BY total_score DESC, a.id ASC
    ");
    $stmtScores->execute([$programId]);
    $scoredRows = $stmtScores->fetchAll();

    if (empty($scoredRows)) {
        $errors[] = "No evaluated applications found for \"{$programName}\". Run evaluation scoring first.";
        return [
            'program'     => $programName,
            'ranked'      => 0,
            'recommended' => 0,
            'errors'      => $errors,
        ];
    }

    // ── 3. Assign rank & recommended flag in PHP ──────────────
    $ranked      = 0;
    $recommended = 0;
    $insertRows  = [];

    foreach ($scoredRows as $i => $row) {
        $rankNo      = $i + 1;                   // 1-indexed
        $isRecommended = ($rankNo <= $slots) ? 1 : 0;

        $insertRows[] = [
            'application_id' => (int)$row['application_id'],
            'total_score'    => (float)$row['total_score'],
            'rank'           => $rankNo,
            'recommended'    => $isRecommended,
        ];

        $ranked++;
        if ($isRecommended) $recommended++;
    }

    // ── 4. Delete OLD ranking rows for this program ───────────
    //    (Only delete rows whose application belongs to this program,
    //     using a JOIN to avoid touching other programs.)
    $pdo->prepare("
        DELETE rr FROM ranking_results rr
        INNER JOIN applications a ON rr.application_id = a.id
        WHERE a.program_id = ?
    ")->execute([$programId]);

    // ── 5. Insert new ranking rows ────────────────────────────
    $stmtInsert = $pdo->prepare("
        INSERT INTO ranking_results (application_id, total_score, `rank`, recommended)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($insertRows as $r) {
        $stmtInsert->execute([
            $r['application_id'],
            $r['total_score'],
            $r['rank'],
            $r['recommended'],
        ]);
    }

    return [
        'program'     => $programName,
        'ranked'      => $ranked,
        'recommended' => $recommended,
        'errors'      => $errors,
    ];
}

/**
 * Generate / refresh rankings for ALL scholarship programs.
 *
 * @param  PDO $pdo
 * @return array[]  One summary per program.
 */
function generateAllRankings(PDO $pdo): array
{
    $programs = $pdo->query("SELECT id FROM scholarship_programs ORDER BY id ASC")->fetchAll();
    $summaries = [];

    foreach ($programs as $prog) {
        $summaries[] = generateRanking($pdo, (int)$prog['id']);
    }

    return $summaries;
}
