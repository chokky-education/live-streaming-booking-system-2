# ระบบจองอุปกรณ์ Live Streaming

## 📋 รายละเอียดโครงการ
- **ชื่อโครงการ**: ระบบจองอุปกรณ์ Live Streaming
- **ประเภท**: เว็บแอปพลิเคชันสำหรับบัณฑิตนิพนธ์
- **พัฒนาโดย**: นายวุฒิพงษ์ ไชยช่วย และ นายนรเบศร ธนิสาเจริญ
- **สถาบัน**: มหาวิทยาลัยราชภัฏบ้านสมเด็จเจ้าพระยา

## 🚀 วิธีเริ่มใช้งาน

### ข้อกำหนดระบบ
- PHP 8.0 หรือสูงกว่า
- MySQL 8.0 หรือสูงกว่า
- Web Browser ที่ทันสมัย

### การติดตั้ง (แบบโลคัล)
1. **Clone โปรเจค**:
   ```bash
   git clone https://github.com/YOUR_USERNAME/live-streaming-booking-system.git
   cd live-streaming-booking-system
   ```

2. **ตั้งค่าฐานข้อมูล/แอป**:
   ```bash
   # (ทางเลือก) ตั้งค่า environment variables ให้ PHP อ่านได้
   export DB_HOST=localhost
   export DB_NAME=live_streaming_booking
   export DB_USER=root
   export DB_PASS=""
   # (ทางเลือก) เปิดใช้งานตัวเลือกสถานที่ผ่าน Google Maps
   export GOOGLE_MAPS_API_KEY="YOUR_GOOGLE_MAPS_API_KEY"
   # หากไม่ตั้งค่า ระบบจะใช้แผนที่ OpenStreetMap ผ่าน Leaflet อัตโนมัติ
   ```

3. **สร้างฐานข้อมูล**:
   ```bash
   # Import SQL file
   mysql -u root -p < database/create_database.sql
   ```

4. **เริ่มเซิร์ฟเวอร์**:
   ```bash
   ./scripts/start.sh
   ```
   หรือ
   ```bash
   php -S localhost:8080
   ```

5. **เปิดเบราว์เซอร์** ไปที่: `http://localhost:8080`

### การติดตั้ง (ด้วย Docker Compose)
1. ติดตั้ง Docker และ Docker Compose
2. รันคำสั่ง
   ```bash
   docker-compose up -d
   ```
3. เปิดเว็บ: `http://localhost:8080`

Compose นี้จะ:
- รัน PHP + Apache โดยแมปซอร์สโค้ดเข้ากับ `/var/www/html`
- รัน MySQL 8 และ import `database/create_database.sql` อัตโนมัติ
- ตั้งค่า env สำหรับ DB ให้กับ container app

### สคริปต์ช่วยงาน
- `scripts/db_import.sh` นำเข้าไฟล์ SQL โดยอ่านค่าจากตัวแปรแวดล้อม (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`)
- `scripts/start.sh` เริ่ม PHP dev server (`PORT` กำหนดพอร์ตได้)

### 🛠 งานบำรุงรักษา
- `scripts/cleanup_ledger.php` — ล้าง ledger ของ booking ที่เสร็จสิ้น/ยกเลิกเกินช่วงเวลา retention และรายงาน orphan (`php scripts/cleanup_ledger.php --dry-run` เพื่อตรวจสอบก่อนรันจริง)
- `scripts/tests/booking_concurrency_smoke.php` — จำลองการจองพร้อมกันหลายผู้ใช้เพื่อตรวจสอบ locking (`php scripts/tests/booking_concurrency_smoke.php 1 2025-10-18 2025-10-19 6`)
- `scripts/tests/availability_cache_smoke.php` — ตรวจสอบการตอบกลับของ API ความพร้อมและสถานะ cache (ต้องเปิดเซิร์ฟเวอร์ `php -S localhost:8080`) 
- `scripts/tests/booking_concurrency_stress.php` — รัน stress test หลายรอบเพื่อดูสถิติรวม (`php scripts/tests/booking_concurrency_stress.php 1 2025-10-18 2025-10-19 12 --rounds=5`)
- `scripts/backup_ledger.php` — สำรอง/กู้คืนข้อมูล ledger (`php scripts/backup_ledger.php backup` หรือ `php scripts/backup_ledger.php restore backups/ledger/ledger_backup_xxx.json`) — สคริปต์จะสร้างโฟลเดอร์สำรองให้เองหากยังไม่เกิดขึ้น
- แนะนำตั้ง cron/งานมือตามความเหมาะสม พร้อม snapshot ตาราง `equipment_availability` รายวันและเกณฑ์การเก็บรักษา

## 🔐 บัญชีทดสอบ

### Admin (ผู้ดูแลระบบ)
- **Username**: `admin`
- **Password**: `password`
- **เข้าถึง**: Admin Dashboard, จัดการการจอง, ตรวจสอบการชำระเงิน

### Customer (ลูกค้า)
- **Username**: `customer`
- **Password**: `password`
- **เข้าถึง**: จองอุปกรณ์, ชำระเงิน, ดูประวัติ

## 📦 แพ็คเกจอุปกรณ์

### 1. แพ็คเกจพื้นฐาน - 2,500 บาท/วัน
- กล้อง DSLR/Mirrorless 1 ตัว
- ไมโครโฟน 1 ตัว

### 2. แพ็คเกจมาตรฐาน - 4,500 บาท/วัน
- กล้อง DSLR/Mirrorless 2 ตัว
- ไมโครโฟน 2 ตัว
- ไฟ LED 2 ชุด

### 3. แพ็คเกจพรีเมี่ยม - 7,500 บาท/วัน
- กล้อง DSLR/Mirrorless 3 ตัว
- ไมโครโฟน 3 ตัว
- ไฟ LED 4 ชุด
- ขาตั้งกล้อง 3 ชุด
- Switcher/Mixer 1 ตัว

## 🌟 ฟีเจอร์หลัก

### สำหรับลูกค้า
- ✅ สมัครสมาชิก/เข้าสู่ระบบ
- ✅ เลือกและจองแพ็คเกจอุปกรณ์
- ✅ เลือกสถานที่ใช้งานผ่านการปักหมุดบนแผนที่ (Google Maps หรือ fallback เป็น OpenStreetMap)
- ✅ ชำระเงินด้วยการอัปโหลดสลิป
- ✅ ดูประวัติการจองและสถานะ
- ✅ จัดการข้อมูลส่วนตัว

### สำหรับ Admin
- ✅ Dashboard พร้อมสถิติ
- ✅ จัดการการจองทั้งหมด
- ✅ ตรวจสอบและยืนยันการชำระเงิน
- ✅ จัดการแพ็คเกจอุปกรณ์
- ✅ ดูข้อมูลลูกค้า
- ✅ รายงานและกราฟสถิติ

## 🔧 เทคโนโลยีที่ใช้
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Backend**: PHP 8.x
- **Database**: MySQL 8.x
- **Security**: bcrypt, CSRF Protection, Input Validation
- **Charts**: Chart.js

## 📱 การใช้งาน

### 1. หน้าแรก
- แสดงแพ็คเกจทั้ง 3 แบบ
- ข้อมูลบริษัทและการติดต่อ
- ลิงก์เข้าสู่ระบบ

### 2. การจองอุปกรณ์
1. เลือกแพ็คเกจที่ต้องการ
2. เลือกวันที่ใช้งาน
3. กรอกข้อมูลการจอง
4. ยืนยันการจอง

### 3. การชำระเงิน
1. ดูสรุปการจอง
2. โอนเงินมัดจำ 50%
3. อัปโหลดสลิปการโอนเงิน
4. รอการตรวจสอบ

## 🌐 การแชร์เว็บไซต์

### วิธีที่ 1: ใช้ ngrok (แนะนำ)
```bash
# ติดตั้ง ngrok
brew install ngrok/ngrok/ngrok

# เริ่มเซิร์ฟเวอร์
php -S localhost:8080 &

# สร้าง tunnel
ngrok http 8080
```

### วิธีที่ 2: ใช้ Local Network
```bash
# หา IP Address
ifconfig | grep "inet "

# เริ่มเซิร์ฟเวอร์
php -S 0.0.0.0:8080

# เพื่อนสามารถเข้าถึงได้ที่: http://[YOUR_IP]:8080
```

## 📞 การติดต่อ
- **Email**: info@livestreaming.com
- **โทร**: 02-123-4567
- **Line**: @livestreamingpro

## 📄 License
© 2024 Live Streaming Pro. สงวนลิขสิทธิ์.

---
**พัฒนาเพื่อการศึกษา - บัณฑิตนิพนธ์ระดับปริญญาตรี**

## 📚 เอกสารสำหรับนักพัฒนา
- Contributor Guide: `AGENTS.md`
- AI Safety & Workflow Guide: `CLAUDE.md`
- API: `docs/api.md`
- สถาปัตยกรรม: `docs/architecture.md`
- แผนงาน/ปรับปรุง: `docs/backlog.md`
- Postman collection: `docs/postman_collection.json`

โฟลเดอร์สำคัญสำหรับส่งมอบ:
- `includes/` ค่าคงที่/ยูทิลิตี้
- `models/` โมเดล PDO
- `pages/` หน้าเว็บผู้ใช้ และ `pages/admin/` สำหรับแอดมิน
- `pages/api/` JSON API
- `uploads/` (ถูก .gitignore) — มี `.gitkeep` ให้โฟลเดอร์ไม่หาย
- `logs/` (ถูก .gitignore) — มี `.gitkeep`
