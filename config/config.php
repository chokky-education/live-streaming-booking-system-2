<?php
/**
 * Configuration Loader
 * ระบบจองอุปกรณ์ Live Streaming
 *
 * This file centralizes all configuration loading and provides
 * a consistent interface for accessing configuration values.
 */

class Config {
    private static $instance = null;
    private $config = [];

    private function __construct() {
        $this->loadConfig();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadConfig() {
        // Environment settings
        $this->config['app_env'] = getenv('APP_ENV') ?: 'development';
        $this->config['is_https'] = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                                   (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

        // Database configuration
        $this->config['database'] = [
            'host' => getenv('DB_HOST') ?: 'localhost',
            'name' => getenv('DB_NAME') ?: 'live_streaming_booking',
            'user' => getenv('DB_USER') ?: 'root',
            'pass' => getenv('DB_PASS') ?: '',
            'charset' => getenv('DB_CHARSET') ?: 'utf8mb4'
        ];

        // Site configuration
        $this->config['site'] = [
            'name' => 'ระบบจองอุปกรณ์ Live Streaming',
            'url' => 'http://localhost',
            'upload_path' => 'uploads/',
            'max_file_size' => 5 * 1024 * 1024, // 5MB
            'timezone' => 'Asia/Bangkok'
        ];

        // Security settings
        $this->config['security'] = [
            'csrf_token_name' => 'csrf_token',
            'session_timeout' => 3600, // 1 hour
            'cookie_secure' => $this->config['is_https'],
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax'
        ];

        // Email configuration
        $this->config['email'] = [
            'smtp_host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
            'smtp_port' => getenv('SMTP_PORT') ? (int)getenv('SMTP_PORT') : 587,
            'smtp_username' => getenv('SMTP_USERNAME') ?: 'your-email@gmail.com',
            'smtp_password' => getenv('SMTP_PASSWORD') ?: 'your-app-password'
        ];

        // Package pricing
        $this->config['pricing'] = [
            'basic_package' => 2500,
            'standard_package' => 4500,
            'premium_package' => 7500,
            'vat_rate' => 0.07
        ];

        // Booking configuration
        $this->config['booking'] = [
            'default_pickup_time' => getenv('BOOKING_DEFAULT_PICKUP_TIME') ?: '09:00',
            'default_return_time' => getenv('BOOKING_DEFAULT_RETURN_TIME') ?: '18:00',
            'surcharge_day2' => 0.40,
            'surcharge_day3_to6' => 0.20,
            'surcharge_day7_plus' => 0.10,
            'weekend_holiday_surcharge' => 0.10,
            'early_pickup_threshold' => getenv('BOOKING_EARLY_PICKUP_THRESHOLD') ?: '12:00',
            'late_return_threshold' => getenv('BOOKING_LATE_RETURN_THRESHOLD') ?: '18:00'
        ];

        // Maintenance
        $this->config['maintenance'] = [
            'ledger_cleanup_retention_days' => getenv('LEDGER_RETENTION_DAYS') ? (int)getenv('LEDGER_RETENTION_DAYS') : 30
        ];

        // Caching
        $this->config['cache'] = [
            'path' => __DIR__ . '/../cache/',
            'availability_ttl' => getenv('AVAILABILITY_CACHE_TTL') ? (int)getenv('AVAILABILITY_CACHE_TTL') : 120
        ];

        // Optional integrations
        $this->config['integrations'] = [
            'google_maps_api_key' => getenv('GOOGLE_MAPS_API_KEY') ?: ''
        ];
    }

    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }

        return $value;
    }

    public function set($key, $value) {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    public function getAll() {
        return $this->config;
    }
}

/**
 * Global configuration functions for backward compatibility
 */
function config($key, $default = null) {
    return Config::getInstance()->get($key, $default);
}

function get_configured_holidays() {
    $raw = getenv('BOOKING_HOLIDAYS');
    if ($raw) {
        $holidays = array_filter(array_map('trim', explode(',', $raw)));
        return array_unique($holidays);
    }
    return [];
}
?>