<?php
/**
 * Database Configuration
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        $config = Config::getInstance();

        try {
            $this->conn = new PDO(
                "mysql:host=" . $config->get('database.host') .
                ";dbname=" . $config->get('database.name') .
                ";charset=" . $config->get('database.charset'),
                $config->get('database.user'),
                $config->get('database.pass'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    /**
     * Legacy method for backward compatibility
     */
    public function getLegacyConnection() {
        $this->conn = null;
        $config = Config::getInstance();

        try {
            $this->conn = new PDO(
                "mysql:host=" . $config->get('database.host') .
                ";dbname=" . $config->get('database.name') .
                ";charset=" . $config->get('database.charset'),
                $config->get('database.user'),
                $config->get('database.pass'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed");
        }

        return $this->conn;
    }
}
?>
