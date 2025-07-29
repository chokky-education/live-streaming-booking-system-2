<?php
/**
 * Booking Model
 * ระบบจองอุปกรณ์ Live Streaming
 */

class Booking {
    private $conn;
    private $table_name = "bookings";

    public $id;
    public $booking_code;
    public $user_id;
    public $package_id;
    public $booking_date;
    public $start_time;
    public $end_time;
    public $location;
    public $notes;
    public $total_price;
    public $status;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * สร้างการจองใหม่
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET booking_code=:booking_code, user_id=:user_id, package_id=:package_id, 
                      booking_date=:booking_date, start_time=:start_time, end_time=:end_time,
                      location=:location, notes=:notes, total_price=:total_price, status=:status";

        $stmt = $this->conn->prepare($query);

        // Generate unique booking code
        $this->booking_code = $this->generateBookingCode();

        $stmt->bindParam(":booking_code", $this->booking_code);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":package_id", $this->package_id);
        $stmt->bindParam(":booking_date", $this->booking_date);
        $stmt->bindParam(":start_time", $this->start_time);
        $stmt->bindParam(":end_time", $this->end_time);
        $stmt->bindParam(":location", $this->location);
        $stmt->bindParam(":notes", $this->notes);
        $stmt->bindParam(":total_price", $this->total_price);
        $stmt->bindParam(":status", $this->status);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * ดึงการจองตาม ID
     */
    public function getById($id) {
        $query = "SELECT b.*, p.name as package_name, p.price as package_price,
                         u.first_name, u.last_name, u.email, u.phone
                  FROM " . $this->table_name . " b
                  LEFT JOIN packages p ON b.package_id = p.id
                  LEFT JOIN users u ON b.user_id = u.id
                  WHERE b.id = :id 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->id = $row['id'];
            $this->booking_code = $row['booking_code'];
            $this->user_id = $row['user_id'];
            $this->package_id = $row['package_id'];
            $this->booking_date = $row['booking_date'];
            $this->start_time = $row['start_time'];
            $this->end_time = $row['end_time'];
            $this->location = $row['location'];
            $this->notes = $row['notes'];
            $this->total_price = $row['total_price'];
            $this->status = $row['status'];
            $this->created_at = $row['created_at'];
            
            return $row;
        }

        return false;
    }

    /**
     * ดึงการจองตาม Booking Code
     */
    public function getByBookingCode($booking_code) {
        $query = "SELECT b.*, p.name as package_name, p.price as package_price,
                         u.first_name, u.last_name, u.email, u.phone
                  FROM " . $this->table_name . " b
                  LEFT JOIN packages p ON b.package_id = p.id
                  LEFT JOIN users u ON b.user_id = u.id
                  WHERE b.booking_code = :booking_code 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":booking_code", $booking_code);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return false;
    }

    /**
     * ดึงประวัติการจองของผู้ใช้
     */
    public function getUserBookings($user_id, $limit = 10, $offset = 0) {
        $query = "SELECT b.*, p.name as package_name, p.price as package_price
                  FROM " . $this->table_name . " b
                  LEFT JOIN packages p ON b.package_id = p.id
                  WHERE b.user_id = :user_id
                  ORDER BY b.created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ดึงการจองทั้งหมด (สำหรับ admin)
     */
    public function getAllBookings($status = null, $limit = 50, $offset = 0) {
        $where_clause = "";
        if ($status) {
            $where_clause = "WHERE b.status = :status";
        }

        $query = "SELECT b.*, p.name as package_name, p.price as package_price,
                         u.first_name, u.last_name, u.email, u.phone
                  FROM " . $this->table_name . " b
                  LEFT JOIN packages p ON b.package_id = p.id
                  LEFT JOIN users u ON b.user_id = u.id
                  $where_clause
                  ORDER BY b.created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        
        if ($status) {
            $stmt->bindParam(":status", $status);
        }
        
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * อัปเดตสถานะการจอง
     */
    public function updateStatus($status) {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":id", $this->id);

        if($stmt->execute()) {
            $this->status = $status;
            return true;
        }

        return false;
    }

    /**
     * ยกเลิกการจอง
     */
    public function cancel() {
        return $this->updateStatus('cancelled');
    }

    /**
     * ยืนยันการจอง
     */
    public function confirm() {
        return $this->updateStatus('confirmed');
    }

    /**
     * สร้างรหัสการจองที่ไม่ซ้ำ
     */
    private function generateBookingCode() {
        do {
            $code = 'BK' . date('Ymd') . strtoupper(substr(uniqid(), -6));
            
            // ตรวจสอบว่ารหัสซ้ำหรือไม่
            $query = "SELECT id FROM " . $this->table_name . " WHERE booking_code = :code LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":code", $code);
            $stmt->execute();
            
        } while ($stmt->rowCount() > 0);

        return $code;
    }

    /**
     * ตรวจสอบความถูกต้องของข้อมูลการจอง
     */
    public function validate($data) {
        $errors = [];

        // ตรวจสอบแพ็คเกจ
        if(empty($data['package_id'])) {
            $errors[] = "กรุณาเลือกแพ็คเกจ";
        }

        // ตรวจสอบวันที่จอง
        if(empty($data['booking_date'])) {
            $errors[] = "กรุณาเลือกวันที่จอง";
        } else {
            $booking_date = strtotime($data['booking_date']);
            $today = strtotime(date('Y-m-d'));
            
            if($booking_date < $today) {
                $errors[] = "ไม่สามารถจองย้อนหลังได้";
            }
        }

        // ตรวจสอบเวลา
        if(!empty($data['start_time']) && !empty($data['end_time'])) {
            if(strtotime($data['start_time']) >= strtotime($data['end_time'])) {
                $errors[] = "เวลาเริ่มต้องน้อยกว่าเวลาสิ้นสุด";
            }
        }

        // ตรวจสอบสถานที่
        if(empty($data['location'])) {
            $errors[] = "กรุณาระบุสถานที่ใช้งาน";
        }

        return $errors;
    }

    /**
     * ดึงสถิติการจอง
     */
    public function getBookingStatistics() {
        $query = "SELECT 
                    COUNT(*) as total_bookings,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_bookings,
                    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_bookings,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_bookings,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings,
                    SUM(CASE WHEN status = 'confirmed' THEN total_price ELSE 0 END) as total_revenue,
                    AVG(CASE WHEN status = 'confirmed' THEN total_price ELSE NULL END) as avg_booking_value
                  FROM " . $this->table_name;

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ดึงการจองตามช่วงวันที่
     */
    public function getBookingsByDateRange($start_date, $end_date) {
        $query = "SELECT b.*, p.name as package_name, u.first_name, u.last_name
                  FROM " . $this->table_name . " b
                  LEFT JOIN packages p ON b.package_id = p.id
                  LEFT JOIN users u ON b.user_id = u.id
                  WHERE b.booking_date BETWEEN :start_date AND :end_date
                  ORDER BY b.booking_date ASC, b.created_at ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":start_date", $start_date);
        $stmt->bindParam(":end_date", $end_date);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ตรวจสอบความพร้อมของแพ็คเกจในวันที่กำหนด
     */
    public function checkPackageAvailability($package_id, $booking_date) {
        $query = "SELECT COUNT(*) as booking_count 
                  FROM " . $this->table_name . " 
                  WHERE package_id = :package_id 
                  AND booking_date = :booking_date 
                  AND status IN ('pending', 'confirmed')";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":package_id", $package_id);
        $stmt->bindParam(":booking_date", $booking_date);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // สมมติว่าแต่ละแพ็คเกจมีอุปกรณ์ 1 ชุด
        $max_bookings = 1;
        
        return $result['booking_count'] < $max_bookings;
    }
}
?>