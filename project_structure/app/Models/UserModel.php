<?php
// ============================================================
// app/Models/UserModel.php
// ============================================================

namespace App\Models;

use App\Core\BaseModel;

class UserModel extends BaseModel
{
    protected string $table = 'users';

    /**
     * Find user by email (for login).
     */
    public function findByEmail(string $email): ?array
    {
        return $this->rawOne(
            "SELECT * FROM users WHERE email = ? LIMIT 1",
            [$email]
        );
    }

    /**
     * Get all users with a specific role.
     */
    public function getByRole(string $role): array
    {
        return $this->raw(
            "SELECT * FROM users WHERE role = ? ORDER BY full_name ASC",
            [$role]
        );
    }

    /**
     * Get all students (role = 'student').
     */
    public function getAllStudents(): array
    {
        return $this->getByRole('student');
    }

    /**
     * Create a new user. Returns new user ID.
     */
    public function create(string $fullName, string $email, string $passwordHash, string $role, ?string $studentCode = null): int
    {
        $this->exec("
            INSERT INTO users (full_name, email, password_hash, role, student_code)
            VALUES (?, ?, ?, ?, ?)
        ", [$fullName, $email, $passwordHash, $role, $studentCode]);

        return $this->lastInsertId();
    }

    /**
     * Update user details.
     */
    public function update(int $id, string $fullName, string $email, string $role, ?string $studentCode): void
    {
        $this->exec("
            UPDATE users SET full_name = ?, email = ?, role = ?, student_code = ?
            WHERE id = ?
        ", [$fullName, $email, $role, $studentCode, $id]);
    }

    /**
     * Update user password hash.
     */
    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->exec(
            "UPDATE users SET password_hash = ? WHERE id = ?",
            [$passwordHash, $id]
        );
    }
}
