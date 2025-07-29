<?php
/**
 * Main Configuration File
 * ระบบจองอุปกรณ์ Live Streaming
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');

// Package prices (in Baht)
define('BASIC_PACKAGE_PRICE', 2500);
define('STANDARD_PACKAGE_PRICE', 4500);
define('PREMIUM_PACKAGE_PRICE', 7500);
define('VAT_RATE', 0.07);

// Helper functions
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generate_csrf_token() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verify_csrf_token($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function is_admin() {
    return is_logged_in() && $_SESSION['role'] === 'admin';
}

function require_login() {
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function require_admin() {
    if (!is_admin()) {
        redirect('index.php');
    }
}
?>