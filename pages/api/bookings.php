<?php
/**
 * Create booking via API (POST)
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/Package.php';
require_once __DIR__ . '/../../models/Booking.php';

init_request_id();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('METHOD_NOT_ALLOWED', 'Only POST allowed', 405);
    }

    if (!is_logged_in()) {
        api_error('UNAUTHORIZED', 'Authentication required', 401);
    }

    // CSRF token from header or JSON body
    $csrf_header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = [];
    }
    $csrf_body = $payload['csrf_token'] ?? null;
    $csrf = $csrf_header ?: $csrf_body;
    if (!$csrf || !verify_csrf_token($csrf)) {
        api_error('CSRF_INVALID', 'Invalid CSRF token', 401);
    }

    $booking_data = [
        'package_id'   => isset($payload['package_id']) ? (int)$payload['package_id'] : null,
        'pickup_date'  => $payload['pickup_date'] ?? null,
        'return_date'  => $payload['return_date'] ?? null,
        'pickup_time'  => $payload['pickup_time'] ?? BOOKING_DEFAULT_PICKUP_TIME,
        'return_time'  => $payload['return_time'] ?? BOOKING_DEFAULT_RETURN_TIME,
        'location'     => isset($payload['location']) ? sanitize_input($payload['location']) : null,
        'notes'        => isset($payload['notes']) ? sanitize_input($payload['notes']) : null,
    ];

    $db = get_db_connection();
    $package = new Package($db);
    $booking = new Booking($db);

    // Validate
    $validation_errors = $booking->validate($booking_data);
    if (!empty($validation_errors)) {
        api_error('VALIDATION_ERROR', 'Validation failed', 400, ['details' => $validation_errors]);
    }

    // Availability
    if (!$booking->checkPackageAvailability(
        $booking_data['package_id'],
        $booking_data['pickup_date'],
        $booking_data['return_date']
    )) {
        $booking->logCapacityWarning(
            $booking_data['package_id'],
            $booking_data['pickup_date'],
            $booking_data['return_date'],
            $_SESSION['user_id'] ?? null
        );
        api_error('AVAILABILITY_CONFLICT', 'Package not available for the selected date range', 409);
    }

    // Price
    if (!$package->getById($booking_data['package_id'])) {
        api_error('NOT_FOUND', 'Package not found', 404);
    }
    $pricing = $booking->calculatePricingBreakdown(
        $package->price,
        $booking_data['pickup_date'],
        $booking_data['return_date']
    );
    $subtotal = $pricing['subtotal'];
    $vat_amount = $subtotal * VAT_RATE;
    $total_price = $subtotal + $vat_amount;

    // Create booking
    $booking->user_id = $_SESSION['user_id'];
    $booking->package_id = $booking_data['package_id'];
    $booking->pickup_date = $booking_data['pickup_date'];
    $booking->return_date = $booking_data['return_date'];
    $booking->pickup_time = $booking_data['pickup_time'];
    $booking->return_time = $booking_data['return_time'];
    $booking->location = $booking_data['location'];
    $booking->notes = $booking_data['notes'];
    $booking->total_price = $total_price;
    $booking->status = 'pending';

    if ($booking->create()) {
        log_event("API booking created: {$booking->booking_code}", 'INFO');
        api_success([
            'id' => (int)$booking->id,
            'booking_code' => $booking->booking_code,
            'rental_days' => $pricing['rental_days'],
            'pickup_date' => $booking->pickup_date,
            'return_date' => $booking->return_date,
            'pickup_time' => $booking->pickup_time,
            'return_time' => $booking->return_time,
            'total_price' => $booking->total_price,
            'pricing_breakdown' => [
                'base_day' => $pricing['base_day'],
                'day2_surcharge' => $pricing['day2_surcharge'],
                'day3_6_surcharge' => $pricing['day3_6_surcharge'],
                'day7_plus_surcharge' => $pricing['day7_plus_surcharge'],
                'weekend_holiday_surcharge' => $pricing['weekend_holiday_surcharge'],
                'subtotal' => $subtotal,
                'vat' => $vat_amount,
            ],
            'status' => $booking->status,
        ], 201);
    } else {
        if ($booking->error_code === 'capacity_conflict') {
            api_error('AVAILABILITY_CONFLICT', 'Package not available for the selected date range', 409);
        }
        api_error('CREATE_FAILED', 'Could not create booking', 500);
    }
} catch (Throwable $e) {
    log_event('API create booking error: ' . $e->getMessage(), 'ERROR');
    api_error('INTERNAL_ERROR', 'Unexpected error', 500);
}

?>
