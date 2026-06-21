<?php
// ============================================================
// app/Core/Router.php
// ============================================================
// Lightweight file-based router.
// Each existing admin PHP page includes this to bootstrap the
// MVC pipeline without requiring URL rewriting (.htaccess).
//
// Usage pattern in admin/applications/index.php:
//   require_once '../../app/Core/Router.php';
//   Router::dispatch(ApplicationController::class, 'index');
// ============================================================

namespace App\Core;

class Router
{
    /**
     * Dispatch a request to the given Controller and action.
     *
     * Before dispatching, the autoloader is registered so that
     * App\Models\* and App\Controllers\* classes resolve correctly.
     *
     * @param string $controllerClass  Fully-qualified class name, e.g. App\Controllers\ApplicationController
     * @param string $action           Method name to call, e.g. 'index'
     * @param array  $params           Extra parameters to pass to the action
     */
    public static function dispatch(string $controllerClass, string $action, array $params = []): void
    {
        // Ensure the autoloader is registered
        self::registerAutoloader();

        if (!class_exists($controllerClass)) {
            http_response_code(500);
            die("Controller not found: <code>" . htmlspecialchars($controllerClass) . "</code>");
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $action)) {
            http_response_code(500);
            die("Action not found: <code>{$controllerClass}::{$action}</code>");
        }

        $controller->$action(...$params);
    }

    /**
     * Register a PSR-4 style autoloader for the App\ namespace.
     * Maps  App\  →  /project structure/app/
     */
    public static function registerAutoloader(): void
    {
        static $registered = false;
        if ($registered) return;

        $appRoot = dirname(__DIR__); // /project structure/app/

        spl_autoload_register(function (string $class) use ($appRoot): void {
            // Only handle App\ namespace
            if (strpos($class, 'App\\') !== 0) return;

            // Convert  App\Core\BaseModel  →  /app/Core/BaseModel.php
            $relative = str_replace(['App\\', '\\'], ['', '/'], $class);
            $file = $appRoot . '/' . $relative . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });

        $registered = true;
    }
}
