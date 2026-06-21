<?php
// ============================================================
// app/Models/ApplicationModel.php
// ============================================================
// All database logic for the `applications` table lives here.
// Controllers call these methods — zero SQL in views.
// ============================================================

namespace App\Models;

use App\Core\BaseModel;

class ApplicationModel extends BaseModel
{
    protected string $table = 'applications';

    /**
     * Get all applications with student name and program name via JOIN.
     */
    public function getAllWithDetails(): array
    {
        return $this->raw("
            SELECT a.*,
                   u.full_name,
                   u.student_code,
                   sp.name AS program_name
            FROM applications a
            JOIN users u  ON a.student_id = u.id
            JOIN scholarship_programs sp ON a.program_id = sp.id
            ORDER BY a.id DESC
        ");
    }

    /**
     * Get one application with full joined details.
     */
    public function findWithDetails(int $id): ?array
    {
        return $this->rawOne("
            SELECT a.*,
                   u.full_name,
                   u.student_code,
                   sp.name AS program_name
            FROM applications a
            JOIN users u  ON a.student_id = u.id
            JOIN scholarship_programs sp ON a.program_id = sp.id
            WHERE a.id = ?
        ", [$id]);
    }

    /**
     * Create a new application. Returns new ID.
     */
    public function create(int $studentId, int $programId, string $status = 'submitted'): int
    {
        $this->exec("
            INSERT INTO applications (student_id, program_id, status, submitted_at)
            VALUES (?, ?, ?, NOW())
        ", [$studentId, $programId, $status]);

        return $this->lastInsertId();
    }

    /**
     * Update status and eligible fields.
     */
    public function updateStatus(int $id, string $status, ?int $eligible): void
    {
        $this->exec("
            UPDATE applications SET status = ?, eligible = ? WHERE id = ?
        ", [$status, $eligible, $id]);
    }

    /**
     * Get all applications for a specific student.
     */
    public function getByStudent(int $studentId): array
    {
        return $this->raw("
            SELECT a.*, sp.name AS program_name
            FROM applications a
            JOIN scholarship_programs sp ON a.program_id = sp.id
            WHERE a.student_id = ?
            ORDER BY a.id DESC
        ", [$studentId]);
    }

    /**
     * Count applications grouped by status (for dashboard).
     * Returns: ['total' => N, 'eligible' => N, 'rejected' => N]
     */
    public function getStatusCounts(): array
    {
        $total    = $this->count();
        $eligible = $this->countWhere('eligible = 1');
        $rejected = $this->countWhere('eligible = 0');
        $pending  = $this->countWhere("status = 'submitted'");

        return compact('total', 'eligible', 'rejected', 'pending');
    }

    /**
     * Check if a student already applied to a program.
     */
    public function alreadyApplied(int $studentId, int $programId): bool
    {
        $count = (int) $this->rawOne("
            SELECT COUNT(*) AS cnt FROM applications
            WHERE student_id = ? AND program_id = ?
        ", [$studentId, $programId])['cnt'];

        return $count > 0;
    }
}
