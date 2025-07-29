<?php
/**
 * Database Configuration Example
 * ระบบจองอุปกรณ์ Live Streaming
 * 
 * คัดลอกไฟล์นี้เป็น database.php และแก้ไขค่าการเชื่อมต่อให้ถูกต้อง
 */

class Database {
    private $host = 'localhost';           // เปลี่ยนเป็น host ของคุณ
    private $db_name = 'live_streaming_booking';  // ชื่อฐานข้อมูล
    private $username = 'root';            // username ฐานข้อมูล
    private $password = '';                // password ฐานข้อมูล
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
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