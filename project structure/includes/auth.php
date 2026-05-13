<?php
// ============================================================
// includes/auth.php  –  Xác thực & Phân quyền
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function currentRole(): ?string {
    return $_SESSION['role'] ?? null;
}

function currentUserId(): ?int {
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function currentUserName(): string {
    return $_SESSION['user_name'] ?? 'Người dùng';
}

function isAdmin(): bool {
    return currentRole() === 'admin';
}

function isStudent(): bool {
    return currentRole() === 'student';
}

function isCouncil(): bool {
    return currentRole() === 'council';
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array(currentRole(), $roles, true)) {
        http_response_code(403);
        echo '<div class="container mt-5">'
           . '<div class="alert alert-danger">'
           . '<h4>403 – Không có quyền truy cập</h4>'
           . '<p>Bạn không có quyền xem trang này.</p>'
           . '<a href="' . BASE_URL . '/index.php" class="btn btn-primary">Về trang chủ</a>'
           . '</div></div>';
        exit;
    }
}

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
               . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
               . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
               . '</div>';
        }
    }
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function post(string $key, string $default = ''): string {
    return trim($_POST[$key] ?? $default);
}

function get(string $key, string $default = ''): string {
    return trim($_GET[$key] ?? $default);
}
