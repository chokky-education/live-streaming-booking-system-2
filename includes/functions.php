<?php
/**
 * Common Functions
 * ระบบจองอุปกรณ์ Live Streaming
 */

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
    $thai_months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    
    $timestamp = strtotime($date);
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
function calculate_total_with_vat($base_price, $vat_rate = VAT_RATE) {
    return $base_price * (1 + $vat_rate);
}

/**
 * Get package information
 */
function get_package_info($package_id) {
    $packages = [
        1 => [
            'name' => 'แพ็คเกจพื้นฐาน (Basic Package)',
            'price' => BASIC_PACKAGE_PRICE,
            'equipment' => [
                'กล้อง DSLR/Mirrorless 1 ตัว',
                'ไมโครโฟน 1 ตัว'
            ]
        ],
        2 => [
            'name' => 'แพ็คเกจมาตรฐาน (Standard Package)',
            'price' => STANDARD_PACKAGE_PRICE,
            'equipment' => [
                'กล้อง DSLR/Mirrorless 2 ตัว',
                'ไมโครโฟน 2 ตัว',
                'ไฟ LED 2 ชุด'
            ]
        ],
        3 => [
            'name' => 'แพ็คเกจพรีเมี่ยม (Premium Package)',
            'price' => PREMIUM_PACKAGE_PRICE,
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
    $log_dir = __DIR__ . '/../logs';
    $log_file = $log_dir . '/system.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
    
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
 * Validate file upload
 */
function validate_file_upload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf']) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 5MB)'];
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'ประเภทไฟล์ไม่ถูกต้อง (อนุญาต: ' . implode(', ', $allowed_types) . ')'];
    }
    
    return ['success' => true];
}
?>