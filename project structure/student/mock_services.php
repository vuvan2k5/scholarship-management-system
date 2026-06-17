<?php
// ============================================================
// mock_services.php  –  Eligibility / Scoring / Ranking
//
// ELIGIBILITY CRITERIA (updated):
//   Prerequisite  : failed_subjects == 0  (No F grades)
//   Mandatory cond: gpa >= 3.2
//   Mandatory cond: count(activities) >= 2  (At least 2 extra-curricular activities)
//   New condition : language_certificate == true  (Has language certificate)
//
// SCORING (Board - 4 criteria, total 100 points):
//   Academic (GPA)       : 50 points – GPA/4.0 * 50
//   Language Certificate : 15 points – Yes: +15 / No: 0
//   Extra-curricular Act : 20 points – 4 points/activity, max 20
//   Research Topics      : 15 points – 7.5 points/topic, max 15
//
// REMOVED: family_income, is_disadvantaged
// ============================================================

// ── Score weight constants ─────────────────────────────────────
const SCORE_GPA_MAX       = 50.0;   // GPA accounts for 50 points
const SCORE_LANG_MAX      = 15.0;   // Language certificate: 15 points
const SCORE_ACTIVITY_UNIT =  4.0;   // 4 points / activity
const SCORE_ACTIVITY_MAX  = 20.0;   // Max 20 points
const SCORE_RESEARCH_UNIT =  7.5;   // 7.5 points / research topic
const SCORE_RESEARCH_MAX  = 15.0;   // Max 15 points

/**
 * Check eligibility for scholarship.
 *
 * Rules (in order of priority):
 *   1. No F grades (prerequisite – immediate disqualification)
 *   2. GPA >= 3.2
 *   3. Number of extra-curricular activities >= 2
 *   4. Has language certificate (new condition)
 *
 * @param  array $student  Student data (from mock_data.php)
 * @return array { passed: bool, reasons: string[], warnings: string[] }
 */
function checkEligibilityMock(array $student): array
{
    $passed   = true;
    $reasons  = [];   // Reasons for failing (hard criteria)
    $warnings = [];   // Mild warnings (not failed, but close to threshold)

    // ── 1. Prerequisite: No F grades ───────────────────
    if (($student['failed_subjects'] ?? 0) > 0) {
        $passed   = false;
        $reasons[] = 'Has ' . $student['failed_subjects'] . ' F-graded subject(s) – does not meet prerequisite.';
    }

    // ── 2. GPA >= 3.2 ──────────────────────────────────────────
    $gpa = (float)($student['gpa'] ?? 0);
    if ($gpa < 3.2) {
        $passed   = false;
        $reasons[] = 'GPA ' . $gpa . ' < 3.2 – does not meet minimum academic threshold.';
    } elseif ($gpa < 3.4) {
        $warnings[] = 'GPA ' . $gpa . ' is at the minimum level (3.2–3.4), ranking score may be low.';
    }

    // ── 3. Extra-curricular activities >= 2 ──────────────────────────
    $actCount = isset($student['activities']) ? count($student['activities']) : 0;
    if ($actCount < 2) {
        $passed   = false;
        $reasons[] = 'Only has ' . $actCount . ' extra-curricular activity/activities – requires at least 2.';
    }

    // ── 4. Language certificate ────────────────────────────────
    if (empty($student['language_certificate'])) {
        $passed   = false;
        $reasons[] = 'No language certificate – this is a mandatory requirement.';
    }

    return [
        'passed'   => $passed,
        'reasons'  => $reasons,
        'warnings' => $warnings,
    ];
}

/**
 * Calculate comprehensive score for a student (scale of 100).
 *
 * Formula:
 *   Academic Score       = (GPA / 4.0) × 50           → max 50
 *   Language Cert Score  = Has cert ? 15 : 0          → max 15
 *   Extra-curricular Act = min(count × 4, 20)         → max 20
 *   Research Topic Score = min(count × 7.5, 15)       → max 15
 *   ─────────────────────────────────────────────────────────
 *   Total Score                                       = 100
 *
 * @param  array $student
 * @return array { total: float, breakdown: array }
 */
function scoreStudentMock(array $student): array
{
    $gpa = min(max((float)($student['gpa'] ?? 0), 0.0), 4.0);

    $gpaScore  = round($gpa / 4.0 * SCORE_GPA_MAX, 2);
    $langScore = !empty($student['language_certificate']) ? SCORE_LANG_MAX : 0.0;

    $actCount     = isset($student['activities']) ? count($student['activities']) : 0;
    $actScore     = min($actCount * SCORE_ACTIVITY_UNIT, SCORE_ACTIVITY_MAX);

    $resCount     = isset($student['research_topics']) ? count($student['research_topics']) : 0;
    $resScore     = min($resCount * SCORE_RESEARCH_UNIT, SCORE_RESEARCH_MAX);

    $total = round($gpaScore + $langScore + $actScore + $resScore, 2);

    return [
        'total'     => $total,
        'breakdown' => [
            'gpa'      => ['score' => $gpaScore,  'max' => SCORE_GPA_MAX,      'label' => 'Academic (GPA)'],
            'language' => ['score' => $langScore, 'max' => SCORE_LANG_MAX,     'label' => 'Language Certificate'],
            'activity' => ['score' => $actScore,  'max' => SCORE_ACTIVITY_MAX, 'label' => 'Extra-curricular Activities'],
            'research' => ['score' => $resScore,  'max' => SCORE_RESEARCH_MAX, 'label' => 'Research Topics'],
        ],
    ];
}

/**
 * Rank and select proposed list of scholarship awardees.
 *
 * @param  array $students   Student array (from mock_data.php)
 * @param  int   $slots      Number of scholarship slots
 * @return array {
 *   ranked   : all ranked students (including ineligible ones),
 *   eligible : only eligible students (ranked),
 *   awardees : top $slots eligible students proposed,
 *   stats    : consolidated statistics
 * }
 */
function rankAndSelect(array $students, int $slots = 3): array
{
    $ranked = [];

    foreach ($students as $s) {
        $elig   = checkEligibilityMock($s);
        $result = scoreStudentMock($s);
        $ranked[] = array_merge($s, [
            'eligibility' => $elig,
            'score'       => $result['total'],
            'breakdown'   => $result['breakdown'],
        ]);
    }

    // Sort: eligible on top, then by descending score
    usort($ranked, function ($a, $b) {
        $aPass = $a['eligibility']['passed'] ? 1 : 0;
        $bPass = $b['eligibility']['passed'] ? 1 : 0;
        if ($aPass !== $bPass) return $bPass - $aPass;           // eligible first
        if ($a['score'] === $b['score']) return 0;
        return ($a['score'] > $b['score']) ? -1 : 1;            // higher score first
    });

    // Add ranking number
    $rank = 0;
    foreach ($ranked as &$r) {
        if ($r['eligibility']['passed']) {
            $r['rank'] = ++$rank;
        } else {
            $r['rank'] = null;  // no rank if ineligible
        }
    }
    unset($r);

    $eligible = array_values(array_filter($ranked, fn($r) => $r['eligibility']['passed']));
    $awardees = array_slice($eligible, 0, $slots);

    $stats = [
        'total'      => count($students),
        'eligible'   => count($eligible),
        'ineligible' => count($students) - count($eligible),
        'awardees'   => count($awardees),
        'avg_score'  => count($eligible)
            ? round(array_sum(array_column($eligible, 'score')) / count($eligible), 2)
            : 0,
    ];

    return [
        'ranked'   => $ranked,
        'eligible' => $eligible,
        'awardees' => $awardees,
        'stats'    => $stats,
    ];
}
