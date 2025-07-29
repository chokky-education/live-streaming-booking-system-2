<?php
/**
 * Package Model
 * ระบบจองอุปกรณ์ Live Streaming
 */

class Package {
    private $conn;
    private $table_name = "packages";

    public $id;
    public $name;
    public $description;
    public $price;
    public $equipment_list;
    public $image_url;
    public $is_active;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * ดึงแพ็คเกจทั้งหมดที่เปิดใช้งาน
     */
    public function getActivePackages() {
        $query = "SELECT id, name, description, price, equipment_list, image_url 
                  FROM " . $this->table_name . " 
                  WHERE is_active = TRUE 
                  ORDER BY price ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ดึงแพ็คเกจตาม ID
     */
    public function getById($id) {
        $query = "SELECT id, name, description, price, equipment_list, image_url, is_active 
                  FROM " . $this->table_name . " 
                  WHERE id = :id 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->id = $row['id'];
            $this->name = $row['name'];
            $this->description = $row['description'];
            $this->price = $row['price'];
            $this->equipment_list = json_decode($row['equipment_list'], true);
            $this->image_url = $row['image_url'];
            $this->is_active = $row['is_active'];
            
            return true;
        }

        return false;
    }

    /**
     * สร้างแพ็คเกจใหม่
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET name=:name, description=:description, price=:price, 
                      equipment_list=:equipment_list, image_url=:image_url, is_active=:is_active";

        $stmt = $this->conn->prepare($query);

        // Convert equipment list to JSON
        $equipment_json = json_encode($this->equipment_list, JSON_UNESCAPED_UNICODE);

        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":price", $this->price);
        $stmt->bindParam(":equipment_list", $equipment_json);
        $stmt->bindParam(":image_url", $this->image_url);
        $stmt->bindParam(":is_active", $this->is_active);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * อัปเดตแพ็คเกจ
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET name=:name, description=:description, price=:price, 
                      equipment_list=:equipment_list, image_url=:image_url, is_active=:is_active 
                  WHERE id=:id";

        $stmt = $this->conn->prepare($query);

        // Convert equipment list to JSON
        $equipment_json = json_encode($this->equipment_list, JSON_UNESCAPED_UNICODE);

        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":price", $this->price);
        $stmt->bindParam(":equipment_list", $equipment_json);
        $stmt->bindParam(":image_url", $this->image_url);
        $stmt->bindParam(":is_active", $this->is_active);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    /**
     * ลบแพ็คเกจ (เปลี่ยนสถานะเป็นไม่ใช้งาน)
     */
    public function delete() {
        $query = "UPDATE " . $this->table_name . " 
                  SET is_active = FALSE 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    /**
     * คำนวณราคารวมพร้อม VAT
     */
    public function calculateTotalPrice($days = 1, $vat_rate = VAT_RATE) {
        $base_price = $this->price * $days;
        $vat_amount = $base_price * $vat_rate;
        $total_price = $base_price + $vat_amount;

        return [
            'base_price' => $base_price,
            'vat_amount' => $vat_amount,
            'total_price' => $total_price,
            'days' => $days,
            'vat_rate' => $vat_rate
        ];
    }

    /**
     * ตรวจสอบความพร้อมของแพ็คเกจในวันที่กำหนด
     */
    public function checkAvailability($date) {
        $query = "SELECT COUNT(*) as booking_count 
                  FROM bookings 
                  WHERE package_id = :package_id 
                  AND booking_date = :booking_date 
                  AND status IN ('pending', 'confirmed')";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":package_id", $this->id);
        $stmt->bindParam(":booking_date", $date);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // สมมติว่าแต่ละแพ็คเกจมีอุปกรณ์ 1 ชุด (สามารถปรับแต่งได้)
        $max_bookings = 1;
        
        return $result['booking_count'] < $max_bookings;
    }

    /**
     * ดึงรายการแพ็คเกจทั้งหมด (สำหรับ admin)
     */
    public function getAllPackages() {
        $query = "SELECT id, name, description, price, equipment_list, image_url, is_active, created_at 
                  FROM " . $this->table_name . " 
                  ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON equipment lists
        foreach ($packages as &$package) {
            $package['equipment_list'] = json_decode($package['equipment_list'], true);
        }

        return $packages;
    }

    /**
     * ดึงสถิติการจองของแพ็คเกจ
     */
    public function getBookingStats() {
        $query = "SELECT 
                    p.id,
                    p.name,
                    p.price,
                    COUNT(b.id) as total_bookings,
                    COUNT(CASE WHEN b.status = 'confirmed' THEN 1 END) as confirmed_bookings,
                    COUNT(CASE WHEN b.status = 'pending' THEN 1 END) as pending_bookings,
                    SUM(CASE WHEN b.status = 'confirmed' THEN b.total_price ELSE 0 END) as total_revenue
                  FROM " . $this->table_name . " p
                  LEFT JOIN bookings b ON p.id = b.package_id
                  WHERE p.is_active = TRUE
                  GROUP BY p.id, p.name, p.price
                  ORDER BY total_bookings DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ตรวจสอบความถูกต้องของข้อมูลแพ็คเกจ
     */
    public function validate($data) {
        $errors = [];

        // ตรวจสอบชื่อแพ็คเกจ
        if(empty($data['name'])) {
            $errors[] = "กรุณากรอกชื่อแพ็คเกจ";
        }

        // ตรวจสอบราคา
        if(empty($data['price']) || !is_numeric($data['price']) || $data['price'] <= 0) {
            $errors[] = "กรุณากรอกราคาที่ถูกต้อง";
        }

        // ตรวจสอบรายการอุปกรณ์
        if(empty($data['equipment_list']) || !is_array($data['equipment_list'])) {
            $errors[] = "กรุณาระบุรายการอุปกรณ์";
        }

        return $errors;
    }

    /**
     * ดึงแพ็คเกจยอดนิยม
     */
    public function getPopularPackages($limit = 3) {
        $query = "SELECT 
                    p.id,
                    p.name,
                    p.description,
                    p.price,
                    p.equipment_list,
                    p.image_url,
                    COUNT(b.id) as booking_count
                  FROM " . $this->table_name . " p
                  LEFT JOIN bookings b ON p.id = b.package_id AND b.status = 'confirmed'
                  WHERE p.is_active = TRUE
                  GROUP BY p.id, p.name, p.description, p.price, p.equipment_list, p.image_url
                  ORDER BY booking_count DESC, p.price ASC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();

        $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON equipment lists
        foreach ($packages as &$package) {
            $package['equipment_list'] = json_decode($package['equipment_list'], true);
        }

        return $packages;
    }
}
?>