<?php
/**
 * User Model
 * ระบบจองอุปกรณ์ Live Streaming
 */

require_once __DIR__ . '/BaseModel.php';

class User extends BaseModel {
    protected $table_name = "users";

    public $id;
    public $username;
    public $password;
    public $email;
    public $phone;
    public $first_name;
    public $last_name;
    public $role;
    public $created_at;

    // Constructor uses parent
    public function __construct($db) {
        parent::__construct($db);
    }

    /**
     * สร้างผู้ใช้ใหม่
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET username=:username, password=:password, email=:email, 
                      phone=:phone, first_name=:first_name, last_name=:last_name, role=:role";

        $stmt = $this->conn->prepare($query);

        // Hash password
        $hashed_password = password_hash($this->password, PASSWORD_BCRYPT);

        // Bind values
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":password", $hashed_password);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":role", $this->role);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * ตรวจสอบการเข้าสู่ระบบ
     */
    public function login($username, $password) {
        $query = "SELECT id, username, password, email, first_name, last_name, role 
                  FROM " . $this->table_name . " 
                  WHERE username = :username OR email = :email 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":email", $username);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(password_verify($password, $row['password'])) {
                $this->id = $row['id'];
                $this->username = $row['username'];
                $this->email = $row['email'];
                $this->first_name = $row['first_name'];
                $this->last_name = $row['last_name'];
                $this->role = $row['role'];
                
                return true;
            }
        }

        return false;
    }

    /**
     * ตรวจสอบว่า username หรือ email ซ้ำหรือไม่
     */
    public function exists($username, $email) {
        $query = "SELECT id FROM " . $this->table_name . " 
                  WHERE username = :username OR email = :email 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * ดึงข้อมูลผู้ใช้ตาม ID
     */
    public function getById($id) {
        $query = "SELECT id, username, email, phone, first_name, last_name, role, created_at 
                  FROM " . $this->table_name . " 
                  WHERE id = :id 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->id = $row['id'];
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->phone = $row['phone'];
            $this->first_name = $row['first_name'];
            $this->last_name = $row['last_name'];
            $this->role = $row['role'];
            $this->created_at = $row['created_at'];
            
            return true;
        }

        return false;
    }

    /**
     * อัปเดตข้อมูลผู้ใช้
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET email=:email, phone=:phone, first_name=:first_name, last_name=:last_name 
                  WHERE id=:id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    /**
     * เปลี่ยนรหัสผ่าน
     */
    public function changePassword($new_password) {
        $query = "UPDATE " . $this->table_name . " 
                  SET password=:password 
                  WHERE id=:id";

        $stmt = $this->conn->prepare($query);
        
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt->bindParam(":password", $hashed_password);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    /**
     * ดึงรายชื่อลูกค้าทั้งหมด (สำหรับ admin)
     */
    public function getAllCustomers() {
        $query = "SELECT 
                        u.id,
                        u.username,
                        u.email,
                        u.phone,
                        u.first_name,
                        u.last_name,
                        u.created_at,
                        COUNT(b.id) AS total_bookings,
                        COALESCE(SUM(CASE WHEN b.status IN ('confirmed', 'completed') THEN b.total_price ELSE 0 END), 0) AS total_spent
                  FROM " . $this->table_name . " u
                  LEFT JOIN bookings b ON u.id = b.user_id
                  WHERE u.role = 'customer'
                  GROUP BY u.id, u.username, u.email, u.phone, u.first_name, u.last_name, u.created_at
                  ORDER BY u.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ตรวจสอบความถูกต้องของข้อมูล
     */
    public function validate($data) {
        $errors = [];

        // ตรวจสอบ username
        if(empty($data['username'])) {
            $errors[] = "กรุณากรอก Username";
        } elseif(strlen($data['username']) < 3) {
            $errors[] = "Username ต้องมีอย่างน้อย 3 ตัวอักษร";
        }

        // ตรวจสอบ email
        if(empty($data['email'])) {
            $errors[] = "กรุณากรอกอีเมล";
        } elseif(!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "รูปแบบอีเมลไม่ถูกต้อง";
        }

        // ตรวจสอบรหัสผ่าน
        if(empty($data['password'])) {
            $errors[] = "กรุณากรอกรหัสผ่าน";
        } elseif(strlen($data['password']) < 6) {
            $errors[] = "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร";
        }

        // ตรวจสอบชื่อ-นามสกุล
        if(empty($data['first_name'])) {
            $errors[] = "กรุณากรอกชื่อ";
        }
        if(empty($data['last_name'])) {
            $errors[] = "กรุณากรอกนามสกุล";
        }

        // ตรวจสอบเบอร์โทร
        if(!empty($data['phone'])) {
            $phone = preg_replace('/[^0-9]/', '', $data['phone']);
            if (!preg_match('/^0[0-9]{8,9}$/', $phone)) {
                $errors[] = "รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง";
            }
        }

        return $errors;
    }
}
?>
