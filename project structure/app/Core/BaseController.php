<?php
// ============================================================
// app/Core/BaseController.php
// ============================================================
// Base class for all Controllers.
// Handles: view rendering, redirects, flash messages,
// and authentication guards.
// Controllers NEVER contain SQL — they call Models.
// ============================================================

namespace App\Core;

abstract class BaseController
{
    // ----------------------------------------------------------
    // View Rendering
    // ----------------------------------------------------------

    /**
     * Render a view file, injecting $data variables into scope.
     *
     * Convention: views live in /views/{path}.php
     * Example: $this->render('admin/applications/index', ['rows' => $rows])
     *
     * @param string $view  Relative path from /views/ (without .php)
     * @param array  $data  Variables to extract into view scope
     */
    protected function render(string $view, array $data = []): void
    {
        // Extract data array into local variables for the view
        extract($data, EXTR_SKIP);

        $viewPath = dirname(__DIR__, 2) . '/views/' . $view . '.php';

        if (!file_exists($viewPath)) {
            http_response_code(500);
            die("View not found: <code>" . htmlspecialchars($viewPath) . "</code>");
        }

        require $viewPath;
    }

    // ----------------------------------------------------------
    // Redirects
    // ----------------------------------------------------------

    /**
     * Redirect to a URL and exit.
     * Accepts absolute URLs or paths relative to BASE_URL.
     */
    protected function redirect(string $url): void
    {
        // If relative path, prepend BASE_URL
        if (!str_starts_with($url, 'http') && defined('BASE_URL')) {
            $url = BASE_URL . '/' . ltrim($url, '/');
        }
        header('Location: ' . $url);
        exit;
    }

    // ----------------------------------------------------------
    // Flash Messages
    // ----------------------------------------------------------

    /**
     * Set a flash message (delegates to existing setFlash() helper).
     */
    protected function flash(string $type, string $message): void
    {
        if (function_exists('setFlash')) {
            setFlash($type, $message);
        }
    }

    // ----------------------------------------------------------
    // Authentication Guards
    // ----------------------------------------------------------

    /**
     * Require the user to be logged in.
     * Delegates to existing requireLogin() from auth.php.
     */
    protected function requireLogin(): void
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }
    }

    /**
     * Require the user to have one of the specified roles.
     * Delegates to existing requireRole() from auth.php.
     *
     * Example: $this->requireRole('admin');
     */
    protected function requireRole(string ...$roles): void
    {
        if (function_exists('requireRole')) {
            requireRole(...$roles);
        }
    }

    // ----------------------------------------------------------
    // Request Helpers
    // ----------------------------------------------------------

    /** Check if current request is POST */
    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /** Get a POST value, trimmed. Returns default if missing. */
    protected function post(string $key, string $default = ''): string
    {
        return trim($_POST[$key] ?? $default);
    }

    /** Get a GET value, trimmed. Returns default if missing. */
    protected function get(string $key, string $default = ''): string
    {
        return trim($_GET[$key] ?? $default);
    }

    /** Get current user ID from session */
    protected function currentUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }
}
