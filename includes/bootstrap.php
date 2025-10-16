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

// Attempt to establish a shared database connection (safe to fail)
$connection = null;
$dbBootstrapException = null;

try {
    $database = Database::getInstance();
    $connection = $database->getConnection();
} catch (Exception $e) {
    $dbBootstrapException = $e;
    log_event('Database bootstrap failed: ' . $e->getMessage(), 'ERROR');
}

$GLOBALS['db_connection'] = $connection;
if (!defined('DATABASE_AVAILABLE')) {
    define('DATABASE_AVAILABLE', $connection !== null);
}

if ($dbBootstrapException !== null) {
    $GLOBALS['db_bootstrap_error'] = $dbBootstrapException;
}

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
