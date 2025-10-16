<?php
/**
 * Health Check Endpoint
 * Returns a basic OK response using the standard API envelope
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Initialize request ID for logging correlation
$rid = init_request_id();

// Optional: set minimal cache headers for health
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    log_event('API health check', 'INFO');
    api_success(['status' => 'ok']);
} catch (Throwable $e) {
    log_event('API health check failed: ' . $e->getMessage(), 'ERROR');
    api_error('INTERNAL_ERROR', 'Unexpected error', 500);
}

?>

