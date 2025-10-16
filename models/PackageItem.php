<?php
/**
 * Package Item Model
 * จัดการข้อมูลอุปกรณ์ย่อยในแต่ละแพ็คเกจ
 */

class PackageItem {
    private $conn;
    private $table_name = "package_items";

    public $id;
    public $package_id;
    public $name;
    public $quantity;
    public $specs;
    public $notes;
    public $image_path;
    public $image_alt;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getById($id) {
        $query = "SELECT * FROM {$this->table_name} WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByPackage($package_id) {
        $query = "SELECT * FROM {$this->table_name} WHERE package_id = :package_id ORDER BY created_at ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':package_id', $package_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $query = "INSERT INTO {$this->table_name}
            (package_id, name, quantity, specs, notes, image_path, image_alt)
            VALUES (:package_id, :name, :quantity, :specs, :notes, :image_path, :image_alt)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':package_id', $data['package_id'], PDO::PARAM_INT);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':quantity', $data['quantity']);
        $stmt->bindParam(':specs', $data['specs']);
        $stmt->bindParam(':notes', $data['notes']);
        $stmt->bindParam(':image_path', $data['image_path']);
        $stmt->bindParam(':image_alt', $data['image_alt']);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function update($id, $data) {
        $query = "UPDATE {$this->table_name}
            SET name = :name,
                quantity = :quantity,
                specs = :specs,
                notes = :notes,
                image_path = :image_path,
                image_alt = :image_alt
            WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':quantity', $data['quantity']);
        $stmt->bindParam(':specs', $data['specs']);
        $stmt->bindParam(':notes', $data['notes']);
        $stmt->bindParam(':image_path', $data['image_path']);
        $stmt->bindParam(':image_alt', $data['image_alt']);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function delete($id) {
        $query = "DELETE FROM {$this->table_name} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function validate($data) {
        $errors = [];
        if (empty($data['name'])) {
            $errors[] = 'กรุณาระบุชื่ออุปกรณ์';
        }
        if (empty($data['package_id'])) {
            $errors[] = 'ไม่พบแพ็คเกจที่ต้องการบันทึกรายการ';
        }
        return $errors;
    }

    public function handleImageUpload($file) {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['success' => true, 'path' => null];
        }

        $validation = validate_file_upload($file, ['jpg', 'jpeg', 'png'], [
            'image/jpeg',
            'image/png'
        ]);
        if (!$validation['success']) {
            return ['success' => false, 'message' => $validation['message']];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        try {
            $filename = bin2hex(random_bytes(16));
        } catch (Exception $e) {
            $filename = uniqid('item_', true);
        }
        $newName = $filename . '.' . $extension;

        $baseDir = dirname(__DIR__);
        $uploadDir = $baseDir . '/uploads/package-items/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destination = $uploadDir . $newName;
        $moved = move_uploaded_file($file['tmp_name'], $destination);
        if (!$moved && PHP_SAPI === 'cli') {
            $moved = rename($file['tmp_name'], $destination);
        }

        if (!$moved) {
            return ['success' => false, 'message' => 'ไม่สามารถอัปโหลดไฟล์ได้'];
        }

        return ['success' => true, 'path' => 'uploads/package-items/' . $newName];
    }
}
?>
