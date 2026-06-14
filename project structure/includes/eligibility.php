<?php
// ============================================================
// includes/eligibility.php  –  Candidate Eligibility Checking
// ============================================================

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/db.php';

/**
 * Run automatic eligibility check for a specific application.
 * Returns true if the student meets all rules, false otherwise.
 * Saves/updates result in eligibility_results table and updates the application's eligibility.
 */
function checkEligibility(PDO $pdo, int $applicationId): bool {
    // 1. Fetch application details (including program_id, student_id)
    $stmtApp = $pdo->prepare("SELECT student_id, program_id FROM applications WHERE id = ?");
    $stmtApp->execute([$applicationId]);
    $app = $stmtApp->fetch();

    if (!$app) {
        return false; // Application not found
    }

    $studentId = (int)$app['student_id'];
    $programId = (int)$app['program_id'];

    // 2. Fetch eligibility rules for the program
    $stmtRules = $pdo->prepare("SELECT * FROM eligibility_rules WHERE program_id = ?");
    $stmtRules->execute([$programId]);
    $rules = $stmtRules->fetchAll();

    // 3. Fetch student profile
    $stmtProfile = $pdo->prepare("SELECT * FROM student_profiles WHERE student_id = ?");
    $stmtProfile->execute([$studentId]);
    $profile = $stmtProfile->fetch();

    $isPassed = 1;
    $failedReasons = [];

    if (!$profile) {
        $isPassed = 0;
        $failedReasons[] = "No student profile configured.";
    } else {
        // Evaluate rules
        foreach ($rules as $rule) {
            $type = strtolower($rule['rule_type']);
            $op = $rule['operator'];
            $threshold = $rule['value'];

            // Map rule type to student profile columns
            $actualValue = null;
            $fieldName = '';

            if ($type === 'gpa') {
                $actualValue = (float)$profile['gpa'];
                $threshold = (float)$threshold;
                $fieldName = 'GPA';
            } elseif ($type === 'activities' || $type === 'activities_count') {
                $actualValue = (int)$profile['activities_count'];
                $threshold = (int)$threshold;
                $fieldName = 'Extracurricular Activities';
            } elseif ($type === 'failed_subjects') {
                $actualValue = (int)$profile['failed_subjects'];
                $threshold = (int)$threshold;
                $fieldName = 'Failed Subjects';
            } elseif ($type === 'research_projects' || $type === 'research_count') {
                $actualValue = (int)$profile['research_count'];
                $threshold = (int)$threshold;
                $fieldName = 'Research Projects';
            } elseif ($type === 'financial_status' || $type === 'family_income' || $type === 'is_disadvantaged') {
                if ($threshold === 'difficult') {
                    // Check if disadvantaged
                    $actualValue = (int)$profile['is_disadvantaged'];
                    $threshold = 1;
                    $fieldName = 'Disadvantaged Status';
                } else {
                    $actualValue = (float)$profile['family_income'];
                    $threshold = (float)$threshold;
                    $fieldName = 'Family Income';
                }
            } else {
                // If it maps to any other column in student_profiles dynamically
                if (array_key_exists($type, $profile)) {
                    $actualValue = $profile[$type];
                    $fieldName = htmlspecialchars($rule['rule_type']);
                } else {
                    // Unknown rule type, skip
                    continue;
                }
            }

            // Perform comparison
            $rulePassed = false;
            if ($op === '>=') {
                $rulePassed = ($actualValue >= $threshold);
            } elseif ($op === '<=') {
                $rulePassed = ($actualValue <= $threshold);
            } elseif ($op === '=') {
                $rulePassed = ($actualValue == $threshold);
            } elseif ($op === '>') {
                $rulePassed = ($actualValue > $threshold);
            } elseif ($op === '<') {
                $rulePassed = ($actualValue < $threshold);
            } else {
                $rulePassed = ($actualValue == $threshold);
            }

            if (!$rulePassed) {
                $isPassed = 0;
                $failedReasons[] = "$fieldName (actual: $actualValue) does not satisfy rule: $op $threshold";
            }
        }
    }

    $reason = $isPassed ? "Meets all eligibility criteria." : "Failed criteria: " . implode("; ", $failedReasons);

    // 4. Save/update eligibility_results
    $stmtCheck = $pdo->prepare("SELECT id FROM eligibility_results WHERE application_id = ?");
    $stmtCheck->execute([$applicationId]);
    $existingResult = $stmtCheck->fetch();

    if ($existingResult) {
        $stmtUpdate = $pdo->prepare("UPDATE eligibility_results SET is_passed = ?, reason = ?, checked_at = CURRENT_TIMESTAMP WHERE application_id = ?");
        $stmtUpdate->execute([$isPassed, $reason, $applicationId]);
    } else {
        $stmtInsert = $pdo->prepare("INSERT INTO eligibility_results (application_id, is_passed, reason, checked_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
        $stmtInsert->execute([$applicationId, $isPassed, $reason]);
    }

    // 5. Update eligible status on applications table
    $status = $isPassed ? 'eligible' : 'ineligible';
    $stmtUpdateApp = $pdo->prepare("UPDATE applications SET eligible = ?, status = ? WHERE id = ?");
    $stmtUpdateApp->execute([$isPassed, $status, $applicationId]);

    // Send notification to student
    $notify = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $notifyTitle = $isPassed ? 'Eligibility Check Passed' : 'Eligibility Check Failed';
    $notifyMsg = $isPassed ? 'Your application has passed the automatic eligibility check.' : 'Your application did not meet the eligibility criteria. Reason: ' . $reason;
    $notifyType = $isPassed ? 'success' : 'error';
    $notify->execute([$studentId, $notifyTitle, $notifyMsg, $notifyType]);

    return (bool)$isPassed;
}
