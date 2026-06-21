<?php
// ============================================================
// app/Core/BaseModel.php
// ============================================================
// Base class for all Models.
// Provides a shared PDO connection and common CRUD helpers.
// All SQL lives in Model classes (subclasses of BaseModel),
// NEVER in Controllers or Views.
// ============================================================

namespace App\Core;

use PDO;

abstract class BaseModel
{
    /** @var PDO Shared PDO connection */
    protected PDO $pdo;

    /** @var string The database table name — must be set by each subclass */
    protected string $table = '';

    public function __construct()
    {
        // Reuse the existing singleton getDB() from config/db.php
        // This avoids a second connection and respects the project's DB config.
        if (!function_exists('getDB')) {
            require_once dirname(__DIR__, 2) . '/config/db.php';
        }
        $this->pdo = \getDB();
    }

    // ----------------------------------------------------------
    // Generic CRUD helpers
    // ----------------------------------------------------------

    /**
     * Return ALL rows from this model's table, ordered by id DESC.
     */
    public function all(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM `{$this->table}` ORDER BY id DESC");
        return $stmt->fetchAll();
    }

    /**
     * Find one row by primary key.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `{$this->table}` WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Delete a row by primary key.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Count all rows.
     */
    public function count(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM `{$this->table}`")->fetchColumn();
    }

    /**
     * Count rows matching a WHERE clause.
     * Example: $this->countWhere('status = ?', ['approved'])
     */
    public function countWhere(string $where, array $params = []): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `{$this->table}` WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Execute a raw prepared query and return all results.
     * Use for complex JOINs that don't fit simple CRUD.
     */
    protected function raw(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a raw prepared query and return one row.
     */
    protected function rawOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Execute a write query (INSERT/UPDATE/DELETE) and return affected rows.
     */
    protected function exec(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Return the last inserted auto-increment ID.
     */
    protected function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }
}
