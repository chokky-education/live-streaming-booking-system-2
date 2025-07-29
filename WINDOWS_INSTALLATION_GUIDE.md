# คู่มือติดตั้งสำหรับ Windows

## 🎯 วิธีการ Download และติดตั้ง (เรียงจากง่ายไปยาก)

---

## 🥇 วิธีที่ 1: Download ZIP (ง่ายที่สุด)

### ขั้นตอน:
1. **ไปที่** https://github.com/Chokky2004/ive-streaming-booking-system
2. **คลิก** ปุ่มสีเขียว "Code"
3. **เลือก** "Download ZIP"
4. **แตกไฟล์** ZIP ที่ดาวน์โหลดมา
5. **เปลี่ยนชื่อโฟลเดอร์** เป็น `live-streaming-booking-system`

### ข้อดี:
- ✅ ไม่ต้องติดตั้ง Git
- ✅ ง่ายมาก คลิกเดียวเสร็จ
- ✅ เหมาะกับคนที่ไม่คุ้นเคย Git

### ข้อเสีย:
- ❌ ไม่สามารถ update โค้ดได้ง่าย
- ❌ ไม่มี version control

---

## 🥈 วิธีที่ 2: ใช้ GitHub Desktop (ง่าย + มี GUI)

### ขั้นตอน:
1. **ดาวน์โหลด** GitHub Desktop จาก https://desktop.github.com/
2. **ติดตั้ง** GitHub Desktop
3. **เปิด** GitHub Desktop
4. **คลิก** "Clone a repository from the Internet"
5. **ใส่ URL**: `https://github.com/Chokky2004/ive-streaming-booking-system`
6. **เลือกโฟลเดอร์** ที่ต้องการเก็บ
7. **คลิก** "Clone"

### ข้อดี:
- ✅ มี GUI ใช้งานง่าย
- ✅ สามารถ sync กับ GitHub ได้
- ✅ ดู history การเปลี่ยนแปลงได้

### ข้อเสีย:
- ❌ ต้องติดตั้งโปรแกรมเพิ่ม

---

## 🥉 วิธีที่ 3: ใช้ Git Command Line (ยากที่สุด แต่มีประสิทธิภาพ)

### ขั้นตอน:
1. **ติดตั้ง Git** จาก https://git-scm.com/download/win
2. **เปิด Command Prompt** หรือ **PowerShell**
3. **ไปที่โฟลเดอร์** ที่ต้องการเก็บโปรเจค:
   ```cmd
   cd C:\Users\YourName\Desktop
   ```
4. **Clone repository**:
   ```cmd
   git clone https://github.com/Chokky2004/ive-streaming-booking-system.git
   ```
5. **เข้าไปในโฟลเดอร์**:
   ```cmd
   cd ive-streaming-booking-system
   ```

### ข้อดี:
- ✅ ควบคุมได้เต็มที่
- ✅ สามารถใช้คำสั่ง Git ขั้นสูงได้
- ✅ เหมาะกับ developer

### ข้อเสีย:
- ❌ ต้องเรียนรู้คำสั่ง Git
- ❌ ยากสำหรับมือใหม่

---

## 🚀 หลังจาก Download แล้ว ต้องทำอะไรต่อ?

### 1. ติดตั้ง XAMPP (สำหรับ PHP + MySQL)
- **ดาวน์โหลด** จาก https://www.apachefriends.org/
- **ติดตั้ง** XAMPP
- **เปิด** XAMPP Control Panel
- **Start** Apache และ MySQL

### 2. ตั้งค่าฐานข้อมูล
```cmd
# คัดลอกไฟล์ config
copy config\database.example.php config\database.php

# แก้ไขไฟล์ config\database.php ให้เหมาะกับ XAMPP:
# - host: localhost
# - username: root  
# - password: (ว่างเปล่า)
# - database: live_streaming_booking
```

### 3. สร้างฐานข้อมูล
- **เปิด** http://localhost/phpmyadmin
- **สร้างฐานข้อมูล** ชื่อ `live_streaming_booking`
- **Import** ไฟล์ `database/create_database.sql`

### 4. วางโปรเจคใน htdocs
- **คัดลอกโฟลเดอร์โปรเจค** ไปที่ `C:\xampp\htdocs\`
- **เปลี่ยนชื่อ** เป็น `live-streaming-booking-system`

### 5. เปิดเว็บไซต์
- **เปิดเบราว์เซอร์** ไปที่: http://localhost/live-streaming-booking-system

---

## 🔐 บัญชีทดสอบ

### Admin
- **Username**: admin
- **Password**: password

### Customer  
- **Username**: customer
- **Password**: password

---

## 🆘 แก้ปัญหาที่พบบ่อย

### ปัญหา: ไม่สามารถเชื่อมต่อฐานข้อมูล
**วิธีแก้**:
1. เช็คว่า MySQL ใน XAMPP เปิดอยู่
2. ตรวจสอบการตั้งค่าใน `config/database.php`

### ปัญหา: หน้าเว็บแสดง Error 404
**วิธีแก้**:
1. เช็คว่า Apache ใน XAMPP เปิดอยู่
2. ตรวจสอบว่าโฟลเดอร์อยู่ใน `htdocs` ถูกต้อง

### ปัญหา: ไม่สามารถ Login ได้
**วิธีแก้**:
1. ตรวจสอบว่า import ฐานข้อมูลเรียบร้อย
2. ลองสร้างบัญชีใหม่ผ่านหน้า Register

---

## 📞 ติดต่อขอความช่วยเหลือ
ถ้ามีปัญหาติดตั้ง สามารถติดต่อได้ที่:
- **GitHub Issues**: https://github.com/Chokky2004/ive-streaming-booking-system/issues
- **Email**: [ใส่อีเมลของคุณ]

---

**💡 คำแนะนำ**: สำหรับมือใหม่แนะนำให้ใช้ **วิธีที่ 1 (Download ZIP)** ก่อน เพราะง่ายที่สุด!