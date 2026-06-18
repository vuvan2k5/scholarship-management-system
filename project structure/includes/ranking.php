<?php
// ============================================================
// includes/ranking.php  –  Ranking Engine
// ============================================================
// Business rules:
//   - Rankings come EXCLUSIVELY from approved evaluation_scores
//   - Admin controls generation and publication
//   - Admins CANNOT manually modify scores
//   - Tie-break: 1) Higher GPA  2) Earlier submission date
//   - awarded = 1 for rank <= slots; 0 otherwise
// ============================================================

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/db.php';

// ── Auto-migrate: add new columns if missing ─────────────────
function ensureRankingColumns(PDO $pdo): void {
    try {
        $pdo->exec("ALTER TABLE ranking_results
            ADD COLUMN IF NOT EXISTS awarded          TINYINT(1)   NOT NULL DEFAULT 0  AFTER recommended,
            ADD COLUMN IF NOT EXISTS tie_break_reason VARCHAR(100) DEFAULT NULL         AFTER awarded,
            ADD COLUMN IF NOT EXISTS published        TINYINT(1)   NOT NULL DEFAULT 0  AFTER tie_break_reason,
            ADD COLUMN IF NOT EXISTS published_at     DATETIME     DEFAULT NULL         AFTER published,
            ADD COLUMN IF NOT EXISTS published_by     INT(11)      DEFAULT NULL         AFTER published_at,
            ADD COLUMN IF NOT EXISTS generated_at     DATETIME     DEFAULT NULL         AFTER published_by,
            ADD COLUMN IF NOT EXISTS generated_by     INT(11)      DEFAULT NULL         AFTER generated_at
        ");
    } catch (Exception $e) {}

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ranking_run_history (
            id            INT(11)      AUTO_INCREMENT PRIMARY KEY,
            program_id    INT(11)      DEFAULT NULL,
            total_ranked  INT(11)      NOT NULL DEFAULT 0,
            awarded_count INT(11)      NOT NULL DEFAULT 0,
            slots_used    INT(11)      NOT NULL DEFAULT 0,
            generated_by  INT(11)      NOT NULL,
            generated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            notes         VARCHAR(255) DEFAULT NULL,
            FOREIGN KEY (program_id)  REFERENCES scholarship_programs(id) ON DELETE SET NULL,
            FOREIGN KEY (generated_by) REFERENCES users(id)              ON DELETE CASCADE
        )
    ");
}

/**
 * Generate / refresh ranking for ONE scholarship program.
 *
 * @param  PDO      $pdo
 * @param  int      $programId
 * @param  int|null $slotsOverride  If set, use this instead of program.slots
 * @param  int|null $generatedBy    Admin user ID
 * @param  string   $notes          Optional run notes
 * @return array Summary: ['program'=>name,'ranked'=>N,'awarded'=>N,'errors'=>[]]
 */
function generateRanking(PDO $pdo, int $programId, ?int $slotsOverride = null, ?int $generatedBy = null, string $notes = ''): array
{
    ensureRankingColumns($pdo);
    $errors = [];

    // ── 1. Validate program ───────────────────────────────────
    $prog = $pdo->prepare("SELECT id, name, slots FROM scholarship_programs WHERE id = ?");
    $prog->execute([$programId]);
    $program = $prog->fetch();

    if (!$program) {
        return ['program' => "Program #$programId", 'ranked' => 0, 'awarded' => 0,
                'errors' => ["Program #$programId not found."]];
    }

    $programName = $program['name'];
    $slots       = $slotsOverride !== null ? max(0, $slotsOverride) : (int)$program['slots'];

    // ── 2. Compute weighted scores (ONLY eligible applications) ─
    $stmt = $pdo->prepare("
        SELECT
            a.id            AS application_id,
            a.submitted_at,
            COALESCE(sp2.gpa, 0) AS gpa,
            ROUND(SUM(es.score * sc.weight / 100), 2) AS total_score
        FROM applications a
        JOIN evaluation_scores es ON es.application_id = a.id
        JOIN scoring_criteria sc
             ON sc.id = es.criteria_id
            AND sc.program_id = a.program_id
            AND (sc.is_active IS NULL OR sc.is_active = 1)
        LEFT JOIN student_profiles sp2 ON sp2.student_id = a.student_id
        WHERE a.program_id = ?
          AND a.eligible = 1
        GROUP BY a.id, a.submitted_at, sp2.gpa
    ");
    $stmt->execute([$programId]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        $errors[] = "No eligible, scored applications found for \"{$programName}\". "
                  . "Run Eligibility Engine and ensure reviewers have scored applications first.";
        return ['program' => $programName, 'ranked' => 0, 'awarded' => 0, 'errors' => $errors];
    }

    // ── 3. Sort: DESC total_score → DESC GPA → ASC submitted_at ─
    usort($rows, function ($a, $b) {
        if ($b['total_score'] != $a['total_score']) {
            return $b['total_score'] <=> $a['total_score'];  // higher score first
        }
        if ($b['gpa'] != $a['gpa']) {
            return $b['gpa'] <=> $a['gpa'];  // tie-break 1: higher GPA
        }
        // tie-break 2: earlier submission
        return strcmp($a['submitted_at'] ?? '', $b['submitted_at'] ?? '');
    });

    // ── 4. Assign ranks with tie-break reason ────────────────
    $insertRows  = [];
    $ranked      = 0;
    $awardedCount = 0;
    $prevScore   = null;
    $prevGpa     = null;

    foreach ($rows as $i => $row) {
        $rankNo    = $i + 1;
        $awarded   = ($rankNo <= $slots) ? 1 : 0;
        $tieReason = null;

        if ($prevScore !== null && (float)$row['total_score'] === $prevScore) {
            // Scores tied — detect which tiebreaker fired
            if ((float)$row['gpa'] !== $prevGpa) {
                $tieReason = 'Higher GPA';
            } else {
                $tieReason = 'Earlier Submission Date';
            }
        }

        $prevScore = (float)$row['total_score'];
        $prevGpa   = (float)$row['gpa'];

        $insertRows[] = [
            'application_id'  => (int)$row['application_id'],
            'total_score'     => (float)$row['total_score'],
            'rank'            => $rankNo,
            'recommended'     => $awarded,
            'awarded'         => $awarded,
            'tie_break_reason'=> $tieReason,
            'generated_at'    => date('Y-m-d H:i:s'),
            'generated_by'    => $generatedBy,
        ];

        $ranked++;
        if ($awarded) $awardedCount++;
    }

    // ── 5. Delete old rows for this program ───────────────────
    $pdo->prepare("
        DELETE rr FROM ranking_results rr
        JOIN applications a ON rr.application_id = a.id
        WHERE a.program_id = ?
    ")->execute([$programId]);

    // ── 6. Insert new rows ────────────────────────────────────
    $ins = $pdo->prepare("
        INSERT INTO ranking_results
            (application_id, total_score, `rank`, recommended, awarded,
             tie_break_reason, published, generated_at, generated_by)
        VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)
    ");

    foreach ($insertRows as $r) {
        $ins->execute([
            $r['application_id'], $r['total_score'], $r['rank'],
            $r['recommended'], $r['awarded'], $r['tie_break_reason'],
            $r['generated_at'], $r['generated_by'],
        ]);
    }

    // ── 7. Update scholarship program slots if overridden ─────
    if ($slotsOverride !== null && $slotsOverride !== (int)$program['slots']) {
        $pdo->prepare("UPDATE scholarship_programs SET slots = ? WHERE id = ?")
            ->execute([$slotsOverride, $programId]);
    }

    // ── 8. Log to history ─────────────────────────────────────
    try {
        $pdo->prepare("
            INSERT INTO ranking_run_history
                (program_id, total_ranked, awarded_count, slots_used, generated_by, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$programId, $ranked, $awardedCount, $slots, $generatedBy ?? 0, $notes]);
    } catch (Exception $e) {}

    return [
        'program'  => $programName,
        'ranked'   => $ranked,
        'awarded'  => $awardedCount,
        'slots'    => $slots,
        'errors'   => $errors,
    ];
}

/**
 * Generate rankings for ALL programs.
 */
function generateAllRankings(PDO $pdo, ?int $generatedBy = null, string $notes = ''): array
{
    ensureRankingColumns($pdo);
    $programs  = $pdo->query("SELECT id FROM scholarship_programs ORDER BY id ASC")->fetchAll();
    $summaries = [];

    foreach ($programs as $p) {
        $summaries[] = generateRanking($pdo, (int)$p['id'], null, $generatedBy, $notes);
    }

    // Log combined run
    try {
        $totalRanked  = array_sum(array_column($summaries, 'ranked'));
        $totalAwarded = array_sum(array_column($summaries, 'awarded'));
        $pdo->prepare("
            INSERT INTO ranking_run_history
                (program_id, total_ranked, awarded_count, slots_used, generated_by, notes)
            VALUES (NULL, ?, ?, 0, ?, ?)
        ")->execute([$totalRanked, $totalAwarded, $generatedBy ?? 0, 'All Programs — ' . $notes]);
    } catch (Exception $e) {}

    return $summaries;
}

/**
 * Publish results for a program — notify students, generate certificates.
 *
 * @param  PDO $pdo
 * @param  int $programId
 * @param  int $publishedBy  Admin user ID
 * @return array ['notified'=>N, 'certificates'=>N, 'errors'=>[]]
 */
function publishRanking(PDO $pdo, int $programId, int $publishedBy): array
{
    ensureRankingColumns($pdo);
    $errors       = [];
    $notified     = 0;
    $certificates = 0;

    // ── Mark as published ─────────────────────────────────────
    $pdo->prepare("
        UPDATE ranking_results rr
        JOIN applications a ON rr.application_id = a.id
        SET rr.published    = 1,
            rr.published_at = NOW(),
            rr.published_by = ?
        WHERE a.program_id = ?
    ")->execute([$publishedBy, $programId]);

    // ── Update application status ─────────────────────────────
    // Awarded apps → 'approved', others → 'rejected'
    $pdo->prepare("
        UPDATE applications a
        JOIN ranking_results rr ON rr.application_id = a.id
        SET a.status = IF(rr.awarded = 1, 'approved', 'rejected')
        WHERE a.program_id = ?
    ")->execute([$programId]);

    // ── Fetch awarded applications ────────────────────────────
    $stmt = $pdo->prepare("
        SELECT
            rr.id         AS rr_id,
            rr.application_id,
            rr.rank       AS rank_no,
            rr.total_score,
            a.student_id,
            a.program_id,
            sp.name       AS program_name,
            sp.budget,
            u.full_name   AS student_name
        FROM ranking_results rr
        JOIN applications a       ON rr.application_id = a.id
        JOIN scholarship_programs sp ON a.program_id = sp.id
        JOIN users u              ON a.student_id = u.id
        WHERE a.program_id = ?
        ORDER BY rr.rank ASC
    ");
    $stmt->execute([$programId]);
    $allRankings = $stmt->fetchAll();

    foreach ($allRankings as $row) {
        $isAwarded = ($row['rank_no'] <= 0 || false); // Re-check via awarded col in DB
    }

    // ── Reload with awarded flag ──────────────────────────────
    $aStmt = $pdo->prepare("
        SELECT rr.awarded, rr.application_id, rr.total_score, rr.rank AS rank_no,
               a.student_id, sp.name AS program_name, sp.budget, sp.id AS prog_id,
               u.full_name AS student_name
        FROM ranking_results rr
        JOIN applications a       ON rr.application_id = a.id
        JOIN scholarship_programs sp ON a.program_id = sp.id
        JOIN users u              ON a.student_id = u.id
        WHERE a.program_id = ?
        ORDER BY rr.rank ASC
    ");
    $aStmt->execute([$programId]);
    $allRankings = $aStmt->fetchAll();

    foreach ($allRankings as $row) {
        // ── Notify student ────────────────────────────────────
        if ($row['awarded']) {
            $title = "🎉 Scholarship Awarded — {$row['program_name']}";
            $msg   = "Congratulations! Your application has been approved for the {$row['program_name']} scholarship. "
                   . "Rank: #{$row['rank_no']} · Score: {$row['total_score']}.";
            $type  = 'success';
        } else {
            $title = "Scholarship Result — {$row['program_name']}";
            $msg   = "Thank you for applying to {$row['program_name']}. "
                   . "Unfortunately, your application was not selected this round. "
                   . "Rank: #{$row['rank_no']} · Score: {$row['total_score']}.";
            $type  = 'info';
        }

        try {
            $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read)
                VALUES (?, ?, ?, ?, 0)
            ")->execute([$row['student_id'], $title, $msg, $type]);
            $notified++;
        } catch (Exception $e) {}

        // ── Generate certificate for awarded ──────────────────
        if ($row['awarded']) {
            $certCode = 'CERT-'
                . str_pad($row['prog_id'],        3, '0', STR_PAD_LEFT) . '-'
                . str_pad($row['application_id'], 5, '0', STR_PAD_LEFT) . '-'
                . date('YmdHis');

            try {
                // Only insert if not already exists
                $existsCheck = $pdo->prepare("SELECT id FROM award_certificates WHERE application_id = ?");
                $existsCheck->execute([$row['application_id']]);
                if (!$existsCheck->fetch()) {
                    $pdo->prepare("
                        INSERT INTO award_certificates
                            (application_id, certificate_code, issued_at, issued_by)
                        VALUES (?, ?, NOW(), ?)
                    ")->execute([$row['application_id'], $certCode, $publishedBy]);
                    $certificates++;
                }
            } catch (Exception $e) {}

            // Create disbursement if not already exists
            try {
                $disbCheck = $pdo->prepare("SELECT id FROM disbursements WHERE application_id = ?");
                $disbCheck->execute([$row['application_id']]);
                if (!$disbCheck->fetch()) {
                    $pdo->prepare("
                        INSERT INTO disbursements (application_id, amount, status, note)
                        VALUES (?, ?, 'approved', ?)
                    ")->execute([
                        $row['application_id'],
                        $row['budget'],
                        "Scholarship approved. Program: {$row['program_name']}. Rank: #{$row['rank_no']}.",
                    ]);
                }
            } catch (Exception $e) {}
        }
    }

    return ['notified' => $notified, 'certificates' => $certificates, 'errors' => $errors];
}
