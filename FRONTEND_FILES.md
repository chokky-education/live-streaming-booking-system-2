# Frontend Files Documentation

## ไฟล์ที่ทำหน้าที่ฝั่ง Frontend เฉพาะ

### 1. HTML Files
- `index.html` - หน้าหลักของระบบ (Pure HTML/CSS/JavaScript)

### 2. CSS Files
- `assets/css/style.css` - ไฟล์ CSS หลักสำหรับการจัดแต่งหน้าตา
- `assets/css/bootstrap.min.css` - Bootstrap CSS Framework

### 3. JavaScript Files
- `assets/js/main.js` - JavaScript หลักสำหรับ interactive features
- `assets/js/booking.js` - JavaScript สำหรับระบบจองคิว
- `assets/js/payment.js` - JavaScript สำหรับระบบชำระเงิน

### 4. Image/Media Files
- `assets/images/` - รูปภาพต่างๆ ที่ใช้ใน UI
- `assets/icons/` - ไอคอนต่างๆ

## ไฟล์ที่เป็น Frontend + Backend (PHP)
- `pages/login.php` - หน้า Login (มี HTML/CSS/JS + PHP backend logic)
- `pages/profile.php` - หน้า Profile (มี HTML/CSS/JS + PHP backend logic)
- `pages/booking.php` - หน้าจองคิว (มี HTML/CSS/JS + PHP backend logic)
- `pages/payment.php` - หน้าชำระเงิน (มี HTML/CSS/JS + PHP backend logic)

## ไฟล์ที่เป็น Backend เฉพาะ
- `models/` - PHP Models (User.php, Booking.php, Payment.php, Package.php)
- `config/database.php` - การตั้งค่าฐานข้อมูล
- `database/create_database.sql` - SQL สำหรับสร้างฐานข้อมูล

## สรุป Frontend Technologies
- **HTML5** - โครงสร้างหน้าเว็บ
- **CSS3** - การจัดแต่งหน้าตา
- **JavaScript** - การทำงานแบบ interactive
- **Bootstrap** - CSS Framework สำหรับ responsive design

## หมายเหตุ
ในโปรเจคนี้ใช้ **PHP** เป็น server-side language แต่ส่วน Frontend ยังคงใช้ HTML, CSS, JavaScript เป็นหลัก