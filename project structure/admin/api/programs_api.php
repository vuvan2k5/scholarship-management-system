<?php
// ============================================================
// admin/api/programs_api.php
// JSON API endpoint for Scholarship Programs AJAX CRUD
// All responses: Content-Type: application/json
// ============================================================

require_once '../../config/db.php';
require_once '../../includes/auth.php';

// Guard: must be logged-in admin
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
requireRole('admin');

header('Content-Type: application/json; charset=UTF-8');

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── Helper ─────────────────────────────────────────────────
function jsonOk(mixed $data = null, string $message = 'OK'): void {
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data]);
    exit;
}
function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}
function safeStr(mixed $v): string { return trim((string)($v ?? '')); }

// ── Route ──────────────────────────────────────────────────

switch ($action) {

    // ── LIST ─────────────────────────────────────────────────
    case 'list':
        $rows = $pdo->query("
            SELECT id, name, description, budget, slots,
                   start_date, end_date, status
            FROM scholarship_programs
            ORDER BY id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        jsonOk($rows);

    // ── GET ONE ───────────────────────────────────────────────
    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM scholarship_programs WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) jsonError('Program not found.', 404);
        jsonOk($row);

    // ── CREATE ────────────────────────────────────────────────
    case 'create':
        if ($method !== 'POST') jsonError('POST required.', 405);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $name        = safeStr($body['name'] ?? '');
        $description = safeStr($body['description'] ?? '');
        $budget      = (float)($body['budget'] ?? 0);
        $slots       = (int)($body['slots'] ?? 0);
        $start_date  = safeStr($body['start_date'] ?? '');
        $end_date    = safeStr($body['end_date'] ?? '');
        $status      = in_array($body['status'] ?? '', ['open', 'closed']) ? $body['status'] : 'open';

        if (!$name)       jsonError('Program name is required.');
        if ($budget <= 0) jsonError('Budget must be greater than 0.');
        if ($slots <= 0)  jsonError('Slots must be greater than 0.');

        $stmt = $pdo->prepare("
            INSERT INTO scholarship_programs
                (name, description, budget, slots, start_date, end_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $description, $budget, $slots,
            $start_date ?: null, $end_date ?: null, $status]);

        $newId = (int)$pdo->lastInsertId();
        $newRow = $pdo->prepare("SELECT * FROM scholarship_programs WHERE id = ?");
        $newRow->execute([$newId]);
        jsonOk($newRow->fetch(PDO::FETCH_ASSOC), 'Program created successfully.');

    // ── UPDATE ────────────────────────────────────────────────
    case 'update':
        if ($method !== 'POST') jsonError('POST required.', 405);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = (int)($body['id'] ?? 0);
        if (!$id) jsonError('Invalid ID.');

        $name        = safeStr($body['name'] ?? '');
        $description = safeStr($body['description'] ?? '');
        $budget      = (float)($body['budget'] ?? 0);
        $slots       = (int)($body['slots'] ?? 0);
        $start_date  = safeStr($body['start_date'] ?? '');
        $end_date    = safeStr($body['end_date'] ?? '');
        $status      = in_array($body['status'] ?? '', ['open', 'closed']) ? $body['status'] : 'open';

        if (!$name)       jsonError('Program name is required.');
        if ($budget <= 0) jsonError('Budget must be greater than 0.');
        if ($slots <= 0)  jsonError('Slots must be greater than 0.');

        $stmt = $pdo->prepare("
            UPDATE scholarship_programs
            SET name = ?, description = ?, budget = ?, slots = ?,
                start_date = ?, end_date = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $description, $budget, $slots,
            $start_date ?: null, $end_date ?: null, $status, $id]);

        $updated = $pdo->prepare("SELECT * FROM scholarship_programs WHERE id = ?");
        $updated->execute([$id]);
        jsonOk($updated->fetch(PDO::FETCH_ASSOC), 'Program updated successfully.');

    // ── DELETE ────────────────────────────────────────────────
    case 'delete':
        if ($method !== 'POST') jsonError('POST required.', 405);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = (int)($body['id'] ?? 0);
        if (!$id) jsonError('Invalid ID.');

        // Safety check: don't delete if applications exist
        $appCount = (int)$pdo->prepare("SELECT COUNT(*) FROM applications WHERE program_id = ?")
            ->execute([$id]) ? $pdo->query("SELECT COUNT(*) FROM applications WHERE program_id = $id")->fetchColumn() : 0;

        $chk = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE program_id = ?");
        $chk->execute([$id]);
        $appCount = (int)$chk->fetchColumn();

        if ($appCount > 0) {
            jsonError("Cannot delete: {$appCount} application(s) linked to this program.", 409);
        }

        $stmt = $pdo->prepare("DELETE FROM scholarship_programs WHERE id = ?");
        $stmt->execute([$id]);
        jsonOk(null, 'Program deleted successfully.');

    default:
        jsonError('Unknown action.', 400);
}
