<?php
// ============================================================
// includes/auth.php  –  Xác thực & Phân quyền
// Require file này ở ĐẦU mọi trang cần bảo vệ
// ============================================================

// Đảm bảo session đã start (gọi nhiều lần cũng an toàn)
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
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

// ------------------------------------------------------------
// HÀM KIỂM TRA ROLE
// ------------------------------------------------------------

/**
 * Lấy role hiện tại của người dùng.
 * Trả về: 'admin' | 'student' | 'council' | null
 */
function currentRole(): ?string {
    return $_SESSION['role'] ?? null;
}

/**
 * Lấy user_id hiện tại.
 */
function currentUserId(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Lấy tên hiển thị của người dùng hiện tại.
 */
function currentUserName(): string {
    return $_SESSION['user_name'] ?? 'Người dùng';
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
 * Kiểm tra có phải Council (Hội đồng) không.
 */
function isCouncil(): bool {
    return currentRole() === 'council';
}

/**
 * Bắt buộc phải có role cụ thể – không đúng thì báo lỗi 403.
 * Có thể truyền nhiều role cùng lúc.
 *
 * Ví dụ:
 *   requireRole('admin');
 *   requireRole('admin', 'council');
 */
function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array(currentRole(), $roles, true)) {
        http_response_code(403);
        include BASE_PATH . '/includes/header.php';
        echo '<div class="container mt-5">
                <div class="alert alert-danger">
                    <h4>403 – Không có quyền truy cập</h4>
                    <p>Bạn không có quyền xem trang này.</p>
                    <a href="' . BASE_URL . '/index.php" class="btn btn-primary">Về trang chủ</a>
                </div>
              </div>';
        include BASE_PATH . '/includes/footer.php';
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
 * Get:  $msg = getFlash('success');  → trả về string | null
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
            echo '<div class="alert alert-' . $bsClass . ' alert-dismissible fade show" role="alert">'
               . htmlspecialchars($msg)
               . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
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
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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
