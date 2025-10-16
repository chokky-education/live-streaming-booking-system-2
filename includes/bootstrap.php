<?php
/**
 * Application bootstrap file
 * - Sets up autoloading and global configuration
 * - Optionally seeds a shared database connection
 * - Prepares request context helpers
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/..'));
}

$composerAutoload = BASE_PATH . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Ensure request ID exists for logging correlation
init_request_id();

// Seed a shared database connection for consumers that prefer using globals
try {
    $database = Database::getInstance();
    $GLOBALS['db_connection'] = $database->getConnection();
} catch (Exception $e) {
    log_event('Database bootstrap failed: ' . $e->getMessage(), 'ERROR');
    throw $e;
}

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
