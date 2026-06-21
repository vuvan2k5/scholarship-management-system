<?php
// ============================================================
// app/Models/ProgramModel.php
// ============================================================

namespace App\Models;

use App\Core\BaseModel;

class ProgramModel extends BaseModel
{
    protected string $table = 'scholarship_programs';

    /**
     * Get all open programs.
     */
    public function getOpen(): array
    {
        return $this->raw(
            "SELECT * FROM scholarship_programs WHERE status = 'open' ORDER BY name ASC"
        );
    }

    /**
     * Create a new scholarship program.
     */
    public function create(array $data): int
    {
        $this->exec("
            INSERT INTO scholarship_programs (name, description, budget, slots, start_date, end_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ", [
            $data['name'],
            $data['description'],
            $data['budget'],
            $data['slots'],
            $data['start_date'],
            $data['end_date'],
            $data['status'] ?? 'open',
        ]);

        return $this->lastInsertId();
    }

    /**
     * Update a program.
     */
    public function update(int $id, array $data): void
    {
        $this->exec("
            UPDATE scholarship_programs
            SET name = ?, description = ?, budget = ?, slots = ?,
                start_date = ?, end_date = ?, status = ?
            WHERE id = ?
        ", [
            $data['name'],
            $data['description'],
            $data['budget'],
            $data['slots'],
            $data['start_date'],
            $data['end_date'],
            $data['status'],
            $id,
        ]);
    }

    /**
     * Get total budget across all programs (for dashboard).
     */
    public function getTotalBudget(): float
    {
        return (float) $this->pdo->query("SELECT SUM(budget) FROM scholarship_programs")->fetchColumn();
    }
}
