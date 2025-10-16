<?php
/**
 * Payment Model
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once __DIR__ . '/BaseModel.php';

class Payment extends BaseModel {
    protected $table_name = "payments";

    public $id;
    public $booking_id;
    public $amount;
    public $payment_method;
    public $slip_image_url;
    public $transaction_ref;
    public $status;
    public $paid_at;
    public $verified_at;
    public $verified_by;
    public $notes;
    public $created_at;

    // Constructor uses parent
    public function __construct($db) {
        parent::__construct($db);
    }

    /**
     * สร้างการชำระเงินใหม่
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET booking_id=:booking_id, amount=:amount, payment_method=:payment_method,
                      slip_image_url=:slip_image_url, transaction_ref=:transaction_ref, 
                      status=:status, paid_at=:paid_at, notes=:notes";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":booking_id", $this->booking_id);
        $stmt->bindParam(":amount", $this->amount);
        $stmt->bindParam(":payment_method", $this->payment_method);
        $stmt->bindParam(":slip_image_url", $this->slip_image_url);
        $stmt->bindParam(":transaction_ref", $this->transaction_ref);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":paid_at", $this->paid_at);
        $stmt->bindParam(":notes", $this->notes);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * ดึงข้อมูลการชำระเงินตาม booking_id
     */
    public function getByBookingId($booking_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE booking_id = :booking_id 
                  ORDER BY created_at DESC 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":booking_id", $booking_id);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->id = $row['id'];
            $this->booking_id = $row['booking_id'];
            $this->amount = $row['amount'];
            $this->payment_method = $row['payment_method'];
            $this->slip_image_url = $row['slip_image_url'];
            $this->transaction_ref = $row['transaction_ref'];
            $this->status = $row['status'];
            $this->paid_at = $row['paid_at'];
            $this->verified_at = $row['verified_at'];
            $this->verified_by = $row['verified_by'];
            $this->notes = $row['notes'];
            $this->created_at = $row['created_at'];
            
            return $row;
        }

        return false;
    }

    /**
     * ดึงข้อมูลการชำระเงินตาม ID
     */
    public function getById($id) {
        $query = "SELECT p.*, b.booking_code, b.total_price as booking_total,
                         u.first_name, u.last_name, u.email, u.phone,
                         pkg.name as package_name
                  FROM " . $this->table_name . " p
                  LEFT JOIN bookings b ON p.booking_id = b.id
                  LEFT JOIN users u ON b.user_id = u.id
                  LEFT JOIN packages pkg ON b.package_id = pkg.id
                  WHERE p.id = :id 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return false;
    }

    /**
     * อัปเดตสถานะการชำระเงิน
     */
    public function updateStatus($status, $verified_by = null, $notes = null) {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status, verified_at = :verified_at, 
                      verified_by = :verified_by, notes = :notes 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        
        $verified_at = ($status === 'verified') ? date('Y-m-d H:i:s') : null;
        
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":verified_at", $verified_at);
        $stmt->bindParam(":verified_by", $verified_by);
        $stmt->bindParam(":notes", $notes);
        $stmt->bindParam(":id", $this->id);

        if($stmt->execute()) {
            $this->status = $status;
            $this->verified_at = $verified_at;
            $this->verified_by = $verified_by;
            $this->notes = $notes;
            return true;
        }

        return false;
    }

    /**
     * ยืนยันการชำระเงิน
     */
    public function verify($verified_by, $notes = null) {
        return $this->updateStatus('verified', $verified_by, $notes);
    }

    /**
     * ปฏิเสธการชำระเงิน
     */
    public function reject($verified_by, $notes = null) {
        return $this->updateStatus('rejected', $verified_by, $notes);
    }

    /**
     * อัปโหลดสลิปการโอนเงิน
     */
    public function uploadSlip($file, $old_slip_url = null) {
        // ตรวจสอบไฟล์
        $validation = $this->validateSlipUpload($file);
        if (!$validation['success']) {
            return $validation;
        }

        // สร้างชื่อไฟล์ใหม่แบบสุ่ม
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        try {
            $rand = bin2hex(random_bytes(16));
        } catch (Exception $e) {
            $rand = uniqid('slip_', true);
        }
        $new_filename = $rand . '.' . $file_extension;
        // ใช้ path แบบ absolute เพื่อให้ทำงานได้จากทุกหน้า
        $base_dir = dirname(__DIR__); // project root
        $upload_path = $base_dir . '/uploads/slips/';
        
        // สร้างโฟลเดอร์ถ้ายังไม่มี
        if (!file_exists($upload_path)) {
            mkdir($upload_path, 0755, true);
        }
        
        $full_path = $upload_path . $new_filename;
        
        // อัปโหลดไฟล์
        $moved = move_uploaded_file($file['tmp_name'], $full_path);
        if (!$moved && PHP_SAPI === 'cli') {
            // ใน CLI (ใช้กับ automated tests) move_uploaded_file จะล้มเหลวเพราะไฟล์ไม่ได้อัปโหลดผ่าน HTTP
            // อนุญาต fallback เป็น rename เพื่อรองรับการทดสอบอัตโนมัติ โดยยังใช้เส้นทางปลายทางเดียวกัน
            $moved = rename($file['tmp_name'], $full_path);
        }

        if ($moved) {
            // ลบไฟล์เก่า (ถ้ามีและอยู่ในโฟลเดอร์ที่กำหนด)
            if ($old_slip_url) {
                $old_path = $base_dir . '/' . ltrim($old_slip_url, '/');
                if (strpos(realpath($old_path) ?: '', realpath($upload_path)) === 0 && file_exists($old_path)) {
                    @unlink($old_path);
                }
            }
            $this->slip_image_url = 'uploads/slips/' . $new_filename;
            return ['success' => true, 'filename' => $new_filename];
        } else {
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์'];
        }
    }

    /**
     * ตรวจสอบไฟล์สลิปที่อัปโหลด
     */
    public function validateSlipUpload($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์'];
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 5MB)'];
        }
        
        $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_types)) {
            return ['success' => false, 'message' => 'ประเภทไฟล์ไม่ถูกต้อง (อนุญาต: JPG, PNG, PDF)'];
        }
        
        // ตรวจสอบ MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = [
            'image/jpeg',
            'image/png',
            'application/pdf'
        ];
        
        if (!in_array($mime_type, $allowed_mimes)) {
            return ['success' => false, 'message' => 'ประเภทไฟล์ไม่ถูกต้อง'];
        }
        
        return ['success' => true];
    }

    /**
     * ดึงรายการการชำระเงินทั้งหมด (สำหรับ admin)
     */
    public function getAllPayments($status = null, $limit = 50, $offset = 0) {
        $where_clause = "";
        if ($status) {
            $where_clause = "WHERE p.status = :status";
        }

        $query = "SELECT p.*, b.booking_code, b.total_price as booking_total,
                         u.first_name, u.last_name, u.email,
                         pkg.name as package_name,
                         v.username as verified_by_username
                  FROM " . $this->table_name . " p
                  LEFT JOIN bookings b ON p.booking_id = b.id
                  LEFT JOIN users u ON b.user_id = u.id
                  LEFT JOIN packages pkg ON b.package_id = pkg.id
                  LEFT JOIN users v ON p.verified_by = v.id
                  $where_clause
                  ORDER BY p.created_at DESC
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
     * ดึงสถิติการชำระเงิน
     */
    public function getPaymentStatistics() {
        $query = "SELECT 
                    COUNT(*) as total_payments,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_payments,
                    COUNT(CASE WHEN status = 'verified' THEN 1 END) as verified_payments,
                    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_payments,
                    SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END) as total_verified_amount,
                    AVG(CASE WHEN status = 'verified' THEN amount ELSE NULL END) as avg_payment_amount
                  FROM " . $this->table_name;

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ดึงการชำระเงินที่รอการตรวจสอบ
     */
    public function getPendingPayments($limit = 20) {
        $query = "SELECT p.*, b.booking_code, b.total_price as booking_total,
                         u.first_name, u.last_name, u.email,
                         pkg.name as package_name
                  FROM " . $this->table_name . " p
                  LEFT JOIN bookings b ON p.booking_id = b.id
                  LEFT JOIN users u ON b.user_id = u.id
                  LEFT JOIN packages pkg ON b.package_id = pkg.id
                  WHERE p.status = 'pending'
                  ORDER BY p.created_at ASC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * คำนวณยอดรวมรายได้
     */
    public function getTotalRevenue($start_date = null, $end_date = null) {
        $where_clause = "WHERE p.status = 'verified'";
        $params = [];

        $startBoundary = $this->normalizeDateBoundary($start_date, false);
        $endBoundary = $this->normalizeDateBoundary($end_date, true, $startBoundary);

        if ($startBoundary && !$endBoundary) {
            $endBoundary = (new DateTime($startBoundary))->modify('last day of this month')->format('Y-m-d');
        }

        if ($startBoundary && $endBoundary) {
            $where_clause .= " AND DATE(p.verified_at) BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startBoundary;
            $params[':end_date'] = $endBoundary;
        } elseif ($startBoundary) {
            $where_clause .= " AND DATE(p.verified_at) >= :start_date";
            $params[':start_date'] = $startBoundary;
        } elseif ($endBoundary) {
            $where_clause .= " AND DATE(p.verified_at) <= :end_date";
            $params[':end_date'] = $endBoundary;
        }

        $query = "SELECT 
                    SUM(p.amount) as total_revenue,
                    COUNT(p.id) as total_transactions
                  FROM " . $this->table_name . " p
                  $where_clause";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function normalizeDateBoundary($value, $isEnd = false, $startReference = null) {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d');
        }

        $ym = DateTime::createFromFormat('Y-m', $value);
        if ($ym instanceof DateTime) {
            if ($isEnd) {
                $ym->modify('last day of this month');
            } else {
                $ym->modify('first day of this month');
            }
            return $ym->format('Y-m-d');
        }

        if ($isEnd && $startReference) {
            $fallback = new DateTime($startReference);
            $fallback->modify('last day of this month');
            return $fallback->format('Y-m-d');
        }

        return null;
    }

    /**
     * ตรวจสอบความถูกต้องของข้อมูลการชำระเงิน
     */
    public function validate($data) {
        $errors = [];

        // ตรวจสอบจำนวนเงิน
        if(empty($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
            $errors[] = "กรุณากรอกจำนวนเงินที่ถูกต้อง";
        }

        // ตรวจสอบ booking_id
        if(empty($data['booking_id'])) {
            $errors[] = "ไม่พบข้อมูลการจอง";
        }

        return $errors;
    }

    /**
     * สร้างใบเสร็จ PDF (placeholder)
     */
    public function generateReceipt($payment_id) {
        // TODO: Implement PDF receipt generation
        // สามารถใช้ library เช่น TCPDF หรือ FPDF
        return true;
    }
}
?>
