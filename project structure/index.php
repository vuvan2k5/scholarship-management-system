<?php
// ============================================================
// index.php  –  Entry point: redirect to dashboard or login
// ============================================================

require_once 'config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, send to the correct dashboard
if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    // Normalize legacy council role
    if ($_SESSION['role'] === 'council') {
        $_SESSION['role'] = 'reviewer';
    }
    $role = $_SESSION['role'];
    if ($role === 'admin') {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
    } elseif ($role === 'student') {
        header('Location: ' . BASE_URL . '/student/dashboard.php');
    } elseif ($role === 'reviewer') {
        header('Location: ' . BASE_URL . '/reviewer/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . '/login.php');
    }
} else {
    header('Location: ' . BASE_URL . '/login.php');
}
exit;
