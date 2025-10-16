<?php
// PHPUnit bootstrap for Live Streaming Booking System

// Ensure predictable environment for tests
putenv('APP_ENV=testing');
// Prevent headers/session cookie issues by faking HTTPS off
$_SERVER['HTTPS'] = 'off';

// Load app configuration and helpers (no DB connection on load)
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/functions.php';

// Load models used by tests (autoload also set in composer.json)
require_once __DIR__ . '/../models/Booking.php';

// Helper: reset holiday env for deterministic tests when needed
function __set_booking_holidays_env(?string $csvDates): void {
    if ($csvDates === null) {
        putenv('BOOKING_HOLIDAYS'); // unset
    } else {
        putenv('BOOKING_HOLIDAYS=' . $csvDates);
    }
}

