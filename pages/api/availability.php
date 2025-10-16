<?php
/**
 * Availability lookup endpoint
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/Booking.php';
require_once __DIR__ . '/../../models/Package.php';

init_request_id();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        api_error('METHOD_NOT_ALLOWED', 'Only GET allowed', 405);
    }

    $package_id = isset($_GET['package_id']) ? (int)$_GET['package_id'] : null;
    if (!$package_id) {
        api_error('VALIDATION_ERROR', 'package_id is required', 400);
    }

    $today = new DateTime('today');
    $default_end = (clone $today)->modify('+60 days');

    $start_param = $_GET['start'] ?? $today->format('Y-m-d');
    $end_param = $_GET['end'] ?? $default_end->format('Y-m-d');

    $start = DateTime::createFromFormat('Y-m-d', $start_param);
    $end = DateTime::createFromFormat('Y-m-d', $end_param);

    if (!$start || !$end) {
        api_error('VALIDATION_ERROR', 'Invalid date format (use YYYY-MM-DD)', 400);
    }

    if ($end < $start) {
        api_error('VALIDATION_ERROR', 'end date must be after start date', 400);
    }

    $startStr = $start->format('Y-m-d');
    $endStr = $end->format('Y-m-d');
    $cacheKey = availability_cache_key($package_id, $startStr, $endStr);

    if (!isset($_GET['fresh'])) {
        $cached = cache_get($cacheKey);
        if ($cached !== null) {
            if (isset($cached['cache'])) {
                $cached['cache']['fresh'] = false;
            }
            api_success($cached);
        }
    }

    $db = get_db_connection();
    $booking = new Booking($db);
    $packageModel = new Package($db);
    $capacity = 1;
    if ($packageModel->getById($package_id)) {
        $capacity = max(1, (int)$packageModel->max_concurrent_reservations);
    }

    $records = $booking->getAvailabilityWindow(
        $package_id,
        $startStr,
        $endStr
    );

    $usage = [];
    foreach ($records as $row) {
        if (in_array($row['status'], ['reserved', 'picked_up', 'maintenance'], true)) {
            $usage[$row['date']] = ($usage[$row['date']] ?? 0) + 1;
        }
    }

    $response = [
        'package_id' => $package_id,
        'capacity' => $capacity,
        'window' => [
            'start' => $startStr,
            'end' => $endStr,
        ],
        'reservations' => array_map(function ($row) {
            return [
                'date' => $row['date'],
                'status' => $row['status'],
                'booking_id' => $row['booking_id'] ? (int)$row['booking_id'] : null,
                'booking_code' => $row['booking_code'] ?? null,
            ];
        }, $records),
        'usage' => $usage,
        'cache' => [
            'ttl_seconds' => AVAILABILITY_CACHE_TTL,
            'fresh' => true,
        ],
    ];

    if (AVAILABILITY_CACHE_TTL > 0) {
        cache_set($cacheKey, $response, AVAILABILITY_CACHE_TTL);
    }

    api_success($response);
} catch (Throwable $e) {
    log_event('API availability error: ' . $e->getMessage(), 'ERROR');
    api_error('INTERNAL_ERROR', 'Unexpected error', 500);
}

?>
