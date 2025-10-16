<?php
/**
 * List bookings for the authenticated user
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/Booking.php';

init_request_id();

try {
    if (!is_logged_in()) {
        api_error('UNAUTHORIZED', 'Authentication required', 401);
    }

    $database = new Database();
    $db = $database->getConnection();
    $booking = new Booking($db);
    $list = $booking->getUserBookings($_SESSION['user_id'], 50, 0);
    api_success(['bookings' => $list]);
} catch (Throwable $e) {
    log_event('API my bookings error: ' . $e->getMessage(), 'ERROR');
    api_error('INTERNAL_ERROR', 'Unexpected error', 500);
}

?>

