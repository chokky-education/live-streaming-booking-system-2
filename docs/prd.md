# Live Streaming Booking Brownfield Enhancement PRD

Version: v2.2
Date: 2025-10-03
Owner: Product Manager (BMAD)

## 1. Intro Project Analysis and Context

- Analysis Source: Document-project output available at: `docs/brownfield-architecture.md:1`
- Current Project State (summary): Server-rendered PHP application using PDO/MySQL for a live streaming equipment booking system. Key domains: users, packages, bookings, payments; admin backoffice for verification/reporting. Security controls exist (CSRF helpers, prepared statements, bcrypt) but require hardening for production. No formal JSON API layer yet.

### Available Documentation Analysis
- Tech Stack Documentation ✓ (from brownfield architecture)
- Source Tree/Architecture ✓ (from brownfield architecture)
- Coding Standards: Partial
- API Documentation: Not present (to be created)
- External API Documentation: N/A currently
- UX/UI Guidelines: N/A
- Technical Debt Documentation ✓ (from brownfield architecture)

## 2. Enhancement Scope Definition

Proposed enhancement focus (confirmed with stakeholders):
- New Feature Addition: Introduce foundational JSON API for bookings and payments
- Admin UX Enhancement: Allow admins to manage detailed package contents and publish them for end users
- Booking Experience Enhancement: Support multi-day rentals with explicit pick-up/return scheduling, availability visibility, and dynamic pricing
- Integration with New Systems: Optional future external notification/email services
- Performance/Scalability Improvements: Minor (query/index usage already reasonable)
- Bug Fix and Stability Improvements: Security hardening and config management

## 3. Goals and Non-Goals

Goals
- Provide a stable JSON API layer for core booking/payment flows without breaking existing pages
- Harden security (sessions, uploads, secrets management)
- Maintain backward compatibility with current web UI
- Give customers clear visibility into package contents through an admin-managed inventory module
- Enable flexible multi-day booking flows with accurate availability checks and pricing transparency

Non-Goals
- Full UI rewrite
- Major database schema redesign
- Payment gateway integration (out of scope unless requested)

## 4. Users and Use Cases

Primary Users
- Existing web UI (internal consumption of API as we migrate)
- Admin staff (payment verification; future admin API)
- Customers booking live-streaming packages over multiple days

Use Cases
- Create and retrieve bookings via JSON
- Upload payment slip metadata and manage verification via API
- Retrieve user booking history
- Maintain package inventories (admin) and display package contents to customers
- Schedule pick-up and return windows with automatic rental-day pricing
- View availability across date ranges before confirming a booking

## 5. Requirements

5.1 Functional Requirements
1. Provide read endpoints for packages and bookings (auth required).
2. Provide create booking endpoint that enforces availability and CSRF/session auth.
3. Provide payment endpoints to submit slip metadata and mark verification status (admin only).
4. Maintain existing page flows; API is additive.
5. Allow admins to CRUD package contents (items/assets) with fields for name, quantity, specs, and image, then expose the composed package details to end users via UI/API.
6. Capture pick-up and return dates/times for each booking, calculate rental days, and surface the values consistently across UI/API.
7. Prevent overlapping reservations by validating package availability across the requested date range, persisting state in the `equipment_availability` ledger table.
8. Provide an availability lookup endpoint/UI widget so customers can verify open dates before booking.

5.2 Non-Functional Requirements
- Security: CSRF on forms, session ID regeneration on login, secure cookies, MIME validation for uploads, role-based authorization for admin.
- Reliability: Error handling with consistent JSON envelope `{ success, data, error }`.
- Performance: P95 < 300ms for read endpoints under normal load; < 600ms for booking create.
- Observability: Log key actions with request IDs and outcomes.
- Data Integrity: Package contents must remain consistent across admin UI, API responses, and database state (auditability of changes).
- Media Handling: Package item images must be stored securely, validated (type/size), and optimized for customer delivery.
- Temporal Accuracy: Date/time calculations must honor application timezone rules and ensure `rental_days` reflects inclusive ranges.
- Concurrency & Capacity: Booking workflow must enforce per-package capacity using transactional locking so that concurrent requests cannot overbook; failed attempts log structured diagnostics.
- Ledger Maintenance: Availability ledger requires automated cleanup for completed/cancelled bookings, orphan detection, and documented backup/restore procedures.
- Availability Performance: `/pages/api/availability.php` must return within P95 < 200ms under typical load via caching/index optimisation while exposing `capacity` and daily usage metadata.
- Operational Testing: Provide repeatable stress tests simulating simultaneous bookings and include them in release readiness checklists.

5.3 Out of Scope (for this epic)
- Third-party payment gateway integration
- Mobile app clients

## 6. Technical Constraints and Integration Requirements

Existing Technology Stack
- Languages: PHP 8.x, SQL (MySQL)
- Frameworks: Native PHP (pages + models), no framework
- Database: MySQL (UTF8MB4) via PDO
- Infrastructure: Traditional web hosting (Apache/Nginx) assumed
- External Dependencies: Optional SMTP (Gmail) not critical to this epic

Integration Approach
- Database Integration Strategy: Add pick-up/return columns to `bookings`, backfill data, and persist per-day reservations in the `equipment_availability` table (required)
- API Integration Strategy: Add PHP endpoints under `pages/api/*.php` returning JSON; share auth via session; add CSRF for state changes
- Frontend Integration Strategy: Keep existing pages while enhancing booking form for dual-date selection and availability preview
- Testing Integration Strategy: Endpoint-level tests (manual or lightweight scripts); verify against existing pages and new multi-day workflows

Code Organization and Standards
- File Structure Approach: Use `pages/api/` for endpoints; reuse `includes/config.php` and models
- Naming Conventions: snake_case parameters in JSON; endpoints use `*.php` files under `pages/api`
- Coding Standards: Prepared statements, centralized JSON response helper, consistent error codes
- Documentation Standards: Update `docs/api.md` with examples; keep PRD endpoint list in sync
- Domain Logic: Extend `models/Booking.php` (and related services) to encapsulate date-range validation, pricing rules, and availability marking

Deployment and Operations
- Build Process Integration: None; deploy PHP files
- Deployment Strategy: Standard file deploy; ensure `uploads/` perms; backup DB; run booking-date migration prior to rolling out UI/API changes
- Monitoring and Logging: Extend `log_event` with request IDs; log API errors
- Configuration Management: Move secrets to environment variables in production

Risk Assessment and Mitigation
- Technical Risks: Security regressions → Mitigate with CSRF checks and session hardening
- Data Migration Risks: Booking history backfill errors → Mitigate with pre/post migration validation scripts and backups
- Integration Risks: Endpoint conflicts → Namespaced under `/api`
- Deployment Risks: Config differences → Use env vars and verify on staging
- Mitigation Strategies: Progressive rollout, detailed test checklist

## 7. Epic and Story Structure

Epic Structure Decision: Single epic with incremental stories (minimize risk and keep pages working)

### Epic 1: Establish JSON API Layer and Security Hardening

Epic Goal
- Deliver foundational API for bookings/payments with no regression to current pages

Integration Requirements
- Share models and auth/session with existing app; ensure CSRF/session hardening in both pages and API

Stories (proposed order — please confirm this sequence)

Story 1.1 Introduce API scaffolding and response envelope
- As a developer,
  I want a namespaced `/api` with a standard JSON response wrapper,
  so that future endpoints are consistent and easy to consume.
Acceptance Criteria
  1: `/api/health` returns `{ success: true, data: { status: 'ok' } }`
  2: Shared JSON helper used; errors return `{ success: false, error: { code, message } }`
Integration Verification
  IV1: Existing pages continue to function
  IV2: Logs include request IDs for API calls
  IV3: P95 for `/api/health` < 50ms

Story File Reference: `docs/stories/1.1.story.md`

Story 1.2 Read endpoints for packages and bookings
- As an authenticated user,
  I want to fetch my bookings and available packages via JSON,
  so that I can build or test clients.
Acceptance Criteria
  1: `GET /api/packages` returns active packages
  2: `GET /api/bookings/me` returns authenticated user’s bookings
  3: Unauthorized returns 401 with JSON error
Integration Verification
  IV1: DB indices used (no table scans on typical queries)
  IV2: Session-based auth respected
  IV3: Response times within NFR

Story File Reference: `docs/stories/1.2.story.md`

Story 1.3 Create booking endpoint with availability checks
- As an authenticated user,
  I want to create a booking through the API,
  so that I can integrate programmatically while enforcing business rules.
Acceptance Criteria
  1: `POST /api/bookings` validates CSRF, payload, and availability
  2: On success returns booking with `booking_code`
  3: Errors return clear codes/messages (validation, availability)
Integration Verification
  IV1: Date/time validation matches page logic
  IV2: Booking code uniqueness maintained
  IV3: No regression to page-based booking

Story File Reference: `docs/stories/1.3.story.md`

Story 1.4 Payment submission/verification endpoints
- As a user/admin,
  I want to submit payment slip metadata and verify/reject payments via API,
  so that the payment workflow can be managed programmatically.
Acceptance Criteria
  1: `POST /api/payments` accepts metadata; file upload via page remains; future API upload optional
  2: `PATCH /api/payments/{id}` (admin) verifies/rejects with audit trail
  3: Authorization enforced (admin-only actions)
Integration Verification
  IV1: Backoffice pages still show accurate statuses
  IV2: Logging includes action, actor, and booking/payment ids
  IV3: No schema changes required

Story File Reference: `docs/stories/1.4.story.md`

Story 1.5 Security hardening and config management
- As an operator,
  I want secure sessions and configuration practices,
  so that the system is safe for production use.
Acceptance Criteria
  1: Regenerate session ID on login; set cookie flags (httponly, secure, samesite)
  2: Validate MIME using `finfo_file` on uploads; randomize filenames; store outside webroot or enforce strict serving
  3: Move secrets (DB/SMTP) to environment variables in production
Integration Verification
  IV1: All POST forms verify CSRF
  IV2: Upload validation rejects incorrect MIME
  IV3: No functional regressions on pages

Story File Reference: `docs/stories/1.5.story.md`

Story 1.6 Admin package contents module
- As an admin staff member,
  I want to manage the list of items included in each package,
  so that customers clearly understand what equipment comes with every booking option.
Acceptance Criteria
  1: Admin UI supports creating, updating, and removing package items (name, quantity, specs, optional notes, image) tied to each package.
  2: Package data persists items in the database with audit trail for changes and stored image paths/metadata.
  3: `GET /api/packages` responses include an `items` array (with specs and image URL) for each package.
  4: Customer-facing package views display the curated item list with specs and image thumbnail.
Integration Verification
  IV1: Existing package booking flows remain unaffected.
  IV2: API and web views show identical item information after updates.
  IV3: Invalid or missing item data is gracefully handled with admin-facing validation errors.

Story File Reference: `docs/stories/1.6.story.md`

Story 1.7 Multi-day booking workflow and availability
- As a customer,
  I want to reserve equipment with explicit pick-up and return windows,
  so that longer rentals are priced and scheduled correctly.
Acceptance Criteria
  1: Booking UI accepts `pickup_date`, `return_date`, `pickup_time`, `return_time`, and surfaces rental day count before submission.
  2: `POST /pages/api/bookings.php` (and corresponding page submission) persists the new fields, calculates `rental_days` and `total_price` via the pricing matrix, and returns them in the response.
  3: Availability checks prevent overlaps across the requested date range; conflicts surface actionable messaging.
  4: Optional `GET /pages/api/availability.php` (or equivalent) returns unavailable dates for a package, enabling client-side calendar highlighting.
  5: Data migration backfills historical bookings with pick-up/return values to avoid null columns and seeds corresponding `equipment_availability` rows.
Integration Verification
  IV1: Booking confirmations, emails (if any), and admin views display the new scheduling fields.
  IV2: Rental pricing matches agreed-upon day-multiplier rules for sample scenarios.
  IV3: Legacy single-day bookings migrated correctly; regression tests cover booking/payment flows.

Source Reference: `../add module.txt`

Pricing & Calendar Rules
- Base price applies to first day; day 2 adds +40%, day 3–6 add +20% per day, day 7+ add +10% per extra day (mirrors `add module.txt` examples).
- Weekend/holiday surcharge: +10% per rental day overlapping Saturday/Sunday or configured holiday list.
- Partial-day pickups (before 12:00) count as full day; returns after 18:00 incur late fee flag for manual follow-up.
- Display availability in Asia/Bangkok timezone; disable past dates and automatically adjust return date to be ≥ pickup.
- Calendar UI highlights unavailable ranges from `equipment_availability`, with tooltips for existing booking codes when staff authenticated.

### API Endpoint Alignment (from `docs/api.md`)

Canonical endpoint paths and behavior to align implementation and tests:
- Health: `GET /pages/api/health.php` → `{ success: true, data: { status: 'ok' } }`
- Packages (list): `GET /pages/api/packages.php` → returns active packages including `items[]` (with optional image URLs under `/uploads/package-items/`)
- My bookings: `GET /pages/api/bookings_me.php` → returns authenticated user’s bookings
- Create booking: `POST /pages/api/bookings.php` → validates CSRF and date-range availability; returns `{ id, booking_code, total_price, rental_days, pickup_date, return_date, status }`
- Create payment metadata: `POST /pages/api/payments.php` → records metadata (file upload remains via page UI)
- Admin update payment: `PATCH /pages/api/payments_update.php` → verify/reject with audit information
- Availability lookup: `GET /pages/api/availability.php?package_id=` → returns reserved date ranges to power calendar UI

Common rules:
- Auth via PHP session cookies; protected endpoints require CSRF token (`X-CSRF-Token` header or JSON `csrf_token`).
- JSON envelope: `{ success, data?, error? }`.

## 8. Acceptance Plan

- Functional tests: exercise all endpoints and main page flows
- Regression: verify booking create via page still works; admin payment verification still works
- Security: CSRF present; sessions hardened; upload MIME validated
- Performance: Validate NFR targets
- Migration Validation: Confirm booking date backfill, rental-day calculations, and availability collisions across representative data sets
- Pricing Validation: Test representative bookings against pricing matrix (weekday, weekend, long-term) and reconcile with finance benchmarks

Traceability
- Stories 1.1–1.7 in `docs/stories/` map 1:1 with PRD sections and acceptance criteria.
 
Related Documents
- API Reference: `docs/api.md`
- Backlog: `docs/backlog.md`
- Booking Module Spec: `../add module.txt`

## 9. Rollout Plan

- Phase 1: Deploy API scaffolding + read endpoints
- Phase 2: Deploy booking create endpoint (baseline single-day flow)
- Phase 3: Deploy payments endpoints + security hardening
- Phase 4: Run database migration for pick-up/return columns and backfill data
- Phase 5: Roll out multi-day booking UI/API enhancements and availability services
- Rollback: Independent per phase; pages remain primary path

## 10. Open Questions

- No additional questions at this time (metadata scope and notification behavior confirmed).

## 11. Marketing Website Sitemap Requirements (2025-10-03 Update)

### 11.1 Global Structure
- **Primary Goal**: สร้างความเข้าใจในแพลตฟอร์มบริหารงานโครงการแบบครบวงจร กระตุ้นให้เริ่มทดลองใช้ฟรี และสนับสนุนลูกค้าองค์กรในการตัดสินใจซื้อ
- **Global Navigation**: สินค้า, โซลูชัน, ราคา, ทรัพยากร, บล็อก, บริการลูกค้า, เข้าสู่ระบบ, เริ่มทดลองใช้ฟรี

### 11.2 Product Section
- **Standalone Feature Pages**:
  - Command Center Dashboard — ภาพรวมโครงการและสถานะงานแบบเรียลไทม์เพื่อให้ผู้บริหารเห็นจุดอัปเดตทันที
  - Workflow Automation — สร้างกฎอัตโนมัติ ลดงานซ้ำซ้อน และให้กระบวนการเดินต่อเนื่อง
  - Collaboration Suite — ศูนย์กลางการสื่อสาร การแชร์ไฟล์ และคอมเมนต์ในบริบทของงาน
  - Analytics & Insights — รายงาน KPI การคาดการณ์ทรัพยากร และแดชบอร์ดที่ปรับแต่งได้
  - Integrations Hub — ศูนย์เชื่อมแอปภายนอกด้วยมาตรการความปลอดภัยสำหรับองค์กร
- **Child Pages Per Feature**: ภาพรวมคุณค่า, Getting Started, Tutorial แบบทีละขั้นพร้อมสกรีนช็อต/วิดีโอ, เอกสาร API/Webhook, Pricing หรือ add-on เฉพาะ, FAQ, Success stories สั้นๆ
- **Integrations & Marketplace Pages**:
  - Google Workspace — โลโก้ Google, การซิงก์ปฏิทิน/ไดรฟ์, ลิงก์คู่มือเชื่อมต่อ
  - Slack — โลโก้ Slack, แจ้งเตือนงานและสรุป sprint, ลิงก์วิธีติดตั้ง
  - Jira — โลโก้ Jira, ซิงก์บอร์ด Agile กับงาน, ลิงก์ Atlassian Marketplace
  - Salesforce — โลโก้ Salesforce, Handoff ข้อมูล pipeline, ลิงก์ศูนย์รวม integration
  - Zapier — โลโก้ Zapier, เชื่อม workflow กับแอปกว่า 5,000 รายการ, ลิงก์สูตรสำเร็จ (Zap library)
- **Feature Groupings**: จำแนกตาม persona (ผู้จัดการโครงการ, ผู้นำการตลาด, ทีม IT/ระบบ, ทีมบริการลูกค้า), ตามประเภทการใช้งาน (การวางแผน, การดำเนินงาน, การติดตามผล, การรายงาน), และตามอุตสาหกรรม (เทคโนโลยี SaaS, การผลิต, บริการมืออาชีพ, การเงิน)

### 11.3 Solutions Section
- **Team Landing Pages**: Product Management, Marketing, Operations, HR & People Ops, IT & Security
- **Pain Points by Team**:
  - Product Management — Roadmap ขาดการอัปเดต, ภาระติดตามข้อเสนอแนะ, การสื่อสารกับผู้มีส่วนได้ส่วนเสียยาก
  - Marketing — การประสานงานแคมเปญกระจัดกระจาย, ขาดตัวชี้วัดเรียลไทม์, ปรับแผนตามงบประมาณไม่ทัน
  - Operations — มองไม่เห็นคอขวด, การอนุมัติงานใช้เวลานาน, การจัดสรรทรัพยากรผิดพลาด
  - HR & People Ops — Onboarding ขาดมาตรฐาน, เอกสารกระจายหลายระบบ, การติดตาม OKR ไม่ต่อเนื่อง
  - IT & Security — ระบบเดิมเปลี่ยนยาก, ควบคุมสิทธิ์ไม่ครบ, ต้องรองรับการตรวจสอบความปลอดภัย
- **Testimonials by Team**:
  - Product Management — “ช่วยลดเวลาปรับ roadmap ลง 40%” (Head of Product, SaaS)
  - Marketing — “แคมเปญ cross-channel ราบรื่นขึ้น” (VP Marketing, Agency)
  - Operations — “ลดเวลาปิดงานซัพพลายเชนจาก 10 วันเหลือ 4 วัน” (Director of Ops, Logistics)
  - HR & People Ops — “Onboard พนักงานใหม่ได้มาตรฐานเดียวกัน” (HRBP, Tech firm)
  - IT & Security — “ผ่านการตรวจสอบ ISO ได้เร็วขึ้น” (CISO, Digital bank)
- **Featured Use Cases**: การเปิดตัวผลิตภัณฑ์, การจัดการแคมเปญการตลาด, การวางแผนทรัพยากรองค์กร, การพัฒนาซอฟต์แวร์แบบ Agile, การบริหารลูกค้า Enterprise
- **Use Case Details**:
  - การเปิดตัวผลิตภัณฑ์ — ปัญหา: ทีมกระจาย ข้อมูลไม่ทันเวลา; เวิร์กโฟลว์: Brief → Timeline → QA → Launch → Retrospective; ROI: ลดการเลื่อนเปิดตัว 30%, เพิ่ม time-to-market 20%
  - การจัดการแคมเปญการตลาด — ปัญหา: การประสานงานหลายช่องทาง; เวิร์กโฟลว์: Campaign brief → Content pipeline → Approval → Distribution → Reporting; ROI: เพิ่ม conversion 18%, ลดงบซ้ำซ้อน 15%
  - การวางแผนทรัพยากรองค์กร — ปัญหา: ขาดการมองเห็นความจุ; เวิร์กโฟลว์: Forecast → Allocation → Tracking → Rebalancing; ROI: ลด over-allocation 25%, เพิ่ม utilization 12%
  - การพัฒนาซอฟต์แวร์แบบ Agile — ปัญหา: Sprint ขาดวินัย; เวิร์กโฟลว์: Backlog grooming → Sprint planning → Daily sync → Review → Retro; ROI: เพิ่ม velocity 22%, ลด bug หลังส่งมอบ 35%
  - การบริหารลูกค้า Enterprise — ปัญหา: SLA ไม่สอดคล้อง; เวิร์กโฟลว์: Handoff → Success plan → Check-in → Escalation → Renewal; ROI: เพิ่ม retention 9%, ลดเวลาจัดการกรณีฉุกเฉิน 40%
- **Use Case Filters**: อุตสาหกรรม (เทคโนโลยี, การเงิน, การผลิต, บริการ), ขนาดบริษัท (SMB, Mid-market, Enterprise), ผลลัพธ์ (เร่งการเปิดตัว, เพิ่มประสิทธิภาพ, ปรับปรุง compliance)

### 11.4 Resources Section
- **Content Types**: Blog, Knowledge base, Product guides, Release notes, Webinars, Case studies, FAQ, Template library
- **Blog Operations**: หมวดหมู่ (Product updates, Productivity tips, Change management, Thought leadership), โพสต์สัปดาห์ละ 2 ครั้ง, ผู้เขียน (PM, CMO guest, Partner agencies, Subject matter experts)
- **FAQ Grouping**: จำแนกตามผลิตภัณฑ์/ฟีเจอร์, ทีมผู้ใช้งาน, และประเด็นเทคนิค/บัญชี/ความปลอดภัย
- **Templates & Formats**: Project kickoff checklist (.docx), Sprint planning board (.xlsx), Stakeholder update deck (.pptx), Budget tracker (.xlsx), Postmortem report (.pdf)
- **Community Channels**: ฟอรัมบนเว็บไซต์ (สมัครด้วยบัญชีลูกค้า), Slack community (ขอเข้าร่วมผ่านแบบฟอร์ม), Office hours webinar รายเดือน (ลงทะเบียนล่วงหน้า)

### 11.5 Other Pages
- **Corporate Pages**: About Us, Leadership, Careers, Diversity & Inclusion, Press/Media kit, Events & Webinars, Partner program, Contact/Support
- **Pricing Table**: Starter — $19/ผู้ใช้/เดือน พร้อมฟีเจอร์พื้นฐาน; Growth — $39/ผู้ใช้/เดือน เพิ่ม automation และ analytics; Scale — $69/ผู้ใช้/เดือน เพิ่ม advanced security และ API; Enterprise — ติดต่อฝ่ายขายเพื่อกำหนด SLA/onboarding เฉพาะ
- **Enterprise-Specific Content**: SLA 99.9%, White-glove onboarding, Dedicated account manager, SOC2/ISO เอกสาร, ตัวเลือก data residency
- **Auth Flows**: Sign Up (ชื่อเต็ม, อีเมลบริษัทพร้อมตรวจ MX, ชื่อบริษัท, จำนวนผู้ใช้, รหัสผ่าน ≥10 ตัวพร้อมตัวใหญ่/เล็ก/ตัวเลข/อักขระพิเศษ, checkbox ยอมรับเงื่อนไข), Log In (อีเมล, รหัสผ่าน, จำฉันไว้, 2FA เมื่อเปิดใช้งาน); กฎ validation ป้องกัน brute force ด้วย rate limit และ error ตามฟิลด์
- **Footer Legal Links**: Privacy Policy, Terms of Service, Cookie Notice, Data Processing Addendum, Security Center, Responsible Disclosure

### 11.6 Technical & UX Considerations
- **Localization**: รองรับภาษาไทยและอังกฤษในระยะเริ่มต้น; เฟส 2 เพิ่มภาษาญี่ปุ่นเมื่อมีดีมานด์
- **User Roles**: Admin, Manager, Member, Viewer/Stakeholder, External collaborator พร้อมสิทธิ์ตามบทบาท
- **SEO Requirements**: โครงสร้าง URL ลึกไม่เกิน 3 ระดับ (เช่น `/solutions/team/product-management`), meta title ≤ 60 ตัวอักษร, meta description ≤ 155 ตัวอักษร, ใช้ schema.org สำหรับบทความและ testimonial, ตั้ง canonical และ hreflang ครบทุกภาษา
- **Navigation Depth**: สูงสุด 3 ระดับเพื่อคงความง่ายในการใช้งาน

### 11.7 Immediate Next Steps
1. ตรวจสอบความสอดคล้องกับ positioning แบรนด์และข้อความการขาย
2. ระบุตัวอย่างลูกค้าจริงและโลโก้ที่ได้รับอนุญาตให้เผยแพร่
3. เตรียม asset ประกอบ (ไอคอน ภาพ workflow) เพื่อส่งมอบทีมออกแบบ

---
This PRD is intended to guide story creation and safe implementation in a brownfield environment while keeping existing functionality intact. Please confirm scope and story order.
