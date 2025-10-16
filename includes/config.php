<?php
/**
 * Main Configuration File
 * ระบบจองอุปกรณ์ Live Streaming
 */

// Configure secure session cookie params before session_start
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
if (session_status() === PHP_SESSION_NONE) {
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        // Best-effort for older PHP versions
        session_set_cookie_params(0, '/; samesite=Lax', '', $isHttps, true);
    }
    session_start();
}

// Error reporting (hide display in non-development)
error_reporting(E_ALL);
$app_env = getenv('APP_ENV') ?: 'development';
ini_set('display_errors', $app_env === 'development' ? '1' : '0');

// Timezone
date_default_timezone_set('Asia/Bangkok');

// Site configuration
define('SITE_NAME', 'ระบบจองอุปกรณ์ Live Streaming');
define('SITE_URL', 'http://localhost');
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Database configuration
require_once __DIR__ . '/../config/database.php';

// Security settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600); // 1 hour

// Email configuration
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ? (int)getenv('SMTP_PORT') : 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'your-email@gmail.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'your-app-password');

// Optional integrations
define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: '');

// Package prices (in Baht)
define('BASIC_PACKAGE_PRICE', 2500);
define('STANDARD_PACKAGE_PRICE', 4500);
define('PREMIUM_PACKAGE_PRICE', 7500);
define('VAT_RATE', 0.07);

// Booking defaults & pricing rules
define('BOOKING_DEFAULT_PICKUP_TIME', getenv('BOOKING_DEFAULT_PICKUP_TIME') ?: '09:00');
define('BOOKING_DEFAULT_RETURN_TIME', getenv('BOOKING_DEFAULT_RETURN_TIME') ?: '18:00');
define('BOOKING_SURCHARGE_DAY2', 0.40);
define('BOOKING_SURCHARGE_DAY3_TO6', 0.20);
define('BOOKING_SURCHARGE_DAY7_PLUS', 0.10);
define('BOOKING_WEEKEND_HOLIDAY_SURCHARGE', 0.10);
define('BOOKING_EARLY_PICKUP_THRESHOLD', getenv('BOOKING_EARLY_PICKUP_THRESHOLD') ?: '12:00');
define('BOOKING_LATE_RETURN_THRESHOLD', getenv('BOOKING_LATE_RETURN_THRESHOLD') ?: '18:00');

// Ledger maintenance defaults
define('LEDGER_CLEANUP_RETENTION_DAYS', getenv('LEDGER_RETENTION_DAYS') ? (int)getenv('LEDGER_RETENTION_DAYS') : 30);

// Caching
define('CACHE_PATH', __DIR__ . '/../cache/');
define('AVAILABILITY_CACHE_TTL', getenv('AVAILABILITY_CACHE_TTL') ? (int)getenv('AVAILABILITY_CACHE_TTL') : 120);



// Session timeout handling (rolling)
if (isset($_SESSION)) {
    $now = time();
    if (isset($_SESSION['LAST_ACTIVITY']) && ($now - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
        // Session expired
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['LAST_ACTIVITY'] = $now;
}
?>
