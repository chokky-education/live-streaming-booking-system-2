<?php
/**
 * Create payment metadata (user)
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/Booking.php';
require_once __DIR__ . '/../../models/Payment.php';

init_request_id();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('METHOD_NOT_ALLOWED', 'Only POST allowed', 405);
    }

    if (!is_logged_in()) {
        api_error('UNAUTHORIZED', 'Authentication required', 401);
    }

    $csrf_header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true) ?: [];
    $csrf = $csrf_header ?: ($payload['csrf_token'] ?? null);
    if (!$csrf || !verify_csrf_token($csrf)) {
        api_error('CSRF_INVALID', 'Invalid CSRF token', 401);
    }

    $booking_id = isset($payload['booking_id']) ? (int)$payload['booking_id'] : null;
    $booking_code = $payload['booking_code'] ?? null;
    $amount = isset($payload['amount']) ? (float)$payload['amount'] : null;
    $payment_method = isset($payload['payment_method']) ? sanitize_input($payload['payment_method']) : 'bank_transfer';
    $transaction_ref = isset($payload['transaction_ref']) ? sanitize_input($payload['transaction_ref']) : null;
    $notes = isset($payload['notes']) ? sanitize_input($payload['notes']) : null;

    $database = new Database();
    $db = $database->getConnection();
    $booking = new Booking($db);

    // Resolve booking
    if ($booking_code) {
        $b = $booking->getByBookingCode($booking_code);
        if (!$b) {
            api_error('NOT_FOUND', 'Booking not found', 404);
        }
        $booking_id = (int)$b['id'];
        if ((int)$b['user_id'] !== (int)$_SESSION['user_id']) {
            api_error('FORBIDDEN', 'Cannot create payment for another user\'s booking', 403);
        }
    } else if ($booking_id) {
        $b = $booking->getById($booking_id);
        if (!$b) {
            api_error('NOT_FOUND', 'Booking not found', 404);
        }
        if ((int)$b['user_id'] !== (int)$_SESSION['user_id']) {
            api_error('FORBIDDEN', 'Cannot create payment for another user\'s booking', 403);
        }
    } else {
        api_error('VALIDATION_ERROR', 'booking_id or booking_code required', 400);
    }

    // Validate basic fields via Payment model
    $payment = new Payment($db);
    $errors = $payment->validate(['amount' => $amount, 'booking_id' => $booking_id]);
    if (!empty($errors)) {
        api_error('VALIDATION_ERROR', 'Validation failed', 400, ['details' => $errors]);
    }

    $payment->booking_id = $booking_id;
    $payment->amount = $amount;
    $payment->payment_method = $payment_method ?: 'bank_transfer';
    $payment->slip_image_url = null; // handled via page upload currently
    $payment->transaction_ref = $transaction_ref;
    $payment->status = 'pending';
    $payment->paid_at = date('Y-m-d H:i:s');
    $payment->notes = $notes;

    if ($payment->create()) {
        log_event("API payment created: id {$payment->id} for booking {$booking_id}", 'INFO');
        api_success([
            'id' => (int)$payment->id,
            'booking_id' => (int)$booking_id,
            'status' => $payment->status,
        ], 201);
    } else {
        api_error('CREATE_FAILED', 'Could not create payment', 500);
    }
} catch (Throwable $e) {
    log_event('API create payment error: ' . $e->getMessage(), 'ERROR');
    api_error('INTERNAL_ERROR', 'Unexpected error', 500);
}

?>

