<?php
// ============================================================
// config/app.php  –  Application constants
// ============================================================

/**
 * BASE_PATH: absolute filesystem path to the project root.
 * (one level up from config/)
 */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

/**
 * BASE_URL: base URL as seen from the browser.
 *
 * Folder name includes a space: "project structure".
 * Local XAMPP mappings can differ, and deriving BASE_URL from REQUEST_URI
 * may mis-detect the prefix.
 *
 * We derive BASE_URL from SCRIPT_NAME by cutting at the app folder segment.
 */
if (!defined('BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // __DIR__: .../project structure/config
    $appFolderName = basename(dirname(__DIR__)); // "project structure"

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $encodedAppFolder = rawurlencode($appFolderName);

    // Try to locate the app folder inside SCRIPT_NAME (with and without encoding).
    $needle1 = '/' . $appFolderName;
    $needle2 = '/' . $encodedAppFolder;

    $p1 = ($scriptName !== '') ? strpos($scriptName, $needle1) : false;
    $p2 = ($scriptName !== '') ? strpos($scriptName, $needle2) : false;

    if ($p1 !== false) {
        $basePath = substr($scriptName, 0, $p1 + strlen($needle1));
        $basePath = rtrim($basePath, '/');
        define('BASE_URL', $scheme . '://' . $host . $basePath);
    } elseif ($p2 !== false) {
        $basePath = substr($scriptName, 0, $p2 + strlen($needle2));
        $basePath = rtrim($basePath, '/');
        define('BASE_URL', $scheme . '://' . $host . $basePath);
    } else {
        // Fallback for the common mapping: /scholarship-management-system/project%20structure
        define('BASE_URL', $scheme . '://' . $host . '/scholarship-management-system/' . $encodedAppFolder);
    }
}

