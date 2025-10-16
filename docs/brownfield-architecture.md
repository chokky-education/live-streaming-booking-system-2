# Live Streaming Equipment Booking – Brownfield Architecture & Service Analysis

Date: 2025-09-25
Version: 1.0
Author: Architect (BMad)

## Introduction

This document captures the current state of the PHP/MySQL booking system for live streaming equipment. It maps actual code, data schema, runtime behaviors, and notable constraints/technical debt to help agents plan service/API enhancements safely.

Scope: Comprehensive project baseline (no PRD provided). Use this as the foundation for a brownfield PRD and subsequent stories.

## Quick Reference – Key Files and Entry Points

- Main public entry: `index.php:1` (redirects to `index.html`)
- Global config/session/security: `includes/config.php:1`
- Database connection (PDO/MySQL): `config/database.php:1`
- Common helpers: `includes/functions.php:1`
- Domain models:
  - `models/User.php:1`
  - `models/Package.php:1`
  - `models/Booking.php:1`
  - `models/Payment.php:1`
- Customer pages:
  - `pages/login.php:1`, `pages/register.php:1`, `pages/profile.php:1`
  - `pages/booking.php:1`, `pages/payment.php:1`
- Admin pages: `pages/admin/*.php` (dashboard, bookings, payments, packages, reports)
- Database schema and seed: `database/create_database.sql:1`
- Logs: `logs/system.log:1`
- Uploads (payment slips): `uploads/slips`

## High-Level Architecture (Reality)

- Pattern: Server-rendered PHP pages (no dedicated REST API layer yet)
- Runtime: PHP with sessions; timezone `Asia/Bangkok`
- Data store: MySQL via PDO (UTF8MB4, exceptions enabled, prepared statements)
- Auth: Username/email + bcrypt password; role-based (`user`, `admin`)
- Security controls present: CSRF token helpers, input sanitization, prepared statements
- Email config placeholders for SMTP (Gmail) exist in config, not validated for use here

### Repository Structure (Actual)

```
project-root/
├── includes/            # Global config + helpers
├── config/              # DB connection class
├── models/              # Domain models (User, Package, Booking, Payment)
├── pages/               # Customer-facing pages
│   └── admin/           # Backoffice/admin pages
├── database/            # SQL schema + seed
├── logs/                # System log file
├── uploads/             # Uploaded payment slips
├── index.php|index.html # Entry / landing
└── AGENTS.md, .bmad-core/ # BMAD method assets
```

## Module Overview

### includes/config.php
- Starts session, sets error reporting (currently enabled), timezone, defines constants (site, upload path, max file size, VAT rate), loads DB, CSRF helpers, and access control helpers (`require_login`, `require_admin`).

### includes/functions.php
- Utilities: currency/date formatting (Thai), booking code generation, validators (email/phone), price calc with VAT, package info, system logging to `logs/system.log`, JSON response helper, and file upload validation (extension/size).

### Database (config/database.php)
- `Database::getConnection()` returns PDO with attributes: ERRMODE_EXCEPTION, FETCH_ASSOC, no emulate prepares.

### Models
- User: create, login (verify), existence check, get/update, password change, etc.
- Package: CRUD-like operations, getActive, getById, JSON `equipment_list`.
- Booking: create (unique `booking_code`), get by id/code, list for user/admin, update status, cancel/confirm, stats, by-date range, check daily availability per package.
- Payment: records tied to bookings with status lifecycle; slip storage path in pages.

### Pages
- booking.php: guards `require_login`, loads packages, validates booking form, CSRF verification, availability check, calculates price, creates booking, redirects to payment page.
- payment.php: displays booking/payment details, computes deposit/remaining amounts, slip upload UI (verify admin later).
- profile.php: shows user data and bookings, allows profile update and password change with CSRF.
- admin/*.php: dashboards and management (bookings, payments verification, packages catalogs, reports).

## Data Model Summary (from SQL)

- users(id, username UNIQUE, password bcrypt, email UNIQUE, phone, first_name, last_name, role ENUM['user','admin'], created_at, ...)
- packages(id, name, description, price DECIMAL, equipment_list JSON, image_url, is_active, created_at)
- bookings(id, booking_code UNIQUE, user_id FK, package_id FK, pickup_date, return_date, pickup_time, return_time, rental_days STORED, location, notes, total_price, status ENUM['pending','confirmed','cancelled','completed'], created/updated)
- payments(id, booking_id FK, amount, method, slip_image_url, transaction_ref, status ENUM['pending','verified','rejected'], paid_at, verified_at, verified_by FK, notes, created/updated)
- equipment(id, name, type, brand, model, daily_price, is_available, package_id FK, image_url, created/updated)
- system_logs(id, user_id FK, action, description, ip, user_agent, created_at)

Indexes align with common queries (status, dates, codes, foreign keys).

## Security Posture (Current)

- CSRF: Token helpers exist; major POST flows (booking, profile update, password change) include verification. Action: ensure all POST forms/pages apply `verify_csrf_token` consistently.
- XSS: Input sanitized on receipt; ensure output escaping in templates where user inputs render (manual review per page recommended).
- AuthZ: `require_login` and `require_admin` helpers gate access; verify admin pages consistently include these.
- Passwords: bcrypt via `password_hash`/`password_verify`.
- Sessions: Session started globally; recommend session ID regeneration on login and secure cookie flags in production.

## Observations, Constraints, Technical Debt

1) Error display enabled in production config (`includes/config.php`: `display_errors=1`).
   - Risk: Information leakage. Action: disable in production and log to files only.

2) Secret/config in code (`config/database.php`, SMTP in `includes/config.php`).
   - Risk: Secrets in repo. Action: move to environment variables or `.env` (dotenv) and keep samples only.

3) File upload validation checks extension+size only.
   - Risk: Spoofed MIME. Action: validate MIME via `finfo_file`, randomize filenames, and restrict storage path.

4) Architecture is page-based; no consistent HTTP API contract.
   - Constraint: Harder to integrate external systems or mobile apps.
   - Option: Introduce a lightweight JSON API layer alongside pages.

5) Logging & monitoring minimal.
   - Action: standardize log levels/contexts, consider request IDs, and rotate logs.

6) Session security hardening.
   - Action: Regenerate session ID on login, set cookie flags (httponly, secure, samesite), add login throttling/lockout.

## Integration Points

- External: Potential SMTP (Gmail) defined but not enforced/used broadly in code inspected.
- Internal: Pages ↔ Models via PDO. Payment slip uploads to `uploads/slips` and reviewed in admin.

## Development & Runbook (Local)

Prereqs: PHP 8.x (recommended), MySQL 8.x (or 5.7+), Composer not required, Web server (Apache/Nginx) or `php -S`.

Setup
1. Create database and schema: run `database/create_database.sql:1` in MySQL.
2. Configure DB connection in `config/database.php:1` (dev only). For prod, prefer env vars.
3. Serve project root via web server; ensure `uploads/slips` writable.
4. Login with seeded admin: `admin / password` (bcrypt hash in seed represents "password"). Change immediately.

Notes
- Timezone: Asia/Bangkok; verify cron/scheduler if added later.
- Logs at `logs/system.log`.

## Path to Service/API Enhancement (Recommendations)

Short-term (non-breaking)
- Add JSON endpoints incrementally for booking lifecycle while keeping pages (e.g., `/api/bookings`, `/api/payments`).
- Introduce response wrapper and consistent error schema; reuse existing models.
- Harden security: CSRF everywhere, MIME validation, session regeneration.

Medium-term
- Abstract DB access behind a service layer (classes/functions) to reduce page-level direct model coupling.
- Introduce authentication middleware for API endpoints (JWT or session-based tokens).
- Move config/secrets to environment variables.

Long-term
- Separate API service from page app (modular structure), or migrate to a microservice if needed.
- Add OpenAPI spec and a Postman collection. Generate SDKs if helpful.

## Open Questions (for PRD scoping)

- What exact service/API enhancements are required (new endpoints, performance targets, DB changes)?
- Any backward-compat constraints for existing web clients?
- Third-party integrations planned (payments, notifications, etc.)?
- Operational SLOs: latency, error rate, throughput, availability?

## Appendices

### Key Queries & Indices
- Availability check: `models/Booking.php:checkPackageAvailability`
- Booking history & admin listings with indices on status/date/code.

### Forms Using CSRF (Sample)
- Booking create: `pages/booking.php:POST`
- Profile update and password change: `pages/profile.php:POST`

### Helpful Constants
- `VAT_RATE`, `MAX_FILE_SIZE`, `UPLOAD_PATH`, `CSRF_TOKEN_NAME` in `includes/config.php:1`

---

This document reflects the current codebase reality. Use it to inform the brownfield PRD and story creation. Future deltas should be appended in a change log.
