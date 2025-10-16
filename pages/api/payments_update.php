<?php
/**
 * Update (verify/reject) a payment — admin only
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/Payment.php';

init_request_id();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true) ?: [];
    if ($method === 'POST' && isset($payload['_method']) && strtoupper($payload['_method']) === 'PATCH') {
        $method = 'PATCH';
    }
    if ($method !== 'PATCH') {
        api_error('METHOD_NOT_ALLOWED', 'PATCH required', 405);
    }

    if (!is_admin()) {
        api_error('FORBIDDEN', 'Admin privileges required', 403);
    }

    $csrf_header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    $csrf = $csrf_header ?: ($payload['csrf_token'] ?? null);
    if (!$csrf || !verify_csrf_token($csrf)) {
        api_error('CSRF_INVALID', 'Invalid CSRF token', 401);
    }

    $payment_id = isset($payload['id']) ? (int)$payload['id'] : null;
    $status = isset($payload['status']) ? strtolower(trim($payload['status'])) : null;
    $notes = isset($payload['notes']) ? sanitize_input($payload['notes']) : null;
    if (!$payment_id || !in_array($status, ['verified', 'rejected'])) {
        api_error('VALIDATION_ERROR', 'id and valid status required (verified|rejected)', 400);
    }

    $database = new Database();
    $db = $database->getConnection();
    $payment = new Payment($db);
    $row = $payment->getById($payment_id);
    if (!$row) {
        api_error('NOT_FOUND', 'Payment not found', 404);
    }

    $payment->id = $payment_id;
    $ok = ($status === 'verified')
        ? $payment->verify($_SESSION['user_id'], $notes)
        : $payment->reject($_SESSION['user_id'], $notes);

    if ($ok) {
        log_event("API payment {$status}: id {$payment_id}", 'INFO');
        $updated = $payment->getById($payment_id);
        api_success(['payment' => $updated]);
    } else {
        api_error('UPDATE_FAILED', 'Could not update payment', 500);
    }
} catch (Throwable $e) {
    log_event('API update payment error: ' . $e->getMessage(), 'ERROR');
    api_error('INTERNAL_ERROR', 'Unexpected error', 500);
}

?>

