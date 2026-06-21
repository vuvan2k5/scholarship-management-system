<?php
// ============================================================
// includes/auth.php  –  Xác thực & Phân quyền
// Require file này ở ĐẦU mọi trang cần bảo vệ
// ============================================================

// Đảm bảo session đã start (gọi nhiều lần cũng an toàn)
require_once __DIR__ . '/../config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ------------------------------------------------------------
// HÀM KIỂM TRA ĐĂNG NHẬP
// ------------------------------------------------------------

/**
 * Kiểm tra người dùng đã đăng nhập chưa.
 */

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Bắt buộc đăng nhập – nếu chưa thì chuyển về login.php
 * Dùng ở đầu mọi trang cần đăng nhập.
 *
 * Ví dụ: requireLogin();
 */

function requireLogin(): void {

    if (!isLoggedIn()) {

        // Avoid redirect loop if already on login page
        $loginUrl = BASE_URL . '/login.php';
        $currentUri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($currentUri, '/login.php') === false) {
            $_SESSION['redirect_after_login'] = $currentUri;
        }

        header('Location: ' . $loginUrl);
        exit;
    }
}

// ------------------------------------------------------------
// HÀM KIỂM TRA ROLE
// ------------------------------------------------------------

/**
 * Lấy role hiện tại của người dùng.
 * Trả về: 'admin' | 'student' | 'reviewer' | null
 */

function currentRole(): ?string {
    $role = $_SESSION['role'] ?? null;
    // Normalize legacy 'council' to 'reviewer' transparently
    if ($role === 'council') {
        $_SESSION['role'] = 'reviewer';
        return 'reviewer';
    }
    return $role;
}

/**
 * Lấy user_id hiện tại.
 */

function currentUserId(): ?int {

    return isset($_SESSION['user_id'])
        ? (int) $_SESSION['user_id']
        : null;
}

/**
 * Lấy tên hiển thị của người dùng hiện tại.
 */

function currentUserName(): string {

    return $_SESSION['user_name'] ?? 'User';
}

/**
 * Kiểm tra có phải Admin không.
 */

function isAdmin(): bool {

    return currentRole() === 'admin';
}

/**
 * Kiểm tra có phải Student không.
 */

function isStudent(): bool {

    return currentRole() === 'student';
}

/**
 * Kiểm tra có phải Reviewer không.
 */

function isReviewer(): bool {
    return currentRole() === 'reviewer';
}

// Keep isCouncil() as alias for backward compatibility
function isCouncil(): bool {
    return currentRole() === 'reviewer';
}

/**
 * Bắt buộc phải có role cụ thể – không đúng thì báo lỗi 403.
 * Có thể truyền nhiều role cùng lúc.
 *
 * Ví dụ:
 *   requireRole('admin');
 *   requireRole('admin', 'reviewer');
 */

function requireRole(string ...$roles): void {

    requireLogin();

    if (!in_array(currentRole(), $roles, true)) {

        http_response_code(403);

        // Render a self-contained 403 page (no sidebar needed)
        echo '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>403 – Access Denied</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f1f5f9; font-family: system-ui, sans-serif; }
    .box { max-width:480px; margin:100px auto; background:#fff; border-radius:16px;
           padding:40px; box-shadow:0 4px 20px rgba(0,0,0,.08); text-align:center; }
    .icon { font-size:56px; margin-bottom:16px; }
    h2 { font-weight:700; color:#0f172a; }
    p  { color:#64748b; }
  </style>
</head>
<body>
  <div class="box">
    <div class="icon">🔒</div>
    <h2>403 – Access Denied</h2>
    <p>You do not have permission to view this page.</p>
    <a href="' . BASE_URL . '/index.php" class="btn btn-primary mt-2">Go to Home</a>
    <a href="' . BASE_URL . '/logout.php" class="btn btn-outline-secondary mt-2 ms-2">Sign Out</a>
  </div>
</body>
</html>';

        exit;
    }
}

// ------------------------------------------------------------
// HÀM FLASH MESSAGE (thông báo 1 lần)
// ------------------------------------------------------------

/**
 * Set hoặc get flash message.
 *
 * Set:  setFlash('success', 'Lưu thành công!');
 * Get:  $msg = getFlash('success');
 */

function setFlash(string $type, string $message): void {

    $_SESSION['flash'][$type] = $message;
}

function getFlash(string $type): ?string {

    if (isset($_SESSION['flash'][$type])) {

        $msg = $_SESSION['flash'][$type];

        unset($_SESSION['flash'][$type]);

        return $msg;
    }

    return null;
}

/**
 * In ra HTML flash message nếu có.
 * Gọi trong view: showFlash();
 */

function showFlash(): void {

    $types = [

        'success' => 'success',
        'error'   => 'danger',
        'warning' => 'warning',
        'info'    => 'info',
    ];

    foreach ($types as $key => $bsClass) {

        $msg = getFlash($key);

        if ($msg) {

            echo '<div class="alert alert-'
               . $bsClass
               . ' alert-dismissible fade show" role="alert">'
               . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
               . '<button type="button"
                          class="btn-close"
                          data-bs-dismiss="alert"></button>'
               . '</div>';
        }
    }
}

// ------------------------------------------------------------
// HÀM TIỆN ÍCH KHÁC
// ------------------------------------------------------------

/**
 * Redirect đến URL và dừng script.
 * Ví dụ: redirect('/admin/dashboard.php');
 */

function redirect(string $url): void {

    header('Location: ' . $url);

    exit;
}

/**
 * Làm sạch input từ người dùng (chống XSS).
 * Dùng khi echo dữ liệu ra HTML.
 * Ví dụ: echo e($user['full_name']);
 */

function e($value): string {

    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/**
 * Lấy POST value đã trim, tránh undefined index.
 * Ví dụ: $email = post('email');
 */

function post(string $key, string $default = ''): string {

    return trim($_POST[$key] ?? $default);
}

/**
 * Lấy GET value đã trim.
 * Ví dụ: $id = (int) get('id');
 */

function get(string $key, string $default = ''): string {

    return trim($_GET[$key] ?? $default);
}