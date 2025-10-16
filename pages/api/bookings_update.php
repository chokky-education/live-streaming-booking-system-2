<?php
/**
 * Update or cancel an existing booking by the authenticated customer
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/Booking.php';

init_request_id();

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['PATCH', 'DELETE'], true)) {
        api_error('METHOD_NOT_ALLOWED', 'PATCH or DELETE required', 405);
    }

    if (!is_logged_in()) {
        api_error('UNAUTHORIZED', 'Authentication required', 401);
    }

    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = [];
    }
    $csrf = $csrfHeader ?: ($payload['csrf_token'] ?? null);
    if (!$csrf || !verify_csrf_token($csrf)) {
        api_error('CSRF_INVALID', 'Invalid CSRF token', 401);
    }

    $db = get_db_connection();
    $bookingModel = new Booking($db);

    $bookingId = isset($payload['booking_id']) ? (int)$payload['booking_id'] : null;
    $bookingCode = isset($payload['booking_code']) ? trim($payload['booking_code']) : null;

    if (!$bookingId && $bookingCode) {
        $row = $bookingModel->getByBookingCode($bookingCode);
        if ($row) {
            $bookingId = (int)$row['id'];
        }
    }

    if (!$bookingId) {
        api_error('VALIDATION_ERROR', 'booking_id or booking_code is required', 400);
    }

    $userId = (int)$_SESSION['user_id'];
    $canModify = $bookingModel->customerCanModify($bookingId, $userId);
    if (!$canModify['allowed']) {
        api_error('FORBIDDEN', $canModify['reason'] ?? 'Action not permitted', 403);
    }

    if ($method === 'PATCH') {
        $allowedFields = ['pickup_date', 'return_date', 'pickup_time', 'return_time', 'location', 'notes'];
        $updates = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $payload)) {
                $updates[$field] = $payload[$field];
            }
        }

        if (empty($updates)) {
            api_error('VALIDATION_ERROR', 'No fields provided for update', 400);
        }

        $result = $bookingModel->updateByCustomer($bookingId, $userId, $updates);
        if (!$result['success']) {
            api_error('UPDATE_FAILED', $result['message'] ?? 'Could not update booking', 400);
        }

        api_success([
            'booking' => $result['booking'],
        ]);
    } else {
        $reason = $payload['reason'] ?? null;
        $result = $bookingModel->cancelByCustomer($bookingId, $userId, $reason);
        if (!$result['success']) {
            api_error('CANCEL_FAILED', $result['message'] ?? 'Could not cancel booking', 400);
        }

        api_success([
            'message' => $result['message'],
        ]);
    }
} catch (Throwable $e) {
    log_event('API bookings_update error: ' . $e->getMessage(), 'ERROR');
    api_error('INTERNAL_ERROR', 'Unexpected error', 500);
}

?>
