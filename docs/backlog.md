# Backlog & Known Improvements

This backlog lists tasks and enhancements to help the next developer plan work.

## High Priority
1) Payment slip handling
   - [x] Save uploads using absolute server path
   - [x] Remove old slip file on re-upload
   - [ ] Add optional virus/malware scan integration (e.g., ClamAV) in production

2) Environment & config
   - [x] DB config via env (already supported in `config/database.php`)
   - [ ] Migrate SMTP settings in `includes/config.php` to env variables
   - [x] Add Docker Compose for local dev

3) Security
   - [ ] Add rate limiting / lockout to login to mitigate brute-force
   - [ ] Add audit trail for admin actions (payments verify/reject, booking status changes)

4) Availability logic
   - [x] Replace hard-coded `max_bookings = 1` with per-package capacity rules using `equipment_availability`
   - [ ] Implement automatic conflict resolution when bookings are cancelled/modified (free up ledger rows)

5) API & Frontend
   - [x] Document API (`docs/api.md`)
   - [ ] Optional: add login/logout API to make API-first clients easier
   - [ ] Optional: support slip upload via API with multipart/form-data

6) Package inventory transparency
   - [x] Add admin UI to create/update/remove package items (name, quantity, specs, optional notes, image)
   - [x] Store validated item images/specs and surface them in customer views and `/api/packages`
   - [ ] Monitor image storage health (quota, cleanup) once in production

7) Multi-day booking enablement
   - [ ] Roll out booking schema migration (pickup/return columns + `rental_days` STORED) across environments
   - [ ] Launch availability calendar UI powered by `GET /pages/api/availability.php`
   - [ ] Finalize rental pricing rules and document CS uplift scenarios (weekend/holiday adjustments)
   - [ ] Add automated tests covering overlapping reservations and rental-day pricing cases

8) System reliability & ledger health
   - [ ] Story 1.8 — Implement booking transaction locking and over-capacity logging
   - [ ] Story 1.9 — Automate ledger cleanup and orphan detection workflows
   - [ ] Story 1.10 — Enhance availability API performance, caching, and integrity checks
   - [ ] Story 1.11 — Establish concurrency stress tests and backup strategy for availability data

9) Observability
   - [ ] Log rotation and retention policy for `logs/system.log`
   - [ ] Add basic metrics endpoint/collector for dashboard health

10) QA
   - [ ] Add end-to-end smoke tests for booking → payment → verify

11) Deployment & Ops
   - [ ] Automate multi-day booking migration/ledger seeding as part of release playbook
   - [ ] Add release checklist item to validate `equipment_availability` vs `bookings` counts post-deploy
   - [ ] Coordinate DevOps handoff for Story 1.7 (confirm ownership, track completion in stand-up)
   - [ ] Integrate login/payment smoke script (`scripts/tests/login_payment_smoke.php`) into CI
   - [ ] Integrate admin package smoke script (`scripts/tests/admin_package_smoke.php`) into CI

## Medium Priority
1) Email notifications
   - [ ] Send email to customer when booking created/confirmed, and payment verified/rejected

2) Admin UX
   - [ ] Bulk actions for verifying payments
   - [ ] Export CSV for bookings/payments/reports

3) Content & I18n
   - [ ] Configurable bank transfer info (from admin panel)
   - [ ] Multi-language support (TH/EN)

## Low Priority
1) Assets & CDN
   - [ ] Use versioned static assets and optional CDN

2) Data
   - [ ] Customer segments, repeat booking rate, package utilization trend

## Upcoming Sprint Candidates
- Story 1.5 — Security Hardening & Configuration Management (Ready for Review)
- Story 1.6 — Admin Package Contents Module (Ready for Review)
- Story 1.8 — Booking Concurrency Hardening (Draft)
- Story 1.9 — Ledger Cleanup Automation (Draft)
- Story 1.10 — Availability Integrity & Performance Enhancements (Draft)
- Story 1.11 — Concurrency Stress Tests & Backup Strategy (Draft)
