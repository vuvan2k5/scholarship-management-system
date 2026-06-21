<?php
// ============================================================
// student/api/save_draft.php  –  AJAX auto-save draft endpoint
// Called by apply.php every N seconds or on blur
// POST body (JSON or form-encoded):
//   program_id  : int
//   draft_id    : int|null  (existing draft to update)
//   draft_notes : string
// Returns JSON { success, draft_id, message }
// ============================================================

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

requireLogin();
requireRole('student');

$pdo       = getDB();
$studentId = currentUserId();

// Accept both JSON body and form-encoded
$body = [];
$raw  = file_get_contents('php://input');
if ($raw) {
    $body = json_decode($raw, true) ?? [];
}
$programId  = (int)(   $body['program_id']  ?? $_POST['program_id']  ?? 0);
$draftId    = (int)(   $body['draft_id']    ?? $_POST['draft_id']    ?? 0);
$draftNotes = trim(    $body['draft_notes'] ?? $_POST['draft_notes'] ?? '');

if ($programId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Chưa chọn chương trình học bổng.']);
    exit;
}

// Verify program exists and is open
$progCheck = $pdo->prepare("SELECT id FROM scholarship_programs WHERE id=? AND status='open'");
$progCheck->execute([$programId]);
if (!$progCheck->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Chương trình không hợp lệ hoặc đã đóng.']);
    exit;
}

// Verify the student hasn't formally submitted for this program already
$submitted = $pdo->prepare(
    "SELECT id FROM applications WHERE student_id=? AND program_id=? AND status != 'draft'"
);
$submitted->execute([$studentId, $programId]);
if ($submitted->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Bạn đã nộp đơn chính thức cho chương trình này.']);
    exit;
}

try {
    if ($draftId > 0) {
        // Verify ownership
        $own = $pdo->prepare(
            "SELECT id FROM applications WHERE id=? AND student_id=? AND status='draft'"
        );
        $own->execute([$draftId, $studentId]);
        if (!$own->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Đơn nháp không tồn tại.']);
            exit;
        }
        $pdo->prepare(
            "UPDATE applications
             SET program_id=?, draft_notes=?, updated_at=NOW()
             WHERE id=? AND student_id=?"
        )->execute([$programId, $draftNotes, $draftId, $studentId]);
        $resultId = $draftId;
        $msg = 'Đã lưu nháp tự động.';
    } else {
        // Check for existing draft on this program
        $exist = $pdo->prepare(
            "SELECT id FROM applications WHERE student_id=? AND program_id=? AND status='draft'"
        );
        $exist->execute([$studentId, $programId]);
        $existRow = $exist->fetch();

        if ($existRow) {
            $resultId = (int)$existRow['id'];
            $pdo->prepare(
                "UPDATE applications SET draft_notes=?, updated_at=NOW() WHERE id=?"
            )->execute([$draftNotes, $resultId]);
            $msg = 'Đã cập nhật nháp tự động.';
        } else {
            $pdo->prepare(
                "INSERT INTO applications (student_id, program_id, status, draft_notes)
                 VALUES (?, ?, 'draft', ?)"
            )->execute([$studentId, $programId, $draftNotes]);
            $resultId = (int)$pdo->lastInsertId();
            $msg = 'Đã tạo đơn nháp mới.';
        }
    }

    echo json_encode([
        'success'  => true,
        'draft_id' => $resultId,
        'message'  => $msg,
        'saved_at' => date('H:i:s'),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu.']);
}
