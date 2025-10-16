# Architecture Overview

This project is a PHP 8 + MySQL 8 web app with a simple MVC-ish structure:

- Includes: global configuration, helpers
  - `includes/config.php` — session, security, constants, CSRF helpers, env setup
  - `includes/functions.php` — formatting, JSON response, logging, validators
- Models: `models/*.php` — database access using PDO
  - `User`, `Package`, `Booking`, `Payment`
- Web pages (views + controllers): `pages/*`, `pages/admin/*`
- JSON API: `pages/api/*`
- Database: schema + seed — `database/create_database.sql`

## High-level Flow

User (Customer)
1. Register/Login
2. View packages → pick one
3. Create booking (status: `pending`)
4. Upload payment slip (creates/updates `payments.pending` with file)
5. Wait for admin verification → on verify, booking becomes `confirmed`
6. Track status in Profile page

Admin
1. Login → Dashboard
2. Review pending payments, verify/reject
3. Manage bookings (status updates)
4. Manage packages (CRUD w/ soft deactivate)
5. Review reports (monthly revenue, popular packages)

## Data Model (ER)

- users (1) ── (n) bookings
- packages (1) ── (n) bookings
- bookings (1) ── (n) payments
- packages (1) ── (n) equipment_availability (per-day availability ledger)
- users (1) ── (n) payments (as `verified_by`, nullable)

Key tables
- `users(id, username, email, password, role)`
- `packages(id, name, price, equipment_list JSON, is_active)`
- `bookings(id, booking_code UNIQUE, user_id FK, package_id FK, pickup_date, return_date, pickup_time, return_time, rental_days STORED, total_price, status)`
- `payments(id, booking_id FK, amount, slip_image_url, status, paid_at, verified_at, verified_by FK)`
- `equipment_availability(id, package_id FK, date, status ENUM, booking_id FK NULL, created_at)`

## Security & Conventions
- Sessions hardened (httponly, samesite, secure on HTTPS)
- CSRF token required for mutating requests (forms and API)
- Bcrypt for passwords, input sanitization on text fields
- File uploads validated: extension, MIME type, size
- Booking timeline validation enforces pick-up before return, inclusive rental-day calculation, and conflict checks per package
- Booking creation must execute inside a DB transaction and acquire the necessary row-level locks so concurrent customers cannot overbook capacity. Conflicts are logged with package/date metadata for later analysis.

## Logging
- `includes/functions.php::log_event()` writes to `logs/system.log`
- Include request correlation id via `init_request_id()` where appropriate
- Availability conflict events log at `WARNING` level with structured context (package_id, date range, usage vs capacity) to aid monitoring.

## Configuration
- Database config reads env vars in `config/database.php`:
  - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`
- SMTP and site constants in `includes/config.php` (can be migrated to env)

## Build & Run
- Local: `php -S localhost:8080`
- Docker Compose: `docker-compose up -d` (see README)

## Operations & Maintenance
- **Ledger cleanup job:** scheduled (e.g., cron) script deletes availability rows for bookings marked `completed` or `cancelled` beyond a configurable retention period and reports orphaned rows. ใช้ `scripts/cleanup_ledger.php --dry-run` ตรวจสอบก่อน และตั้ง cron ตาม retention ที่กำหนด (เริ่มต้น 30 วันผ่าน `LEDGER_RETENTION_DAYS`).
- **Availability caching:** `/pages/api/availability.php` caches responses per package/date window with short TTL and invalidates cache after booking create/update/cancel events.
- **Database indexes:** ensure `equipment_availability(package_id, date)` and `equipment_availability(booking_id, date)` plus `bookings(status, created_at)` indexes exist to keep availability lookups responsive.
- **Stress tests:** CLI tooling simulates concurrent bookings to verify the locking strategy after deployments. Test scripts should be documented together with expected success/failure counts.
- **Backup guidance:** Documented routine backups (daily snapshot) of `equipment_availability` and dependent tables enable rapid restore if ledger data is corrupted.
