# Database Files Documentation

## ไฟล์ที่เกี่ยวข้องกับ Database

### 1. Database Schema & Setup
- `database/create_database.sql` - SQL script สำหรับสร้างฐานข้อมูลและตาราง

### 2. Database Configuration
- `config/database.php` - ไฟล์การตั้งค่าการเชื่อมต่อฐานข้อมูล (PHP)

### 3. Database Models (PHP Classes)
- `models/User.php` - Model สำหรับจัดการข้อมูลผู้ใช้
- `models/Booking.php` - Model สำหรับจัดการข้อมูลการจองคิว (รองรับ pickup/return, rental_days, ตรวจสอบ availability)
- `models/Payment.php` - Model สำหรับจัดการข้อมูลการชำระเงิน
- `models/Package.php` - Model สำหรับจัดการข้อมูลแพ็คเกจ
- `models/PackageItem.php` - Model สำหรับจัดการอุปกรณ์ย่อยในแต่ละแพ็คเกจ

## Database Technology Stack
- **MySQL** - ระบบจัดการฐานข้อมูล
- **SQL** - ภาษาสำหรับจัดการฐานข้อมูล
- **PHP PDO/MySQLi** - การเชื่อมต่อและจัดการฐานข้อมูลผ่าน PHP

## ตารางในฐานข้อมูล (ตาม SQL file)
- `users` - ข้อมูลผู้ใช้งาน
- `bookings` - ข้อมูลการจองคิว (มีคอลัมน์ `pickup_date`, `return_date`, `pickup_time`, `return_time`, `rental_days`)
- `payments` - ข้อมูลการชำระเงิน
- `packages` - ข้อมูลแพ็คเกจบริการ
- `package_items` - รายการอุปกรณ์ที่ประกอบอยู่ในแต่ละแพ็คเกจ พร้อมข้อมูลสเปก/รูปภาพ
- `equipment_availability` - ปฏิทินสถานะอุปกรณ์ต่อวัน ใช้บล็อกช่วงวันที่ถูกจอง/พร้อมใช้งาน

## การทำงานของ Database Layer
1. **create_database.sql** - สร้างโครงสร้างฐานข้อมูล
2. **database.php** - จัดการการเชื่อมต่อ
3. **Models** - จัดการ CRUD operations (Create, Read, Update, Delete)

### Migration: Multi-day Booking (2025-09-26)
1. สำรองฐานข้อมูล (`mysqldump`) ก่อนทุกครั้ง
2. รันสคริปต์ `database/migrations/2025_09_26_multi_day_booking.sql`
   - สคริปต์จะเพิ่มคอลัมน์ pick-up/return, คำนวณ `rental_days`, สร้างตาราง `equipment_availability`, และ seed ledger ตามข้อมูลเก่า
3. ตรวจสอบผลลัพธ์หลังไมเกรต
   - `SELECT COUNT(*) FROM bookings WHERE return_date IS NULL` ควรได้ 0
   - ตรวจสอบจำนวนแถวใน `equipment_availability` ให้อยู่ในช่วงที่คาดไว้
4. Rollback (หากจำเป็น)
   - กู้คืนจากไฟล์สำรอง หรือย้อนขั้นตอนด้วยการลบคอลัมน์/ตารางที่เพิ่มขึ้นพร้อมตรวจสอบข้อมูลให้ครบถ้วน

## หมายเหตุ
- ใช้ **MySQL** เป็นฐานข้อมูลหลัก
- Models เขียนด้วย **PHP** สำหรับจัดการข้อมูล
- มีการแยกไฟล์ config สำหรับความปลอดภัย
- การเพิ่มคอลัมน์ pickup/return และตาราง `equipment_availability` ต้องรันสคริปต์ migration พร้อมสำรองข้อมูลก่อนเสมอ
