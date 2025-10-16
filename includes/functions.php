<?php
/**
 * Common Functions
 * ระบบจองอุปกรณ์ Live Streaming
 */

// Load configuration for constants
require_once __DIR__ . '/../config/config.php';

/**
 * Format currency in Thai Baht
 */
function format_currency($amount) {
    return number_format($amount, 2) . ' บาท';
}

/**
 * Format Thai date
 */
function format_thai_date($date) {
    if (empty($date)) {
        return '-';
    }

    $thai_months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '-';
    }

    $day = date('j', $timestamp);
    $month = $thai_months[date('n', $timestamp)];
    $year = date('Y', $timestamp) + 543; // Convert to Buddhist year

    return "$day $month $year";
}

/**
 * Generate unique booking code
 */
function generate_booking_code() {
    return 'BK' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

/**
 * Validate email format
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate phone number (Thai format)
 */
function validate_phone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return preg_match('/^0[0-9]{8,9}$/', $phone);
}

/**
 * Calculate total price with VAT
 */
function calculate_total_with_vat($base_price, $vat_rate = null) {
    if ($vat_rate === null) {
        $config = Config::getInstance();
        $vat_rate = $config->get('pricing.vat_rate');
    }
    return $base_price * (1 + $vat_rate);
}

/**
 * Get package information
 */
function get_package_info($package_id) {
    $config = Config::getInstance();
    $packages = [
        1 => [
            'name' => 'แพ็คเกจพื้นฐาน (Basic Package)',
            'price' => $config->get('pricing.basic_package'),
            'equipment' => [
                'กล้อง DSLR/Mirrorless 1 ตัว',
                'ไมโครโฟน 1 ตัว'
            ]
        ],
        2 => [
            'name' => 'แพ็คเกจมาตรฐาน (Standard Package)',
            'price' => $config->get('pricing.standard_package'),
            'equipment' => [
                'กล้อง DSLR/Mirrorless 2 ตัว',
                'ไมโครโฟน 2 ตัว',
                'ไฟ LED 2 ชุด'
            ]
        ],
        3 => [
            'name' => 'แพ็คเกจพรีเมี่ยม (Premium Package)',
            'price' => $config->get('pricing.premium_package'),
            'equipment' => [
                'กล้อง DSLR/Mirrorless 3 ตัว',
                'ไมโครโฟน 3 ตัว',
                'ไฟ LED 4 ชุด',
                'ขาตั้งกล้อง 3 ชุด',
                'Switcher/Mixer 1 ตัว'
            ]
        ]
    ];

    return isset($packages[$package_id]) ? $packages[$package_id] : null;
}

/**
 * Log system events
 */
function log_event($message, $level = 'INFO') {
    if (!isset($GLOBALS['REQUEST_ID'])) {
        init_request_id();
    }
    $log_dir = __DIR__ . '/../logs';
    $log_file = $log_dir . '/system.log';
    $timestamp = date('Y-m-d H:i:s');
    $rid = isset($GLOBALS['REQUEST_ID']) ? $GLOBALS['REQUEST_ID'] : null;
    $rid_part = $rid ? " [RID:$rid]" : '';
    $log_entry = "[$timestamp] [$level]$rid_part $message" . PHP_EOL;

    // Create logs directory if it doesn't exist
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Send JSON response
 */
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * API success response helper with standard envelope
 */
function api_success($data = [], $status_code = 200) {
    json_response([
        'success' => true,
        'data' => $data,
    ], $status_code);
}

/**
 * API error response helper with standard envelope
 */
function api_error($code, $message, $status_code = 400, $extra = null) {
    $error = ['code' => $code, 'message' => $message];
    if (is_array($extra) && !empty($extra)) {
        $error = array_merge($error, $extra);
    }
    json_response([
        'success' => false,
        'error' => $error,
    ], $status_code);
}

/**
 * Initialize a per-request ID for logging correlation
 */
function init_request_id() {
    if (!isset($GLOBALS['REQUEST_ID'])) {
        try {
            $GLOBALS['REQUEST_ID'] = bin2hex(random_bytes(8));
        } catch (Exception $e) {
            $GLOBALS['REQUEST_ID'] = substr(uniqid('', true), -12);
        }
    }
    return $GLOBALS['REQUEST_ID'];
}

/**
 * Validate file upload
 */
function validate_file_upload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'], $allowed_mimes = null) {
    $config = Config::getInstance();
    $max_file_size = $config->get('site.max_file_size');

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์'];
    }

    if ($file['size'] > $max_file_size) {
        $max_size_mb = round($max_file_size / (1024 * 1024), 1);
        return ['success' => false, 'message' => "ไฟล์มีขนาดใหญ่เกินไป (สูงสุด {$max_size_mb}MB)"];
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'ประเภทไฟล์ไม่ถูกต้อง (อนุญาต: ' . implode(', ', $allowed_types) . ')'];
    }

    // MIME validation using finfo
    if ($allowed_mimes === null) {
        $allowed_mimes = [
            'image/jpeg',
            'image/png',
            'application/pdf'
        ];
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, $allowed_mimes)) {
                return ['success' => false, 'message' => 'ประเภทไฟล์ไม่ถูกต้อง'];
            }
        }
    }

    return ['success' => true];
}

/**
 * Cache functions
 */
function ensure_cache_directory() {
    $config = Config::getInstance();
    $cache_path = $config->get('cache.path');
    if (!is_dir($cache_path)) {
        @mkdir($cache_path, 0775, true);
    }
}

function cache_file_path($key) {
    $config = Config::getInstance();
    $cache_path = $config->get('cache.path');
    return rtrim($cache_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $key . '.cache';
}

function cache_get($key) {
    $file = cache_file_path($key);
    if (!is_file($file)) {
        return null;
    }
    $data = @json_decode(@file_get_contents($file), true);
    if (!is_array($data) || !isset($data['expires_at'])) {
        @unlink($file);
        return null;
    }
    if ($data['expires_at'] < time()) {
        @unlink($file);
        return null;
    }
    return $data['payload'];
}

function cache_set($key, $payload, $ttl) {
    ensure_cache_directory();
    $file = cache_file_path($key);
    $data = [
        'expires_at' => time() + max(0, (int)$ttl),
        'payload' => $payload,
    ];
    @file_put_contents($file, json_encode($data));
}

function cache_delete_prefix($prefix) {
    $config = Config::getInstance();
    $cache_path = $config->get('cache.path');
    if (!is_dir($cache_path)) {
        return;
    }
    $pattern = rtrim($cache_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $prefix . '*.cache';
    foreach (glob($pattern) ?: [] as $file) {
        @unlink($file);
    }
}

function availability_cache_key($packageId, $startDate, $endDate) {
    return sprintf('availability_%d_%s_%s', (int)$packageId, $startDate, $endDate);
}

function availability_cache_invalidate($packageId) {
    cache_delete_prefix(sprintf('availability_%d_', (int)$packageId));
}

/**
 * Security functions
 */
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generate_csrf_token() {
    $config = Config::getInstance();
    $token_name = $config->get('security.csrf_token_name');

    if (!isset($_SESSION[$token_name])) {
        $_SESSION[$token_name] = bin2hex(random_bytes(32));
    }
    return $_SESSION[$token_name];
}

function verify_csrf_token($token) {
    $config = Config::getInstance();
    $token_name = $config->get('security.csrf_token_name');

    return isset($_SESSION[$token_name]) && hash_equals($_SESSION[$token_name], $token);
}

/**
 * Authentication functions
 */
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

/**
 * Utility functions
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Database helper function
 */
function get_db_connection() {
    try {
        return Database::getInstance()->getConnection();
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        throw new Exception("Database connection failed");
    }
}
?>
