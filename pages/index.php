<?php
/**
 * Pages Router
 * ระบบจองอุปกรณ์ Live Streaming
 *
 * This file routes requests to the appropriate pages
 */

// Load bootstrap
require_once __DIR__ . '/../includes/bootstrap.php';

// Get the request path
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];
$path = str_replace(dirname($script_name), '', $request_uri);
$path = trim($path, '/');

// Simple routing
if (empty($path) || $path === 'index.php') {
    // Default to booking page
    require_once __DIR__ . '/web/booking.php';
    exit;
}

// Route to web pages
$web_pages = [
    'booking' => 'web/booking.php',
    'login' => 'web/login.php',
    'logout' => 'web/logout.php',
    'payment' => 'web/payment.php',
    'profile' => 'web/profile.php',
    'register' => 'web/register.php'
];

// Route to API endpoints
$api_endpoints = [
    'api/availability' => 'api/availability.php',
    'api/bookings' => 'api/bookings.php',
    'api/bookings/me' => 'api/bookings_me.php',
    'api/bookings/update' => 'api/bookings_update.php',
    'api/health' => 'api/health.php',
    'api/packages' => 'api/packages.php',
    'api/payments' => 'api/payments.php',
    'api/payments/update' => 'api/payments_update.php'
];

// Check if path matches a web page
if (isset($web_pages[$path])) {
    require_once __DIR__ . '/' . $web_pages[$path];
    exit;
}

// Check if path matches an API endpoint
foreach ($api_endpoints as $route => $file) {
    if ($path === $route || strpos($path, $route . '/') === 0) {
        require_once __DIR__ . '/' . $file;
        exit;
    }
}

// Check for admin pages
if (strpos($path, 'admin/') === 0) {
    $admin_page = substr($path, 6); // Remove 'admin/'
    $admin_file = __DIR__ . '/admin/' . $admin_page . '.php';

    if (file_exists($admin_file)) {
        require_once $admin_file;
        exit;
    }
}

// 404 - Page not found
header('HTTP/1.0 404 Not Found');
echo '<h1>404 - Page Not Found</h1>';
echo '<p>The page you are looking for does not exist.</p>';
echo '<p><a href="/">Go to Home</a></p>';
exit;
?>