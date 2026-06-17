<?php
// ============================================================
// mock_data.php  –  10 mock students (Front-end / Demo)
//
// Evidence columns according to new criteria:
//   gpa               – Academic score (float, 0–4)
//   failed_subjects   – Number of re-exams / F grades (int)  ← prerequisite
//   language_certificate – Language certificate (bool)  ← new condition
//   activities        – List of extra-curricular activities (array, need >= 2)
//   research_topics   – List of scientific research topics (array)
//
// REMOVED: family_income, is_disadvantaged (no longer used)
// ============================================================

$mock_students = [

    // ── 1. Eligible – high score, has research + certificate ──────
    [
        'id'                  => 1,
        'full_name'           => 'Nguyen Van An',
        'email'               => 'an01@student.edu.vn',
        'faculty'             => 'Information Technology',
        'major'               => 'Software Engineering',
        'gpa'                 => 3.6,
        'failed_subjects'     => 0,          // ✅ No F grades
        'language_certificate'=> true,        // ✅ Has certificate
        'activities'          => [            // ✅ >= 2 activities
            'Volunteer Club',
            'Robotics Club',
        ],
        'research_topics'     => [            // Has research
            'AI in Education',
        ],
    ],

    // ── 2. FAILED – GPA < 3.2 ──────────────────────────────────
    [
        'id'                  => 2,
        'full_name'           => 'Tran Minh Hoang',
        'email'               => 'hoang02@student.edu.vn',
        'faculty'             => 'Business Administration',
        'major'               => 'Digital Marketing',
        'gpa'                 => 3.1,         // ❌ GPA < 3.2
        'failed_subjects'     => 0,
        'language_certificate'=> false,       // ❌ No certificate
        'activities'          => [
            'Football Team',
            'Student Council',
        ],
        'research_topics'     => [],
    ],

    // ── 3. Eligible – high GPA, many activities + research ─────
    [
        'id'                  => 3,
        'full_name'           => 'Le Thu Ha',
        'email'               => 'ha03@student.edu.vn',
        'faculty'             => 'Foreign Languages',
        'major'               => 'English Studies',
        'gpa'                 => 3.8,
        'failed_subjects'     => 0,
        'language_certificate'=> true,
        'activities'          => [
            'Debate Club',
            'Volunteer Club',
            'Math Society',
        ],
        'research_topics'     => [
            'Quantum Computing',
        ],
    ],

    // ── 4. FAILED – has F grade + low GPA ──────────────────────
    [
        'id'                  => 4,
        'full_name'           => 'Pham Gia Bao',
        'email'               => 'bao04@student.edu.vn',
        'faculty'             => 'Information Technology',
        'major'               => 'Network Engineering',
        'gpa'                 => 2.9,         // ❌ GPA < 3.2
        'failed_subjects'     => 1,           // ❌ Has F grade
        'language_certificate'=> true,
        'activities'          => [
            'Music Club',
            'Volunteer Club',
        ],
        'research_topics'     => [],
    ],

    // ── 5. FAILED – no language certificate ──────────────
    [
        'id'                  => 5,
        'full_name'           => 'Vo Thanh Dat',
        'email'               => 'dat05@student.edu.vn',
        'faculty'             => 'Business Administration',
        'major'               => 'Finance',
        'gpa'                 => 3.4,
        'failed_subjects'     => 0,
        'language_certificate'=> false,       // ❌ No certificate
        'activities'          => [
            'Student Council',
            'Photography Club',
        ],
        'research_topics'     => [],
    ],

    // ── 6. Eligible – GPA exactly at threshold, has research ───────────
    [
        'id'                  => 6,
        'full_name'           => 'Bui Quoc Khanh',
        'email'               => 'khanh06@student.edu.vn',
        'faculty'             => 'Information Technology',
        'major'               => 'Cybersecurity',
        'gpa'                 => 3.2,         // ✅ GPA = 3.2 (exactly at threshold)
        'failed_subjects'     => 0,
        'language_certificate'=> true,
        'activities'          => [
            'Volunteer Club',
            'Coding Club',
        ],
        'research_topics'     => [
            'Blockchain Security',
        ],
    ],

    // ── 7. FAILED – only 1 activity ───────────────────────────
    [
        'id'                  => 7,
        'full_name'           => 'Dang Ngoc Linh',
        'email'               => 'linh07@student.edu.vn',
        'faculty'             => 'Foreign Languages',
        'major'               => 'Japanese Studies',
        'gpa'                 => 3.3,
        'failed_subjects'     => 0,
        'language_certificate'=> true,
        'activities'          => [
            'Art Club',               // ❌ Only 1 activity (< 2)
        ],
        'research_topics'     => [],
    ],

    // ── 8. Eligible – highest score, multiple research topics ──────────
    [
        'id'                  => 8,
        'full_name'           => 'Hoang Minh Quan',
        'email'               => 'quan08@student.edu.vn',
        'faculty'             => 'Information Technology',
        'major'               => 'Data Science',
        'gpa'                 => 3.9,
        'failed_subjects'     => 0,
        'language_certificate'=> true,
        'activities'          => [
            'Research Group',
            'Math Society',
            'Volunteer Club',
        ],
        'research_topics'     => [
            'Renewable Energy Optimization',
            'Smart Grids',
        ],
    ],

    // ── 9. Eligible – no research topics but meets all criteria
    [
        'id'                  => 9,
        'full_name'           => 'Do Thi Mai',
        'email'               => 'mai09@student.edu.vn',
        'faculty'             => 'Business Administration',
        'major'               => 'International Business',
        'gpa'                 => 3.3,
        'failed_subjects'     => 0,
        'language_certificate'=> true,
        'activities'          => [
            'Debate Club',
            'Volunteer Club',
        ],
        'research_topics'     => [],          // No research topics (no deduction, just no bonus points)
    ],

    // ── 10. Eligible – has research + certificate ───────────────
    [
        'id'                  => 10,
        'full_name'           => 'Phan Duc Huy',
        'email'               => 'huy10@student.edu.vn',
        'faculty'             => 'Information Technology',
        'major'               => 'Robotics',
        'gpa'                 => 3.5,
        'failed_subjects'     => 0,
        'language_certificate'=> true,
        'activities'          => [
            'Coding Club',
            'Robotics Club',
        ],
        'research_topics'     => [
            'Autonomous Drones',
        ],
    ],
];

return $mock_students;
