# API Reference

This document describes the JSON API endpoints available under `pages/api/`.

Notes
- Authentication: all endpoints that require a session rely on PHP session cookies. Obtain a session by logging in via the web UI (`pages/login.php`) or by posting to a dedicated login route if you add one. For cURL testing, reuse the cookie file.
- CSRF: protected endpoints require a CSRF token. Supply it in header `X-CSRF-Token` or in JSON field `csrf_token`. You can get a token from any rendered form or from `generate_csrf_token()` after loading the page.
- Envelope: responses use a standard JSON envelope: `{ success: boolean, data?: any, error?: { code, message, ... } }`.

## Health
- GET `/pages/api/health.php`
- Response: `{ success: true, data: { status: 'ok' } }`

Example
```bash
curl -s http://localhost:8080/pages/api/health.php
```

## Packages (list active)
- GET `/pages/api/packages.php`
- Response data: `{ packages: [{ id, name, description, price, equipment_list: string[], image_url?, items: [{ id, name, quantity?, specs?, notes?, image_url?, image_alt }] }] }`
  - `items.image_url` จะเป็นพาธภายใต้ `/uploads/package-items/`
  - `items.image_alt` ให้คำอธิบายสั้นๆ สำหรับรูปภาพ

Example
```bash
curl -s http://localhost:8080/pages/api/packages.php | jq
```

## Bookings & Availability

Create booking
- POST `/pages/api/bookings.php`
- Auth: required (session)
- CSRF: required
- JSON body:
  - `package_id`: number (required)
  - `pickup_date`: string (YYYY-MM-DD, required)
  - `return_date`: string (YYYY-MM-DD, required; must be ≥ pickup)
  - `pickup_time?`: string (HH:MM, defaults `09:00`)
  - `return_time?`: string (HH:MM, defaults `18:00`)
  - `location`: string (required)
  - `notes?`: string
  - `csrf_token?`: string (optional when header absent)
- Response `201`: `{ id, booking_code, rental_days, pickup_date, return_date, pickup_time, return_time, total_price, status }`
- Errors return `{ success: false, error: { code: 'AVAILABILITY_CONFLICT' | 'VALIDATION_ERROR' | ... } }`

Example (with cookie jar and CSRF header)
```bash
# assuming you have an authenticated cookie in cookies.txt and a token in $CSRF
curl -s \
  -b cookies.txt -c cookies.txt \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $CSRF" \
  -d '{
        "package_id": 1,
        "pickup_date": "2025-12-01",
        "return_date": "2025-12-03",
        "pickup_time": "09:00",
        "return_time": "18:00",
        "location": "Bangkok",
        "notes": "Livestream product launch"
      }' \
  http://localhost:8080/pages/api/bookings.php | jq
```

List my bookings
- GET `/pages/api/bookings_me.php`
- Auth: required

```bash
curl -s -b cookies.txt -c cookies.txt \
  http://localhost:8080/pages/api/bookings_me.php | jq
```

Lookup availability
- GET `/pages/api/availability.php?package_id={id}`
- Auth: optional (read-only)
- Query params:
  - `package_id`: number (required)
  - `start?`: YYYY-MM-DD lower bound (defaults today, Asia/Bangkok)
  - `end?`: YYYY-MM-DD upper bound (defaults +60 days)
  - `fresh?`: set to `1` เพื่อข้าม cache (ตัวเลือก)
- Response data:
  - `package_id`: หมายเลขแพ็กเกจ
  - `capacity`: จำนวนการจองสูงสุดต่อวันสำหรับแพ็กเกจนั้น
  - `window`: ช่วงวันที่ครอบคลุม (`start`, `end`)
  - `reservations`: รายการ `{ date, status, booking_id?, booking_code? }`
  - `usage`: แผนที่ `{ YYYY-MM-DD: reservedCount }`
  - `cache`: `{ ttl_seconds, fresh }` (fresh=false หมายถึงตอบกลับจาก cache)
- ใช้ response เพื่อไฮไลต์วันที่เต็ม/ว่างในปฏิทิน ส่วน cache จะหมดอายุอัตโนมัติภายใน `AVAILABILITY_CACHE_TTL` (ค่าเริ่มต้น 120 วินาที)

## Payments

Create payment metadata (no file upload via API; slip upload is done in `pages/payment.php`)
- POST `/pages/api/payments.php`
- Auth: required
- CSRF: required
- JSON body:
  - `booking_id?`: number OR `booking_code?`: string
  - `amount`: number
  - `payment_method?`: string (default `bank_transfer`)
  - `transaction_ref?`: string
  - `notes?`: string
- Response `201`: `{ id, booking_id, status }`

Admin: verify/reject payment
- PATCH `/pages/api/payments_update.php`
- Auth: admin required
- CSRF: required
- JSON body:
  - `id`: payment id
  - `status`: `verified` | `rejected`
  - `notes?`: string
- Response: `{ payment: { id, status, verified_at, verified_by, ... } }`

Example
```bash
curl -s -b cookies.txt -c cookies.txt \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $CSRF" \
  -X PATCH \
  -d '{ "id": 123, "status": "verified" }' \
  http://localhost:8080/pages/api/payments_update.php | jq
```

## Common Errors
- `METHOD_NOT_ALLOWED` (405): wrong HTTP method
- `UNAUTHORIZED`/`FORBIDDEN` (401/403): missing session or insufficient role
- `CSRF_INVALID` (401): missing/invalid CSRF token
- `VALIDATION_ERROR` (400): input validation failed
- `AVAILABILITY_CONFLICT` (409): requested dates overlap existing reservation
- `NOT_FOUND` (404): resource not found
- `INTERNAL_ERROR` (500): unhandled server error
