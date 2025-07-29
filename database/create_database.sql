-- สร้างฐานข้อมูลระบบจองอุปกรณ์ Live Streaming
-- Create Database for Live Streaming Equipment Booking System

CREATE DATABASE IF NOT EXISTS live_streaming_booking 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE live_streaming_booking;

-- ตาราง users สำหรับจัดการผู้ใช้งาน
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('admin', 'customer') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- ตาราง packages สำหรับแพ็คเกจอุปกรณ์
CREATE TABLE packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    equipment_list JSON,
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_price (price)
) ENGINE=InnoDB;

-- ตาราง bookings สำหรับการจอง
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_code VARCHAR(20) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    location TEXT,
    notes TEXT,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE RESTRICT,
    INDEX idx_booking_date (booking_date),
    INDEX idx_status (status),
    INDEX idx_user_bookings (user_id, created_at),
    INDEX idx_booking_code (booking_code)
) ENGINE=InnoDB;

-- ตาราง payments สำหรับการชำระเงิน
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'bank_transfer',
    slip_image_url VARCHAR(255),
    transaction_ref VARCHAR(100),
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    paid_at TIMESTAMP NULL,
    verified_at TIMESTAMP NULL,
    verified_by INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_payment_status (status),
    INDEX idx_booking_payment (booking_id),
    INDEX idx_paid_at (paid_at)
) ENGINE=InnoDB;

-- ตาราง equipment สำหรับจัดการอุปกรณ์
CREATE TABLE equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    brand VARCHAR(50),
    model VARCHAR(50),
    description TEXT,
    daily_price DECIMAL(10,2),
    is_available BOOLEAN DEFAULT TRUE,
    package_id INT,
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL,
    INDEX idx_available (is_available),
    INDEX idx_type (type),
    INDEX idx_package (package_id)
) ENGINE=InnoDB;

-- ตาราง system_logs สำหรับบันทึกการทำงานของระบบ
CREATE TABLE system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_logs (user_id, created_at),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- เพิ่มข้อมูลเริ่มต้น

-- เพิ่มผู้ดูแลระบบ (admin)
INSERT INTO users (username, password, email, first_name, last_name, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@livestreaming.com', 'ผู้ดูแล', 'ระบบ', 'admin');

-- เพิ่มแพ็คเกจอุปกรณ์
INSERT INTO packages (name, description, price, equipment_list, is_active) VALUES 
(
    'แพ็คเกจพื้นฐาน (Basic Package)',
    'เหมาะสำหรับการถ่ายทอดสดขนาดเล็ก เช่น การขายของออนไลน์ การสัมภาษณ์',
    2500.00,
    JSON_ARRAY('กล้อง DSLR/Mirrorless 1 ตัว', 'ไมโครโฟน 1 ตัว'),
    TRUE
),
(
    'แพ็คเกจมาตรฐาน (Standard Package)',
    'เหมาะสำหรับงานสัมมนา การประชุม หรืองานขนาดกลาง',
    4500.00,
    JSON_ARRAY('กล้อง DSLR/Mirrorless 2 ตัว', 'ไมโครโฟน 2 ตัว', 'ไฟ LED 2 ชุด'),
    TRUE
),
(
    'แพ็คเกจพรีเมี่ยม (Premium Package)',
    'เหมาะสำหรับงานแต่งงาน อีเวนต์ใหญ่ หรืองานที่ต้องการคุณภาพสูง',
    7500.00,
    JSON_ARRAY('กล้อง DSLR/Mirrorless 3 ตัว', 'ไมโครโฟน 3 ตัว', 'ไฟ LED 4 ชุด', 'ขาตั้งกล้อง 3 ชุด', 'Switcher/Mixer 1 ตัว'),
    TRUE
);

-- เพิ่มอุปกรณ์ตัวอย่าง
INSERT INTO equipment (name, type, brand, model, daily_price, is_available, package_id) VALUES 
('Canon EOS R6', 'กล้อง', 'Canon', 'EOS R6', 800.00, TRUE, 1),
('Sony A7 III', 'กล้อง', 'Sony', 'A7 III', 750.00, TRUE, 2),
('Canon EOS R5', 'กล้อง', 'Canon', 'EOS R5', 1000.00, TRUE, 3),
('Rode PodMic', 'ไมโครโฟน', 'Rode', 'PodMic', 200.00, TRUE, 1),
('Shure SM7B', 'ไมโครโฟน', 'Shure', 'SM7B', 300.00, TRUE, 2),
('Godox SL-60W', 'ไฟ LED', 'Godox', 'SL-60W', 150.00, TRUE, 2),
('ATEM Mini Pro', 'Switcher', 'Blackmagic', 'ATEM Mini Pro', 500.00, TRUE, 3);

-- สร้าง View สำหรับสถิติการจอง
CREATE VIEW booking_statistics AS
SELECT 
    DATE(created_at) as booking_date,
    COUNT(*) as total_bookings,
    SUM(total_price) as total_revenue,
    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_bookings,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_bookings,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_bookings
FROM bookings 
GROUP BY DATE(created_at)
ORDER BY booking_date DESC;

-- สร้าง View สำหรับรายงานแพ็คเกจยอดนิยม
CREATE VIEW popular_packages AS
SELECT 
    p.id,
    p.name,
    p.price,
    COUNT(b.id) as booking_count,
    SUM(b.total_price) as total_revenue
FROM packages p
LEFT JOIN bookings b ON p.id = b.package_id
WHERE p.is_active = TRUE
GROUP BY p.id, p.name, p.price
ORDER BY booking_count DESC;

COMMIT;