<?php
// ============================================================
// app/Models/EligibilityModel.php
// ============================================================

namespace App\Models;

use App\Core\BaseModel;

class EligibilityModel extends BaseModel
{
    protected string $table = 'eligibility_results';

    /**
     * Get all eligibility results with student and program info.
     */
    public function getAllWithDetails(): array
    {
        return $this->raw("
            SELECT er.*,
                   a.id AS application_number,
                   u.full_name AS student_name,
                   sp.name AS program_name
            FROM eligibility_results er
            INNER JOIN applications a ON er.application_id = a.id
            INNER JOIN users u ON a.student_id = u.id
            INNER JOIN scholarship_programs sp ON a.program_id = sp.id
            ORDER BY er.id DESC
        ");
    }

    /**
     * Save or update an eligibility result.
     * Upserts: updates if exists, inserts if not.
     */
    public function saveResult(int $applicationId, int $isPassed, string $reason): void
    {
        $existing = $this->rawOne(
            "SELECT id FROM eligibility_results WHERE application_id = ?",
            [$applicationId]
        );

        if ($existing) {
            $this->exec("
                UPDATE eligibility_results
                SET is_passed = ?, reason = ?, checked_at = CURRENT_TIMESTAMP
                WHERE application_id = ?
            ", [$isPassed, $reason, $applicationId]);
        } else {
            $this->exec("
                INSERT INTO eligibility_results (application_id, is_passed, reason, checked_at)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ", [$applicationId, $isPassed, $reason]);
        }
    }

    /**
     * Get eligibility rules for a specific program.
     */
    public function getRulesForProgram(int $programId): array
    {
        return $this->raw(
            "SELECT * FROM eligibility_rules WHERE program_id = ?",
            [$programId]
        );
    }

    /**
     * Get the result for a specific application.
     */
    public function getResultForApplication(int $applicationId): ?array
    {
        return $this->rawOne(
            "SELECT * FROM eligibility_results WHERE application_id = ? LIMIT 1",
            [$applicationId]
        );
    }
}
