<?php
// Mock services: eligibility check, scoring and ranking using mock_data.php


require_once __DIR__ . '/mock_data.php';


/**
* Eligibility rules per requirement:
* - GPA >= 3.2
* - No failed subjects (failed_subjects == 0)
* - Activities count >= 2
*/
function checkEligibilityMock(array $student): array {
   $passed = true;
   $reasons = [];


   if (($student['gpa'] ?? 0) < 3.2) {
       $passed = false;
       $reasons[] = 'GPA below 3.2';
   }
   if (($student['failed_subjects'] ?? 0) > 0) {
       $passed = false;
       $reasons[] = 'Has failing grade(s)';
   }
   if (!isset($student['activities']) || count($student['activities']) < 2) {
       $passed = false;
       $reasons[] = 'Fewer than 2 extracurricular activities';
   }


   return ['passed' => $passed, 'reasons' => $reasons];
}


/**
* Scoring model (simple, configurable):
* - GPA: normalized to 0-60 points (GPA 0-4 mapped to 0-60)
* - Language certificate: +10 points if true
* - Activities: up to 20 points (5 points per activity, capped at 20)
* - Research topics: +10 points if any research topics present (could be scaled per-topic)
*/
function scoreStudentMock(array $student): float {
   $gpa = (float)($student['gpa'] ?? 0);
   $gpaScore = min(max($gpa, 0), 4.0) / 4.0 * 60.0; // 0-60


   $lang = !empty($student['language_certificate']) ? 10.0 : 0.0;


   $activitiesCount = isset($student['activities']) ? count($student['activities']) : 0;
   $activitiesScore = min($activitiesCount * 5.0, 20.0);


   $researchScore = (!empty($student['research_topics'])) ? 10.0 : 0.0;


   $total = $gpaScore + $lang + $activitiesScore + $researchScore;
   return round($total,2);
}


/**
* Rank and select awardees
* - $students: array of student arrays
* - $slots: number of award slots
* Returns array with keys: ranked (students with score & eligibility), awardees (top N)
*/
function rankAndSelect(array $students, int $slots = 3): array {
   $ranked = [];
   foreach ($students as $s) {
       $elig = checkEligibilityMock($s);
       $score = scoreStudentMock($s);
       $ranked[] = array_merge($s, ['eligibility' => $elig, 'score' => $score]);
   }


   usort($ranked, function($a,$b){
       if ($a['score'] === $b['score']) return 0;
       return ($a['score'] > $b['score']) ? -1 : 1;
   });


   $awardees = array_slice(array_filter($ranked, function($r){return $r['eligibility']['passed'];}), 0, $slots);


   return ['ranked' => $ranked, 'awardees' => $awardees];
}




