<?php
// ============================================================
// includes/eligibility.php  –  Candidate Eligibility Checking
// Scope: Evaluate applications against rules → PASS / FAIL.
// Does NOT: calculate scores, rank students, award scholarships.
// ============================================================

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/db.php';

// ── Rule type → human label mapping ─────────────────────────
function eligibilityRuleLabel(string $type): string {
    $map = [
        'gpa'                  => 'GPA Requirement',
        'activities'           => 'Activity Requirement',
        'activities_count'     => 'Activity Requirement',
        'activity'             => 'Activity Requirement',
        'income'               => 'Income Requirement',
        'family_income'        => 'Income Requirement',
        'language_certificate' => 'Language Certificate',
        'language_cert'        => 'Language Certificate',
        'research'             => 'Research Experience',
        'research_count'       => 'Research Experience',
        'research_projects'    => 'Research Experience',
        'failed_subjects'      => 'Max Failed Subjects',
    ];
    return $map[strtolower($type)] ?? ucwords(str_replace('_', ' ', $type));
}

/**
 * Run automatic eligibility check for a specific application.
 *
 * Returns array: [
 *   'passed'        => bool,
 *   'reason'        => string,
 *   'rule_trace'    => array of per-rule detail objects
 * ]
 *
 * Side effects:
 *  - Saves/updates eligibility_results
 *  - Updates applications.eligible and applications.status
 *  - Sends notification to student
 */
function checkEligibility(PDO $pdo, int $applicationId, ?int $checkedByUserId = null): array {

    // 1. Fetch application
    $stmtApp = $pdo->prepare("SELECT student_id, program_id FROM applications WHERE id = ?");
    $stmtApp->execute([$applicationId]);
    $app = $stmtApp->fetch();

    if (!$app) {
        return ['passed' => false, 'reason' => 'Application not found.', 'rule_trace' => []];
    }

    $studentId = (int)$app['student_id'];
    $programId = (int)$app['program_id'];

    // 2. Fetch ACTIVE eligibility rules for the program
    $stmtRules = $pdo->prepare("
        SELECT * FROM eligibility_rules
        WHERE program_id = ?
          AND (is_active IS NULL OR is_active = 1)
        ORDER BY id ASC
    ");
    $stmtRules->execute([$programId]);
    $rules = $stmtRules->fetchAll();

    // 3. Fetch student profile
    $stmtProfile = $pdo->prepare("SELECT * FROM student_profiles WHERE student_id = ?");
    $stmtProfile->execute([$studentId]);
    $profile = $stmtProfile->fetch();

    $isPassed      = true;
    $failedReasons = [];
    $ruleTrace     = [];

    if (!$profile) {
        $isPassed        = false;
        $failedReasons[] = 'No student profile found.';
        $ruleTrace[]     = [
            'rule_id'       => null,
            'rule_type'     => 'profile',
            'label'         => 'Student Profile',
            'operator'      => 'exists',
            'expected'      => 'required',
            'actual'        => 'missing',
            'passed'        => false,
            'fail_reason'   => 'No student profile configured for this student.',
        ];
    } else {
        foreach ($rules as $rule) {
            $type      = strtolower($rule['rule_type']);
            $op        = $rule['operator'];
            $threshold = $rule['value'];
            $label     = eligibilityRuleLabel($rule['rule_type']);

            // Map rule type → profile field
            $actualValue = null;

            if ($type === 'gpa') {
                $actualValue = (float)($profile['gpa'] ?? 0);
                $threshold   = (float)$threshold;
            } elseif (in_array($type, ['activities','activities_count','activity'])) {
                $actualValue = (int)($profile['activities_count'] ?? 0);
                $threshold   = (int)$threshold;
            } elseif ($type === 'failed_subjects') {
                $actualValue = (int)($profile['failed_subjects'] ?? 0);
                $threshold   = (int)$threshold;
            } elseif (in_array($type, ['research_projects','research_count','research'])) {
                $actualValue = (int)($profile['research_count'] ?? 0);
                $threshold   = (int)$threshold;
            } elseif (in_array($type, ['language_certificate','language_cert'])) {
                $actualValue = (int)($profile['language_certificate'] ?? 0);
                $threshold   = (int)$threshold;
            } elseif (in_array($type, ['income','family_income'])) {
                $actualValue = (float)($profile['family_income'] ?? 0);
                $threshold   = (float)$threshold;
            } else {
                // Dynamic mapping to profile column
                if (array_key_exists($type, $profile)) {
                    $actualValue = $profile[$type];
                } else {
                    // Unknown rule — skip but record in trace
                    $ruleTrace[] = [
                        'rule_id'     => (int)$rule['id'],
                        'rule_type'   => $rule['rule_type'],
                        'label'       => $label,
                        'operator'    => $op,
                        'expected'    => $threshold,
                        'actual'      => 'N/A',
                        'passed'      => null,
                        'fail_reason' => 'Rule type not mapped to a known profile field — skipped.',
                    ];
                    continue;
                }
            }

            // Perform comparison
            $rulePassed = match($op) {
                '>='    => $actualValue >= $threshold,
                '<='    => $actualValue <= $threshold,
                '>'     => $actualValue >  $threshold,
                '<'     => $actualValue <  $threshold,
                '='     => $actualValue == $threshold,
                '!='    => $actualValue != $threshold,
                default => $actualValue == $threshold,
            };

            // Build human-readable fail reason
            if (!$rulePassed) {
                $failReasonText = sprintf(
                    '%s not met: student value is %s, required %s %s.',
                    $label,
                    is_float($actualValue) ? number_format($actualValue, 2) : $actualValue,
                    $op,
                    is_float($threshold) ? number_format($threshold, 2) : $threshold
                );
                // Map to friendly message
                $failReasonText = match(true) {
                    $type === 'gpa'
                        => "GPA Requirement Not Met: student GPA is {$actualValue}, required {$op} {$threshold}.",
                    in_array($type, ['language_certificate','language_cert'])
                        => "Missing Language Certificate: student has no valid language certificate on record.",
                    in_array($type, ['income','family_income'])
                        => "Income Threshold Exceeded: family income is " . number_format((float)$actualValue, 0, ',', '.') . "đ, limit is {$op} " . number_format((float)$threshold, 0, ',', '.') . "đ.",
                    $type === 'failed_subjects'
                        => "Failed Subjects Limit Exceeded: student has {$actualValue} failed subject(s), maximum allowed is {$threshold}.",
                    in_array($type, ['research_projects','research_count','research'])
                        => "Research Experience Insufficient: student has {$actualValue} research project(s), required {$op} {$threshold}.",
                    in_array($type, ['activities','activities_count','activity'])
                        => "Activity Requirement Not Met: student has {$actualValue} activity record(s), required {$op} {$threshold}.",
                    default
                        => "{$label} not met: actual value is {$actualValue}, required {$op} {$threshold}.",
                };

                $isPassed        = false;
                $failedReasons[] = $failReasonText;
            }

            $ruleTrace[] = [
                'rule_id'     => (int)$rule['id'],
                'rule_type'   => $rule['rule_type'],
                'label'       => $label,
                'operator'    => $op,
                'expected'    => $threshold,
                'actual'      => $actualValue,
                'passed'      => $rulePassed,
                'fail_reason' => $rulePassed ? null : ($failedReasons[count($failedReasons) - 1] ?? ''),
            ];
        }
    }

    $isPassed  = $isPassed ? 1 : 0;
    $reason    = $isPassed
        ? 'Meets all eligibility criteria.'
        : 'Failed criteria: ' . implode('; ', $failedReasons);
    $traceJson = json_encode($ruleTrace, JSON_UNESCAPED_UNICODE);

    // 4. Upsert eligibility_results
    $stmtCheck = $pdo->prepare("SELECT id FROM eligibility_results WHERE application_id = ?");
    $stmtCheck->execute([$applicationId]);
    $existing  = $stmtCheck->fetch();

    // Try with rule_trace column (may not exist on legacy installs)
    try {
        if ($existing) {
            $pdo->prepare("
                UPDATE eligibility_results
                SET is_passed = ?, reason = ?, rule_trace = ?, checked_by = ?,
                    checked_at = CURRENT_TIMESTAMP
                WHERE application_id = ?
            ")->execute([$isPassed, $reason, $traceJson, $checkedByUserId, $applicationId]);
        } else {
            $pdo->prepare("
                INSERT INTO eligibility_results
                    (application_id, is_passed, reason, rule_trace, checked_by, checked_at)
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ")->execute([$applicationId, $isPassed, $reason, $traceJson, $checkedByUserId]);
        }
    } catch (Exception $e) {
        // Fallback: without new columns
        if ($existing) {
            $pdo->prepare("UPDATE eligibility_results SET is_passed = ?, reason = ?, checked_at = CURRENT_TIMESTAMP WHERE application_id = ?")
                ->execute([$isPassed, $reason, $applicationId]);
        } else {
            $pdo->prepare("INSERT INTO eligibility_results (application_id, is_passed, reason, checked_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)")
                ->execute([$applicationId, $isPassed, $reason]);
        }
    }

    // 5. Update application eligible + status
    $newStatus = $isPassed ? 'eligible' : 'ineligible';
    $pdo->prepare("UPDATE applications SET eligible = ?, status = ? WHERE id = ?")
        ->execute([$isPassed, $newStatus, $applicationId]);

    // 6. Notify student
    try {
        $notifyTitle = $isPassed ? 'Eligibility Check Passed' : 'Eligibility Check Failed';
        $notifyMsg   = $isPassed
            ? 'Your application has passed the automatic eligibility check.'
            : 'Your application did not meet the eligibility criteria. Reason: ' . $reason;
        $notifyType  = $isPassed ? 'success' : 'error';
        $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)")
            ->execute([$studentId, $notifyTitle, $notifyMsg, $notifyType]);
    } catch (Exception $e) { /* notifications table may differ */ }

    return [
        'passed'     => (bool)$isPassed,
        'reason'     => $reason,
        'rule_trace' => $ruleTrace,
    ];
}

/**
 * Run engine for all applications in one or all programs.
 * Returns summary: ['total'=>int, 'passed'=>int, 'failed'=>int, 'skipped'=>int]
 */
function runEligibilityEngine(PDO $pdo, ?int $programId = null, int $executedBy = 0, string $mode = 'all'): array {
    // mode: 'all' = all apps, 'pending' = only unchecked
    $whereProgram  = $programId ? "AND a.program_id = $programId" : '';
    $wherePending  = $mode === 'pending' ? "AND a.eligible IS NULL" : '';

    $stmt = $pdo->query("
        SELECT a.id FROM applications a
        WHERE 1=1 $whereProgram $wherePending
        ORDER BY a.id ASC
    ");
    $ids  = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $total = count($ids);
    $passed = 0;
    $failed = 0;

    foreach ($ids as $appId) {
        $result = checkEligibility($pdo, (int)$appId, $executedBy);
        if ($result['passed']) $passed++; else $failed++;
    }

    // Record to engine_run_history
    try {
        $pdo->prepare("
            INSERT INTO engine_run_history
                (program_id, total_checked, total_passed, total_failed, executed_by)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$programId, $total, $passed, $failed, $executedBy]);
    } catch (Exception $e) { /* table may not exist yet */ }

    return ['total' => $total, 'passed' => $passed, 'failed' => $failed, 'skipped' => 0];
}
