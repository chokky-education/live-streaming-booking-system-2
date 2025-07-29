# Database Files Documentation

## ไฟล์ที่เกี่ยวข้องกับ Database

### 1. Database Schema & Setup
- `database/create_database.sql` - SQL script สำหรับสร้างฐานข้อมูลและตาราง

### 2. Database Configuration
- `config/database.php` - ไฟล์การตั้งค่าการเชื่อมต่อฐานข้อมูล (PHP)

### 3. Database Models (PHP Classes)
- `models/User.php` - Model สำหรับจัดการข้อมูลผู้ใช้
- `models/Booking.php` - Model สำหรับจัดการข้อมูลการจองคิว
- `models/Payment.php` - Model สำหรับจัดการข้อมูลการชำระเงิน
- `models/Package.php` - Model สำหรับจัดการข้อมูลแพ็คเกจ

## Database Technology Stack
- **MySQL** - ระบบจัดการฐานข้อมูล
- **SQL** - ภาษาสำหรับจัดการฐานข้อมูล
- **PHP PDO/MySQLi** - การเชื่อมต่อและจัดการฐานข้อมูลผ่าน PHP

## ตารางในฐานข้อมูล (ตาม SQL file)
- `users` - ข้อมูลผู้ใช้งาน
- `bookings` - ข้อมูลการจองคิว
- `payments` - ข้อมูลการชำระเงิน
- `packages` - ข้อมูลแพ็คเกจบริการ

## การทำงานของ Database Layer
1. **create_database.sql** - สร้างโครงสร้างฐานข้อมูล
2. **database.php** - จัดการการเชื่อมต่อ
3. **Models** - จัดการ CRUD operations (Create, Read, Update, Delete)

## หมายเหตุ
- ใช้ **MySQL** เป็นฐานข้อมูลหลัก
- Models เขียนด้วย **PHP** สำหรับจัดการข้อมูล
- มีการแยกไฟล์ config สำหรับความปลอดภัย