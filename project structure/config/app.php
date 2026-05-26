<?php
// ============================================================
// config/app.php  –  Application constants
// ============================================================

if (!defined('BASE_URL')) {
    // URL to the project root as seen from the browser.
    // Use %20 for the space in "project structure" so HTTP redirects work.
    define('BASE_URL', 'http://localhost/project%20structure');
}

if (!defined('BASE_PATH')) {
    // Absolute filesystem path to the project root (one level up from config/).
    define('BASE_PATH', dirname(__DIR__));
}
