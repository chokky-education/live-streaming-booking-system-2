<?php
/**
 * Package Model
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/PackageItem.php';
require_once __DIR__ . '/Booking.php';

class Package extends BaseModel {
    protected $table_name = "packages";

    public $id;
    public $name;
    public $description;
    public $price;
    public $equipment_list;
    public $image_url;
    public $is_active;
    public $max_concurrent_reservations = 5;
    public $created_at;

    // Constructor uses parent
    public function __construct($db) {
        parent::__construct($db);
    }

    /**
     * ดึงแพ็คเกจทั้งหมดที่เปิดใช้งาน
     */
    public function getActivePackages($with_items = false) {
        $query = "SELECT id, name, description, price, equipment_list, image_url, max_concurrent_reservations 
                  FROM " . $this->table_name . " 
                  WHERE is_active = TRUE 
                  ORDER BY price ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($with_items) {
            foreach ($packages as &$package) {
                $decoded = json_decode($package['equipment_list'], true);
                $package['equipment_list'] = is_array($decoded) ? $decoded : [];
            }
            return $this->attachItems($packages);
        }

        return $packages;
    }

    /**
     * ดึงแพ็คเกจตาม ID
     */
    public function getById($id) {
        $query = "SELECT id, name, description, price, equipment_list, image_url, is_active, max_concurrent_reservations 
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
            $decoded = json_decode($row['equipment_list'], true);
            $this->equipment_list = is_array($decoded) ? $decoded : [];
            $this->image_url = $row['image_url'];
            $this->is_active = $row['is_active'];
            $this->max_concurrent_reservations = (int)($row['max_concurrent_reservations'] ?? 5);
            
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
                      equipment_list=:equipment_list, image_url=:image_url, is_active=:is_active,
                      max_concurrent_reservations=:max_concurrent_reservations";

        $stmt = $this->conn->prepare($query);

        // Convert equipment list to JSON
        $equipment_json = json_encode($this->equipment_list, JSON_UNESCAPED_UNICODE);

        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":price", $this->price);
        $stmt->bindParam(":equipment_list", $equipment_json);
        $stmt->bindParam(":image_url", $this->image_url);
        $stmt->bindParam(":is_active", $this->is_active);
        $stmt->bindParam(":max_concurrent_reservations", $this->max_concurrent_reservations, PDO::PARAM_INT);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * อัปเดตแพ็คเกจ
     */
    public function update($id = null, $data = null) {
        $targetId = $id ?? $this->id;

        if ($targetId === null) {
            throw new Exception('Package ID is required for update');
        }

        if ($data === null) {
            $data = [
                'name' => $this->name,
                'description' => $this->description,
                'price' => $this->price,
                'equipment_list' => $this->equipment_list,
                'image_url' => $this->image_url,
                'is_active' => $this->is_active,
                'max_concurrent_reservations' => (int)$this->max_concurrent_reservations,
            ];
        }

        if (isset($data['equipment_list']) && is_array($data['equipment_list'])) {
            $data['equipment_list'] = json_encode($data['equipment_list'], JSON_UNESCAPED_UNICODE);
        }

        return parent::update($targetId, $data);
    }

    /**
     * ลบแพ็คเกจ (เปลี่ยนสถานะเป็นไม่ใช้งาน)
     */
    public function delete($id = null) {
        $targetId = $id ?? $this->id;

        if ($targetId === null) {
            throw new Exception('Package ID is required for delete');
        }

        $query = "UPDATE " . $this->table_name . " 
                  SET is_active = FALSE 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":id", $targetId, PDO::PARAM_INT);

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
     * ตรวจสอบความพร้อมของแพ็คเกจในช่วงวันที่กำหนด
     */
    public function checkAvailability($pickup_date, $return_date) {
        $booking = new Booking($this->conn);
        return $booking->checkPackageAvailability($this->id, $pickup_date, $return_date);
    }

    /**
     * ดึงรายการแพ็คเกจทั้งหมด (สำหรับ admin)
     */
    public function getAllPackages() {
        $query = "SELECT id, name, description, price, equipment_list, image_url, is_active, created_at, max_concurrent_reservations 
                  FROM " . $this->table_name . " 
                  ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON equipment lists
        foreach ($packages as &$package) {
            $decoded = json_decode($package['equipment_list'], true);
            $package['equipment_list'] = is_array($decoded) ? $decoded : [];
        }

        return $this->attachItems($packages);
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
                    COALESCE(SUM(CASE WHEN b.status IN ('confirmed', 'completed') THEN b.total_price ELSE 0 END), 0) as total_revenue
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
     * แนบรายการ items ให้ชุดแพ็คเกจที่ส่งเข้ามา
     */
    private function attachItems(array $packages) {
        if (empty($packages)) {
            return $packages;
        }

        $packageIds = array_column($packages, 'id');
        $itemsMap = $this->getItemsByPackageIds($packageIds);

        foreach ($packages as &$package) {
            $package['items'] = $itemsMap[$package['id']] ?? [];
        }

        return $packages;
    }

    /**
     * ดึง items ทั้งหมดของแพ็คเกจที่ระบุ (ผลลัพธ์ grouped)
     */
    public function getItemsByPackageIds(array $packageIds) {
        if (empty($packageIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($packageIds), '?'));
        $query = "SELECT * FROM package_items WHERE package_id IN ($placeholders) ORDER BY created_at ASC";
        $stmt = $this->conn->prepare($query);
        foreach ($packageIds as $index => $id) {
            $stmt->bindValue($index + 1, (int)$id, PDO::PARAM_INT);
        }
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $grouped = [];
        foreach ($items as $item) {
            $grouped[$item['package_id']][] = $item;
        }
        return $grouped;
    }

    /**
     * ดึง items สำหรับแพ็คเกจเดียว
     */
    public function getItemsForPackage($package_id) {
        $map = $this->getItemsByPackageIds([$package_id]);
        return $map[$package_id] ?? [];
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
                    COUNT(CASE WHEN b.status IN ('confirmed', 'completed') THEN 1 END) AS bookings_count,
                    COALESCE(SUM(CASE WHEN b.status IN ('confirmed', 'completed') THEN b.total_price ELSE 0 END), 0) AS total_revenue
                  FROM " . $this->table_name . " p
                  LEFT JOIN bookings b ON p.id = b.package_id
                  WHERE p.is_active = TRUE
                  GROUP BY p.id, p.name, p.description, p.price, p.equipment_list, p.image_url
                  ORDER BY bookings_count DESC, total_revenue DESC, p.price ASC
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
