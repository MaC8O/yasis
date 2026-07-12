# ISMS — Implementation Specification (Developer Build Guide)

**Project:** Integrated School Management System (ISMS) — Yangon Adventist Seminary (YASIS)
**Companion to:** ISMS IT483 Report v1.4 (requirements, ERD, use cases, feasibility)
**Audience:** Implementing developer(s) building on Laravel 12 / PHP 8.3 / MySQL 8.0
**Status:** Build-ready. This document is the single source of truth for *how* to build each screen and endpoint. Where it conflicts with prose in the report, this document wins for implementation detail; where it conflicts with the data dictionary, the report's data dictionary wins for field names/types.

---

## 0. How to read this document

Every user-facing **page** is specified with the same nine facets, in this order:

1. **Purpose & route** — what it's for, URL, who can reach it.
2. **UI description** — regions top-to-bottom, exact components, all visual states.
3. **User flow** — the step sequence, including error/empty/loading branches.
4. **Components** — the shared components it composes (see §3.4).
5. **API endpoints** — the calls it makes (full contract in §14).
6. **Validation rules** — client + server rules for every input.
7. **Business logic** — the rules the server enforces for this screen.
8. **Responsive behavior** — how it reflows at each breakpoint (see §3.5).
9. **Acceptance criteria** — Given/When/Then checks a QA can run.

Global concerns (auth, RBAC, audit, error format, design tokens, responsive rules) are defined **once** in §1–§3 and referenced, not repeated.

---

## 1. Tech stack & project conventions

| Concern | Decision |
|---|---|
| Framework | Laravel 12 (MVC + Service layer) |
| Language | PHP 8.3 |
| DB | MySQL 8.0, InnoDB, `utf8mb4_unicode_ci` |
| Auth | Laravel session auth (web) + Sanctum for the SPA/mobile-responsive fetch layer |
| RBAC | `spatie/laravel-permission` — 9 roles (§2.2) |
| Frontend | Blade + Tailwind CSS + Alpine.js (server-rendered, progressively enhanced) |
| PDF | `barryvdh/laravel-dompdf` (report cards, transcripts, certificates, fee reports) |
| Excel/CSV | `maatwebsite/excel` (fee import, exports) |
| Testing | Pest (feature + unit) |
| Queue | database driver (notifications, PDF generation) |
| Time | Store UTC; display Asia/Yangon (UTC+6:30) |
| Money | Fees are display-only imported values; store `DECIMAL(12,2)`, never float |

**Layering rule (enforced in review):** Controllers are thin — validate input (FormRequest), call a Service, return a Resource/response. All business rules live in Services (`app/Services/*`). Services call the `AuditService` for every state change and never bypass it. No business logic in Blade, controllers, or models beyond relationships/accessors.

**Naming:** tables snake_case plural; models singular PascalCase; routes kebab-case; API JSON keys camelCase (via API Resources); DB columns snake_case.

---

## 2. Roles & access model

### 2.1 The 9 roles
`Admin`, `Principal`, `VP_Academic`, `Registrar`, `Teacher`, `Treasurer`, `HR_Office`, `Guardian`, `Student`.

Staff roles (`Admin…HR_Office`) live in `staff_profiles.role_type` **and** are assigned as Spatie roles on the `users` row. `Guardian` and `Student` are Spatie roles only (no `staff_profiles` row). The Spatie role is the RBAC authority; `role_type` is the HR/org attribute. A seeder keeps them in sync on user creation.

### 2.2 Permission → role matrix (authoritative)
Permissions are checked in policies/middleware, never by string-matching role names in controllers.

| Permission (ability) | Admin | Principal | VP | Registrar | Teacher | Treasurer | HR | Guardian | Student |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| users.manage | ✅ | | | | | | | | |
| users.reset_credentials | ✅ | | | | | | | | |
| retention.action | ✅ | | | | | | | | |
| settings.technical | ✅ | | | | | | | | |
| settings.governance | | ✅ | | | | | | | |
| academic_year.configure | ✅ | | | | | | | | |
| grade_scale.configure | ✅ | | | | | | | | |
| audit.view | ✅ | ✅ | | | | | | | |
| students.manage | | | | ✅ | | | | | |
| students.exit_certificate | | | | ✅ | | | | | |
| sections.manage | | | | ✅ | | | | | |
| subjects.manage | | | ✅ | | | | | | |
| enrollment.manage | | | | ✅ | | | | | |
| promotion.prepare | | | | ✅ | | | | | |
| promotion.approve (key) | | ✅ | ✅ | | | | | | |
| transcript.generate | | | | ✅ | | | | | |
| transcript.issue (governance) | | ✅ | | | | | | | |
| attendance.record | | | | | ✅ | | | | |
| attendance.correct_classification | | | | ✅ | | | | | |
| gradebook.manage | | | | | ✅ | | | | |
| grades.enter | | | | | ✅ | | | | |
| grade_change.approve (key) | | ✅ | ✅ | | | | | | |
| absence_notice.acknowledge | | | | | ✅ | | | | |
| absence_notice.submit | | | | | | | | ✅ | |
| fees.import | | | | | | ✅ | | | |
| fees.batch_revert | | | | | | ✅ | | | |
| fees.resolve_unmatched | | | | | | ✅ | | | |
| fees.report | | | | | | ✅ | | | |
| fees.view_readonly | | ✅ | ✅ | ✅ | | ✅ | | | |
| staff.manage | | | | | | | ✅ | | |
| staff_attendance.record | | | | | | | ✅ | | |
| leave.decide | | | | | | | ✅ | | |
| leave.request | | | | | ✅ | | ✅ | | |
| staff.view_readonly | | ✅ | | | | | ✅ | | |
| announcement.publish | | ✅ | | | | | | | |
| board_report.generate | | ✅ | | | | | | | |
| child.view_own | | | | | | | | ✅ | |
| self.view_own | | | | | | | | | ✅ |

**Scope guards (row-level, beyond the ability check):**
- Teacher endpoints are additionally scoped: a teacher may only touch sections in their `teaching_assignments` / homeroom. Enforced in the policy via a `teachesSection($sectionId)` check.
- Guardian endpoints are scoped to children linked via `student_guardian`. `child.view_own` + `guardian_can_access(student_id)`.
- Student endpoints are scoped to `auth()->user()->student->id`.
- `fees.view_readonly` viewers never see rows where `imported_fee_records.is_restricted = 1`.

---

## 3. Global cross-cutting specifications

### 3.1 Authentication, session & account security
- **Login**: email + password, `bcrypt` verify. On success, regenerate session, redirect to the role's home route (§3.3).
- **Failed-login lockout**: after **5** consecutive failures for an email, lock that account for **15 minutes** (`users` gains `failed_attempts INT DEFAULT 0`, `locked_until DATETIME NULL`). Reset counter on success. Locked login returns 423.
- **Session idle timeout**: **30 minutes** (`config/session.php lifetime=30`, `expire_on_close=true`). A request after timeout → 419 → redirect to login with "Session expired."
- **Self-service password reset**: standard Laravel broker, email link, 60-min token, one-time use.
- **Admin-initiated reset**: `users.reset_credentials` → forces `must_reset_password=true` and emails a fresh link ("re-send login"). On next login the user is forced through a set-password screen.
- **Password policy**: min 8 chars, must contain letters + digits; blocklist the 100 most common passwords; never logged.
- **CSRF** on all state-changing web routes; **HTTPS/TLS** enforced (HSTS in prod).

### 3.2 Audit logging (non-repudiation)
Every state-changing service call writes one `audit_logs` row **inside the same DB transaction** as the change (so a failed action logs nothing). Fields: `user_id`, `role` (the actor's role *at the time*, copied — not derived), `action` (verb.noun, e.g. `leave.approve`), `entity_type`, `entity_id`, `created_at`. Audit rows are **append-only**: no update/delete routes, no model events that mutate them. A DB trigger (or app-level guard) rejects UPDATE/DELETE on the table in production.

Actions that MUST be audited: any create/update/deactivate of users; role change; credential reset; academic-year/grade-scale change; student create/edit/exit; guardian link/edit; section/homeroom change; enrollment; promotion prepare/approve/apply; attendance record + classification correction; gradebook category/assessment change; grade enter/change; grade-change approval; transcript/certificate generate + issue; fee import + batch revert + unmatched resolve + visibility change; staff create/edit/status; staff-attendance record; leave submit/decide/cancel; absence-notice submit/acknowledge/cancel; announcement publish; board-report generate; retention/erasure action; settings change.

### 3.3 Role home routes (post-login redirect)
`Admin→/admin`, `Principal→/principal`, `VP_Academic→/vp`, `Registrar→/registrar`, `Teacher→/teacher`, `Treasurer→/treasurer`, `HR_Office→/hr`, `Guardian→/guardian`, `Student→/student`.

### 3.4 Shared component library (Blade + Alpine)
Build these once; every page composes them. Each is a Blade component `<x-...>` with documented props.

- **`<x-app-shell>`** — the authenticated layout: fixed left **Sidebar** (214px desktop), top bar (page title, user menu, sign-out), content slot. Props: `:nav`, `title`.
- **`<x-sidebar>`** — role-specific nav list; active item = pine-green pill; each item `{label, icon, route, badge?}`. Badge is a small count chip (e.g., pending items).
- **`<x-kpi-tile>`** — label (uppercase 11px), big value, sub-caption, tint variant (`green|blue|amber|red|neutral`). Fixed height 104px.
- **`<x-card>`** — white surface, radius 16, 1px border `#DFE4DF`, 24px padding, optional `title`+`subtitle` header slot.
- **`<x-data-table>`** — header row, sortable columns (prop `:sortable`), row slot, empty state, pagination footer, optional per-row action slot. On mobile it switches to stacked cards (§3.5).
- **`<x-pill>`** — status chip; variants map to semantic colors (§3.6): `green(Approved/Present/Paid/Active)`, `amber(Pending/Submitted/Tardy/Partial/On Leave)`, `red(Rejected/Absent/Outstanding/Inactive)`, `blue(Excused/info)`, `neutral(default)`.
- **`<x-btn>`** — variants `primary(green)`, `soft`, `danger`, `ghost`; sizes `sm|md`; `:disabled`, `:loading`.
- **`<x-form-field>`** — label, input/select/textarea slot, hint text, inline error (red, below field), required asterisk.
- **`<x-modal>` / `<x-drawer>`** — Alpine-driven; trap focus; ESC + backdrop close; `@confirm` event.
- **`<x-toast>`** — transient success/error, top-right, auto-dismiss 4s, ARIA live.
- **`<x-confirm-dialog>`** — destructive actions (deactivate, revert batch, cancel request); requires explicit confirm.
- **`<x-empty-state>`** — icon, message, optional CTA.
- **`<x-file-drop>`** — drag/drop + browse; shows filename, size, type validation.

### 3.5 Responsive behavior (global rules)
Breakpoints (Tailwind): `sm 640`, `md 768`, `lg 1024`, `xl 1280`. Design canvas is 1280.

- **≥ lg (1024+)**: full layout as in Figma. Sidebar fixed-visible 214px. KPI tiles in a row of 4–5. Tables full.
- **md (768–1023)**: sidebar collapses to an icon rail (64px) or a hamburger drawer; KPI tiles wrap to 2 columns; tables keep horizontal scroll for ≤6 columns, else switch to card mode.
- **< md (<768, phones — the primary Guardian/Student surface)**: sidebar becomes a top hamburger drawer; KPI tiles stack 1-per-row (or 2 if compact); **all `<x-data-table>` render as stacked cards** — each row becomes a card with `label: value` pairs and the row actions as full-width buttons; forms go single-column; sticky bottom action bar for primary submit; modals become full-screen sheets.
- Touch targets ≥ 44×44px. No horizontal page scroll on phones. Charts collapse to a simple labeled bar list under 480px.

### 3.6 Design tokens (exact)
Pine green `#1F573D` (primary/active), sidebar near-black `#131B1B`, gold `#C9A227`, paper `#F6F7F2`, mute text `#576661`, ink `#131B1B`, card border `#DFE4DF` / `#B2BFB2`, line `#E1E6E1`. Card radius 16–18. KPI tints: green `#D6EDDE`, blue `#D6E5F7`, amber `#FAEDC2`, red `#F2D9D4`. Semantic ink: gold `#8A6D10`, blue `#2E5AAC`, red `#B0392B`. Font **Inter** (400/600/700). Sidebar nav item 214×42, active = green pill, 50px vertical step.

### 3.7 API conventions
- Base path `/api`. Session/Sanctum auth. JSON only.
- **Success envelope**: `{ "data": <resource|array>, "meta": {pagination?} }`.
- **Error envelope**: `{ "message": "...", "errors": { "field": ["..."] } }` (422 validation), or `{ "message": "..." }` for 4xx/5xx.
- **Status codes**: 200 ok, 201 created, 204 no-content, 400 bad request, 401 unauth, 403 forbidden, 404 not-found, 409 conflict (e.g., double-mark), 422 validation, 423 locked, 429 rate-limited, 500 server.
- **Pagination**: `?page=&per_page=` (default 25, max 100); `meta.pagination = {total, per_page, current_page, last_page}`.
- **Filtering/sort**: `?filter[field]=&sort=field|-field&q=` (free-text search). Documented per endpoint.
- **Idempotency**: mutating imports/reverts accept an `Idempotency-Key` header; replays return the original result.
- **Rate limits**: auth endpoints 10/min/IP; import 6/min/user; general 120/min/user.
- All list endpoints are RBAC- and scope-filtered server-side (never trust client filters for authorization).

### 3.8 Standard states every screen must implement
Loading (skeleton), empty (`<x-empty-state>`), error (inline banner + retry), partial-permission (hide controls the role lacks — never render then 403), and optimistic-lock conflict (409 → "This record changed, reload").

---

## 4. Database schema (authoritative)

Notation: PK, FK→table.col, UQ unique, NN not null, IDX index. All tables have `created_at`/`updated_at` unless noted. Engine InnoDB. All FKs `ON UPDATE CASCADE`; `ON DELETE` is `RESTRICT` unless stated (we never hard-delete parents).

### 4.1 Identity & access

**users**
| col | type | constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AI |
| name | VARCHAR(150) | NN |
| email | VARCHAR(150) | NN, UQ |
| password | VARCHAR(255) | NN (bcrypt) |
| status | ENUM('Active','Inactive') | NN, DEFAULT 'Active' |
| must_reset_password | BOOLEAN | NN, DEFAULT 0 |
| failed_attempts | INT | NN, DEFAULT 0 |
| locked_until | DATETIME | NULL |
| email_verified_at | DATETIME | NULL |
| remember_token | VARCHAR(100) | NULL |

**staff_profiles** (shares PK with users)
| id | BIGINT UNSIGNED | PK, FK→users.id, NN |
| staff_id_number | VARCHAR(30) | NN, UQ |
| role_type | ENUM('Admin','Principal','VP_Academic','Registrar','Teacher','Treasurer','HR_Office') | NN |
| department_id | BIGINT UNSIGNED | FK→departments.id, NULL |
| status | ENUM('Active','On Leave','Probation','Inactive') | NN, DEFAULT 'Active' |
| joined_date | DATE | NN |
| phone | VARCHAR(30) | NULL |
IDX: (role_type), (department_id), (status).

**students**
| id | BIGINT UNSIGNED | PK, AI |
| user_id | BIGINT UNSIGNED | FK→users.id, NULL |
| student_id_number | VARCHAR(30) | NN, UQ (existing school format, preserved) |
| first_name | VARCHAR(80) | NN |
| last_name | VARCHAR(80) | NN |
| date_of_birth | DATE | NN |
| gender | ENUM('M','F') | NN |
| department_id | BIGINT UNSIGNED | FK→departments.id, NN |
| religion | VARCHAR(40) | NULL (for Board religious-background summary) |
| enrollment_status | ENUM('Active','Inactive','Graduated','Transferred') | NN, DEFAULT 'Active' |
| admission_date | DATE | NN |
| exited_at | DATE | NULL |
IDX: (department_id), (enrollment_status), (student_id_number).

**guardians**
| id | BIGINT UNSIGNED | PK, AI |
| user_id | BIGINT UNSIGNED | FK→users.id, NN |
| relationship | VARCHAR(40) | NN |
| phone | VARCHAR(20) | NN |

**student_guardian** (M:N resolver)
| id | PK, AI | | |
| student_id | FK→students.id, NN | |
| guardian_id | FK→guardians.id, NN | |
| is_primary | BOOLEAN NN DEFAULT 0 | |
UQ (student_id, guardian_id). Business rule: exactly one `is_primary=1` per student.

### 4.2 Organisation & calendar

**departments**: id PK; name VARCHAR(50) NN UQ (Pre-School/Elementary/Middle/High); level VARCHAR(20) NN (ordering 1–4).

**academic_years**: id PK; year_label VARCHAR(20) NN UQ; start_date, end_date DATE NN; is_active BOOLEAN NN DEFAULT 0. Rule: exactly one active year (partial unique or app guard).

**terms**: id PK; academic_year_id FK NN; name VARCHAR(20) NN; sequence TINYINT NN; start_date, end_date DATE NN. UQ (academic_year_id, sequence). Exactly 4 per year.

### 4.3 Academic structure

**sections**: id PK; academic_year_id FK NN; department_id FK NN; name VARCHAR(50) NN ("Grade 10 – A"); homeroom_teacher_id FK→staff_profiles.id NULL. IDX (academic_year_id, department_id).

**subjects**: id PK; code VARCHAR(20) NN UQ; name VARCHAR(100) NN; department_id FK NN.

**teaching_assignments**: id PK; section_id FK NN; subject_id FK NN; teacher_id FK→staff_profiles.id NN. UQ (section_id, subject_id) — one teacher per subject per section.

**enrollments**: id PK; student_id FK NN; section_id FK NN; status ENUM('Enrolled','Promoted','Dropped') NN DEFAULT 'Enrolled'. UQ (student_id, section_id).

**grade_scale_bands**: id PK; department_id FK NN; letter VARCHAR(5) NN; min_score DECIMAL(5,2) NN; max_score DECIMAL(5,2) NN; gpa_point DECIMAL(3,2) NULL (NULL for descriptive lower-school scales). Rule: bands per department are contiguous, non-overlapping, cover 0–100.

### 4.4 Academic records

**assessment_categories**: id PK; section_id FK NN; subject_id FK NN; term_id FK NN; name VARCHAR(40) NN; weight_pct DECIMAL(5,2) NN. Rule: Σ weight_pct per (section, subject, term) = 100.00.

**assessments**: id PK; category_id FK NN; name VARCHAR(100) NN; max_score DECIMAL(6,2) NN.

**grades**: id PK; assessment_id FK NN; student_id FK NN; score DECIMAL(6,2) NN; entered_by FK→staff_profiles.id NN. UQ (assessment_id, student_id). Rule: 0 ≤ score ≤ assessment.max_score.

**attendance_records**: id PK; student_id FK NN; section_id FK NN; term_id FK NN; attendance_date DATE NN; status ENUM('Present','Absent','Tardy','Excused') NN; remark VARCHAR(150) NULL; absence_notice_id FK→absence_notices.id NULL; recorded_by FK→staff_profiles.id NN. UQ (student_id, section_id, attendance_date). IDX (section_id, attendance_date), (term_id).

**report_card_comments**: id PK; student_id FK NN; term_id FK NN; teacher_id FK→staff_profiles.id NN; comment VARCHAR(500) NN. UQ (student_id, term_id). *(New — supports homeroom comment.)*

### 4.5 Finance (import-only)

**import_batches**: id PK; uploaded_by FK→staff_profiles.id NN; period VARCHAR(40) NN; source_filename VARCHAR(255) NULL; row_count INT NN DEFAULT 0; matched_count INT NN DEFAULT 0; unmatched_count INT NN DEFAULT 0; status ENUM('Draft','Review','Published','Reverted') NN DEFAULT 'Draft'; uploaded_at DATETIME NN; reverted_at DATETIME NULL; reverted_by FK→staff_profiles.id NULL.

**imported_fee_records**: id PK; student_id FK→students.id NULL (NULL while unmatched); import_batch_id FK NN; raw_account_id VARCHAR(60) NULL (the source key that must match); txn_date DATE NN; description VARCHAR(150) NULL; amount DECIMAL(12,2) NN; balance DECIMAL(12,2) NN; account_status ENUM('Paid','Partial','Outstanding') NN; is_restricted BOOLEAN NN DEFAULT 0; match_status ENUM('Matched','Unmatched','Resolved','Held') NN DEFAULT 'Matched'. IDX (student_id), (import_batch_id), (match_status). *(`account_status` renamed from report's `status` to avoid collision; `raw_account_id`/`match_status` added for the resolution workflow.)*

### 4.6 Human Resource

**leave_types**: id PK; name VARCHAR(30) NN UQ (Annual/Sick/Unpaid); is_paid BOOLEAN NN.

**leave_requests**: id PK; staff_id FK→staff_profiles.id NN; leave_type_id FK NN; from_date, to_date DATE NN; days SMALLINT NN; reason VARCHAR(255) NULL; status ENUM('Pending','Approved','Rejected','Cancelled') NN DEFAULT 'Pending'; submitted_by FK→staff_profiles.id NN; decided_by FK→staff_profiles.id NULL; decided_at DATETIME NULL. IDX (staff_id), (status). Rule: to_date ≥ from_date; days = working days in range.

**leave_balances**: id PK; staff_id FK NN; leave_type_id FK NN; year SMALLINT NN; allocated SMALLINT NN; pending SMALLINT NN DEFAULT 0; used SMALLINT NN DEFAULT 0. UQ (staff_id, leave_type_id, year). Invariant: `used + pending ≤ allocated` for capped types.

**staff_attendance**: id PK; staff_id FK NN; attendance_date DATE NN; status ENUM('Present','Absent','Tardy','On-Leave') NN; remark VARCHAR(150) NULL; leave_request_id FK→leave_requests.id NULL; recorded_by FK→staff_profiles.id NN. UQ (staff_id, attendance_date).

### 4.7 Absence notices (guardian-initiated)

**absence_notices**: id PK; student_id FK NN; guardian_id FK NN; from_date, to_date DATE NN; reason VARCHAR(255) NULL; status ENUM('Submitted','Acknowledged','Cancelled') NN DEFAULT 'Submitted'; acknowledged_by FK→staff_profiles.id NULL; acknowledged_at DATETIME NULL. IDX (student_id), (status), (from_date,to_date). Rule: from_date ≥ today at submit; to_date ≥ from_date; guardian must be linked to student.

### 4.8 System

**audit_logs**: id PK; user_id FK→users.id NN; role VARCHAR(30) NN; action VARCHAR(255) NN; entity_type VARCHAR(80) NN; entity_id BIGINT UNSIGNED NULL; ip_address VARCHAR(45) NULL; created_at DATETIME NN. **Append-only.** IDX (user_id), (entity_type, entity_id), (created_at).

**announcements**: id PK; author_id FK→staff_profiles.id NN; title VARCHAR(150) NN; body TEXT NN; audience ENUM('School','Staff','Department','Section') NN; department_id FK NULL; section_id FK NULL; published_at DATETIME NN. IDX (audience), (published_at).

**settings**: id PK; key VARCHAR(80) NN UQ; value JSON NN; group ENUM('technical','governance') NN; updated_by FK→staff_profiles.id NN. Holds SMTP/notification config, academic-calendar events, and governance toggles (promotion_window_open, grade_lock:{term_id}, transcript_issuance_enabled, results_released:{term_id}).

**promotion_batches**: id PK; from_academic_year_id FK NN; to_academic_year_id FK NN; prepared_by FK NN; status ENUM('Draft','AwaitingVP','AwaitingPrincipal','Approved','Applied','Rejected') NN; vp_approved_by FK NULL; vp_approved_at DATETIME NULL; principal_approved_by FK NULL; principal_approved_at DATETIME NULL; applied_at DATETIME NULL. **promotion_batch_items**: id PK; promotion_batch_id FK NN; student_id FK NN; from_section_id FK NN; to_section_id FK NULL; decision ENUM('Promote','Retain','Graduate') NN.

**document_requests** (transcripts & certificates): id PK; student_id FK NN; type ENUM('Transcript','ReportCard','TransferCertificate','CompletionCertificate','EnrollmentCertificate') NN; status ENUM('Queued','NeedsApproval','Approved','Ready','Returned') NN; requested_by FK NN; approved_by FK NULL; pdf_path VARCHAR(255) NULL. IDX (student_id), (status).

**grade_change_requests**: id PK; grade_id FK NN; old_score DECIMAL(6,2) NN; new_score DECIMAL(6,2) NN; reason VARCHAR(255) NN; requested_by FK NN; status ENUM('AwaitingVP','AwaitingPrincipal','Approved','Rejected') NN; vp_by FK NULL; principal_by FK NULL. *(Post-lock grade changes route here.)*

### 4.9 Seeders (required)
Roles+permissions (§2.2); departments (4); one active academic_year + 4 terms; leave_types (Annual/Sick/Unpaid); a demo user per role; grade_scale_bands for HS/MS (with GPA) and Elementary/Pre-School (descriptive); one section per department with homeroom + subjects + teaching_assignments; sample students+guardians; sample import batch. Demo login accounts appear on the Login screen (§5).

---

## 5. Login (`/login`) — all roles

**Purpose & route:** Unauthenticated entry. `GET /login` (form), `POST /login`. Redirects authenticated users to their role home (§3.3).

**UI:** Centered card (max 420px) on paper background. School logo + "YASIS ISMS". Fields: Email, Password (show/hide toggle), "Remember me" checkbox, **Sign in** primary button (full-width). Below: "Forgot password?" link → `/forgot-password`. A small TLS/lock indicator. In non-production, a **demo accounts** disclosure lists one login per role (click fills the form). Error banner above the form for failed/locked attempts.

**User flow:** Enter creds → Sign in (button shows loading) → success: session regenerates, redirect to role home → failure: inline error, password cleared, focus password. 5th failure → locked banner with countdown. `must_reset_password=true` → redirect to `/set-password` before home.

**Components:** `<x-card>`, `<x-form-field>`, `<x-btn primary>`, `<x-toast>`.

**Validation:** email required|email; password required. Server: throttle 10/min/IP.

**Business logic:** §3.1 (bcrypt, lockout counter, session regenerate, timeout). Never reveal whether email exists ("These credentials do not match").

**Responsive:** Card is fluid ≤420px with 16px side gutters on phones; inputs 44px tall; button full-width.

**Acceptance:**
- Given valid creds, When Sign in, Then redirected to the correct role home and a session cookie is set.
- Given 5 wrong attempts, When 6th submit, Then 423 + locked message with remaining minutes; correct password during lock still refused.
- Given `must_reset_password`, When login succeeds, Then forced to `/set-password`; cannot reach any other route until reset.
- Given an idle session >30 min, When any request, Then redirect to login with "Session expired."

---

## 6. Admin portal (`/admin/*`)

Sidebar: Dashboard, User Management, Teacher Class Assignment, Academic Year, Grade Scale, Audit Logs, System Settings. Every page requires an `Admin` ability from §2.2.

### 6.1 Admin Dashboard — `/admin`
**UI:** 4 KPI tiles: **Active users** (count), **Accounts to action** (inactive + never-logged-in + reset-requested; amber), **Login activity** (logins today), **Last backup** (status + time; from settings/ops ping). Below: "System access controls" card with quick chips (RBAC on, session timeout 30m, lockout 5, **Retention policy set**); a shortcuts panel (User Management, Academic Year, Audit Logs, Backup & Export); "Recent login activity" table (user, role, time, IP). Subtitle: "Manage system access, users, roles, credentials, configuration, backups, and data retention."
**Flow:** Read-only landing; tiles and shortcuts deep-link. "Accounts to action" → User Management pre-filtered.
**Components:** `<x-kpi-tile>`×4, `<x-card>`, `<x-data-table>`.
**API:** `GET /api/admin/dashboard` → `{activeUsers, accountsToAction, loginsToday, lastBackup:{status,at}, recentLogins:[]}`.
**Validation:** n/a (read).
**Business logic:** counts computed server-side; `accountsToAction = users where status=Inactive OR never logged in OR must_reset_password`.
**Responsive:** tiles 4→2→1; recent-login table → cards on phone.
**Acceptance:** Given N such accounts, dashboard shows N and the filtered link lands on exactly those rows.

### 6.2 User Management — `/admin/users`
**UI:** Header with **Add User** + **Bulk Import** (user accounts) buttons. Searchable/filterable `<x-data-table>`: columns Name, Email, Role (pill), Status (pill), Last login, **Actions**. Row actions (kebab/menu): **Edit**, **Reset password / Re-send login**, **Deactivate** or **Reactivate**, **View audit**. Bottom actions: **Access Control**, **Data Retention**, **View Audit Trail**. A create/edit **drawer** (name, email, role select [9 roles], department [if Teacher/HR-relevant], status, staff_id_number when staff). Legend line: "Per-user actions: Edit · Reset password / Re-send login · Deactivate / Reactivate · View audit."
**Flow (create):** Add User → drawer → fill → Save → on success the account is created (Guardian/Student accounts are normally auto-provisioned at registration; this is for staff/manual) → toast, table refresh. **Reset:** confirm dialog → sets `must_reset_password`, emails link → toast. **Deactivate:** confirm → status Inactive (never delete). **Data Retention:** opens a modal to action an erasure/retention request against a named student/guardian with a reason (audited).
**Components:** `<x-data-table>`, `<x-drawer>`, `<x-form-field>`, `<x-confirm-dialog>`, `<x-file-drop>` (bulk), `<x-pill>`.
**API:** `GET /api/admin/users?q&filter[role]&filter[status]&page`; `POST /api/admin/users`; `PATCH /api/admin/users/{id}`; `POST /api/admin/users/{id}/reset-credentials`; `PATCH /api/admin/users/{id}/status`; `POST /api/admin/users/bulk-import`; `POST /api/admin/retention-actions`.
**Validation:** name required|max150; email required|email|unique; role required|in(9); status in(Active,Inactive); staff_id_number required_if staff|unique; bulk file mimes:csv,xlsx|max:5MB with a header-mapping preview and per-row validation (reject malformed rows, report line numbers).
**Business logic:** creating a staff user creates the paired `staff_profiles` row + assigns the Spatie role atomically. Role change re-syncs Spatie role + `role_type`. Reset never exposes a password; it emails a one-time link. All actions audited with target entity.
**Responsive:** table → cards; drawer → full-screen sheet; bulk preview scrolls.
**Acceptance:**
- Add staff user → both `users` and `staff_profiles` exist and the Spatie role is attached; audit row `users.create`.
- Reset → `must_reset_password=1`, email queued, no password in any response/log.
- Deactivate → status Inactive, user can no longer authenticate; Reactivate restores.
- Bulk import of a file with 2 bad rows → good rows imported, 2 rejected with line numbers, nothing partially half-written (transaction).

### 6.3 Teacher Class Assignment — `/admin/teacher-assignments`
**UI:** Table of sections with their homeroom teacher + subject-teacher assignments; assign/reassign via drawer. **Note:** operational ownership of section+homeroom is the Registrar (§7.5); this Admin screen is the initial technical setup/override and is labeled as such.
**API:** `GET/POST/PATCH /api/admin/teaching-assignments`.
**Business logic:** writing a `teaching_assignments` row enforces UQ(section,subject); homeroom set on `sections.homeroom_teacher_id`.
**Acceptance:** assigning the same subject twice to one section → 409.

### 6.4 Academic Year — `/admin/academic-year`
**UI:** List of years (label, dates, active badge); create year (label, start, end) which auto-creates 4 terms (editable dates); **Activate** action (exactly one active). Term editor sub-table.
**API:** `GET/POST /api/admin/academic-years`; `POST /api/admin/academic-years/{id}/activate`; `PATCH /api/admin/terms/{id}`.
**Validation:** label unique; end>start; term sequence 1–4 contiguous; term ranges within year and non-overlapping.
**Business logic:** activating a year deactivates the previous; guard prevents zero active years.
**Acceptance:** create year → 4 terms exist; activate Y2 → Y1.is_active=0, Y2=1.

### 6.5 Grade Scale — `/admin/grade-scale`
**UI:** Per-department band editor (letter, min%, max%, GPA point). Validation banner if bands overlap or leave gaps or don't cover 0–100. Lower-school departments allow GPA-null descriptive bands.
**API:** `GET/PUT /api/admin/grade-scales/{departmentId}` (replace full set atomically).
**Validation:** contiguous, non-overlapping, cover 0–100; min<max; letters unique per dept.
**Acceptance:** saving overlapping bands → 422 with the offending pair; valid set persists and is used by grade computation (§8.4).

### 6.6 Audit Logs — `/admin/audit` (also Principal read)
**UI:** Filterable table: When, User, Role, Action, Entity, Entity ID, IP. Filters: date range, user, action prefix, entity type. Export CSV. **Read-only; no edit/delete controls anywhere.**
**API:** `GET /api/admin/audit?filter[...]&page` (+ `GET .../export`).
**Business logic:** endpoint is read-only; table has no mutating routes.
**Acceptance:** no UI or API path can modify an audit row; filter by entity returns only that entity's trail.

### 6.7 System Settings / Access Control — `/admin/settings`
**UI:** Tabs/sections: **Access Control** (read-only RBAC matrix; security rules: session 30m, lockout 5, audit records user+role+timestamp), **Notifications & SMTP** (host/port/user/from, channel toggles: Email on; SMS/Viber/Telegram off/config), **Academic Calendar** (events feeding dashboards), **Backups & Export** (last backup status, on-demand export snapshot button — backups themselves are infra-scheduled, not run from UI). A note: technical settings are Admin-owned; governance controls live in the Principal portal (§11.5).
**API:** `GET /api/admin/settings`; `PUT /api/admin/settings/{group}`; `POST /api/admin/export-snapshot`.
**Validation:** SMTP host/port format; from-email valid; JSON schema per settings key.
**Business logic:** settings writes audited; export-snapshot streams a zip/download (does not expose DB creds); never a "run backup now" that blocks a request.
**Acceptance:** toggling a channel persists and is audited; export-snapshot returns a downloadable artifact; RBAC matrix is non-editable here.

---

## 7. Registrar portal (`/registrar/*`)

Sidebar: Dashboard, Register Student, Students, Guardians, Sections, Transcripts & Certificates, (Student Exit is reached from Students). Abilities: `students.*`, `sections.manage`, `enrollment.manage`, `promotion.prepare`, `transcript.generate`, `students.exit_certificate`, `attendance.correct_classification`.

### 7.1 Registrar Dashboard — `/registrar`
**UI:** KPI tiles: Active students, Pending registrations, Guardian links %, **Documents queue** (transcripts+certificates). Work queue table: New applications / Missing documents / **Promotion list → Prepare**. Quick actions (Students, Guardians, Sections, Transcripts, Announcements) with a summary line: "**Graduation/exit queue: N · Certificate requests: N**". Absence-classification correction card (students whose Excused/Unexcused needs a registrar fix).
**API:** `GET /api/registrar/dashboard`.
**Business logic:** "students without guardian" = students with no `student_guardian` row; "upcoming promotions" = enrollments in the top section of each grade band nearing year end.
**Responsive:** tiles 4→2→1; work queue → cards.
**Acceptance:** counts match underlying queries; "Prepare" launches the promotion batch (§7.6).

### 7.2 Register Student — `/registrar/students/create`
**UI:** Multi-section form: **Personal** (student_id_number [preserved format], first/last, DOB, gender, department, religion, admission_date), **Guardian linking** (search existing guardian or add new: name/email/phone/relationship; mark primary), **Academic** (assign to section → creates enrollment). Inline validation; **Save & Enroll** primary.
**Flow:** fill → Save & Enroll → server creates student (+ optional student portal user), links guardian(s) (auto-provisions guardian user + emails login), creates enrollment, writes audit → success → go to the student's profile. Missing required → inline errors, no submit.
**Components:** `<x-form-field>`, guardian search combobox, `<x-btn primary>`.
**API:** `POST /api/registrar/students` (body: student, guardians[], section_id).
**Validation:** student_id_number required|unique|matches configured format regex; names required|max80; DOB required|date|before:today; gender in(M,F); department_id exists; admission_date date; ≥1 guardian; guardian.email email|unique-per-new; is_primary exactly one.
**Business logic:** transaction wraps student+guardian+user+enrollment+audit. Guardian/student user accounts auto-provisioned (random password + reset link email). Preserve existing ID format (no re-generation).
**Responsive:** single-column sections stacked; sticky Save bar on phone.
**Acceptance:** valid submit → student + enrollment + ≥1 linked guardian + guardian user with reset email queued + audit `students.create`; duplicate student_id → 422.

### 7.3 Students list — `/registrar/students`
**UI:** Searchable table (ID, Name, Dept, Section, Status pill, Guardian?). Row → profile. Profile shows tabs: Overview, Guardians, Enrollment history, Attendance, Grades, **Exit & Certificates**. Actions: Edit profile, Transfer/Drop (→ exit flow §7.7), Graduate (final year), Issue Enrollment Certificate.
**API:** `GET /api/registrar/students?q&filter[dept]&filter[status]`; `GET/PATCH /api/registrar/students/{id}`.
**Validation:** edit re-validates changed fields; status transitions constrained (§7.7).
**Acceptance:** editing a name writes audit `students.update` with a diff; search by ID/name works.

### 7.4 Guardians — `/registrar/guardians`
**UI:** Guardian table (name, phone, email, #children). Edit guardian contact; link/unlink to students; set primary.
**API:** `GET /api/registrar/guardians`; `PATCH /api/registrar/guardians/{id}`; `POST /api/registrar/guardians/{id}/links`; `DELETE .../links/{studentId}`.
**Validation:** phone/email formats; cannot unlink the only guardian of a student (leave student guardian-less warns).
**Acceptance:** setting a new primary flips the previous primary off atomically.

### 7.5 Sections — `/registrar/sections`
**UI:** Per department-year: sections table (name, homeroom teacher, roster size, subjects). **Add Section**, **Assign Homeroom Teacher**, place students (enroll). Ownership note: "The Registrar creates sections and assigns the homeroom teacher; the VP Academic owns subject-teaching assignments; year-end promotion is Registrar-prepared and applied only after VP + Principal co-approval."
**API:** `GET/POST/PATCH /api/registrar/sections`; `POST /api/registrar/sections/{id}/homeroom`; `POST /api/registrar/sections/{id}/enroll` (student_ids[]).
**Validation:** section name unique per (year, department); homeroom teacher must be a `Teacher` role_type; enroll respects UQ(student,section).
**Business logic:** creating sections is scoped to the active academic year; homeroom writes `sections.homeroom_teacher_id`; enroll creates `enrollments`.
**Acceptance:** assigning a non-teacher as homeroom → 422; enrolling an already-enrolled student → 409.

### 7.6 Prepare Promotion Batch (two-key) — `/registrar/promotion`
**UI:** Select from-year → grid of students by section with per-student decision (Promote→target section / Retain / Graduate). **Submit for approval** sends to VP then Principal. Status banner tracks Draft → AwaitingVP → AwaitingPrincipal → Approved → Applied. **Apply** button enabled only when Approved.
**Flow:** Registrar prepares → submits (`promotion.prepare`) → appears in VP queue (§12) → VP approves (key 1) → Principal approves (key 2) → Registrar clicks **Apply** → system moves enrollments (status Promoted), creates next-year enrollments for target sections, marks Graduate students `enrollment_status=Graduated`.
**API:** `POST /api/registrar/promotion-batches`; `POST .../submit`; `POST .../{id}/apply`; VP/Principal approve at `POST /api/promotion-batches/{id}/approve` (key recorded by role).
**Validation:** every student has a decision; target section required for Promote; cannot apply unless status Approved.
**Business logic:** two-key: `vp_approved_by` then `principal_approved_by` must both be set (distinct users, correct roles) before `apply`. Apply is transactional and idempotent (Idempotency-Key). Graduate decisions feed the exit/certificate flow.
**Acceptance:** apply before both keys → 409; after both keys → enrollments updated + audit `promotion.apply`; re-post apply with same key → no double application.

### 7.7 Student Exit & Certificates — `/registrar/students/{id}/exit`
**UI:** Two paths — **Transfer/Drop** (reason, exit date → generates **Transfer/Leaving Certificate** PDF, sets status Transferred/Inactive) and **Graduation** (final-year → mark Graduated, generate final transcript + **Completion Certificate**, archive). Plus **Enrollment (bonafide) Certificate** on demand. Certificate requests route to Principal issuance sign-off (governance) then Ready-to-print. Document queue table with status pills.
**Flow:** choose path → confirm → server creates `document_requests` (type), sets student status, queues PDF → if type requires issuance, status NeedsApproval → Principal enables issuance (§11.5) → Ready → print/download.
**API:** `POST /api/registrar/students/{id}/transfer`; `POST .../graduate`; `POST .../certificates` (type); `GET /api/registrar/documents?filter[status]`; `GET /api/documents/{id}/pdf`.
**Validation:** exit date required|date; reason required for transfer/drop; graduate only allowed for top-grade active students.
**Business logic:** status transitions: Active→Transferred (transfer), Active→Graduated (graduate), Active→Inactive (drop). Certificate PDFs use dompdf templates; issuance gated by `settings.transcript_issuance_enabled`. All audited.
**Responsive:** path cards stack; PDF opens in new tab.
**Acceptance:** transfer → status Transferred + Transfer Certificate PDF generated + audit; graduate top-grade student → Graduated + completion cert; graduate a non-final grade → 422.

### 7.8 Transcripts & Certificates — `/registrar/transcripts`
**UI:** Document-type selector (Transcript / Report Card / Transfer/Leaving / Completion / Enrollment). Status counters (Queued, Ready, Needs approval, Returned). Request table (student, document, status, action: prepare/approve/print). Registrar prepares → Principal/VP approve → PDF.
**API:** `GET /api/registrar/documents`; `POST /api/registrar/documents` (student_id,type); `GET /api/documents/{id}/pdf`.
**Business logic:** transcript pulls cumulative grades across terms via the grading service; report card per term; both respect grade-lock/results-release governance where applicable.
**Acceptance:** generating a transcript for a student with grades produces a PDF whose GPA matches the grading service; unreleased term results are excluded if governance blocks them.

### 7.9 Correct Absence Classification
Registrar can open any attendance record flagged for correction and set Excused/Unexcused (senior correction path). `PATCH /api/registrar/attendance/{id}/classification`. Audited `attendance.correct`. Acceptance: correction updates the day's status and links/detaches the notice appropriately.

---

## 8. Teacher portal (`/teacher/*`)

Sidebar: Dashboard, My Classes, Attendance, Gradebook, Announcements (read/class future), Leave Request. Abilities: `attendance.record`, `gradebook.manage`, `grades.enter`, `absence_notice.acknowledge`, `leave.request`. **Every teacher endpoint is section-scoped** (§2.2 scope guards).

### 8.1 Teacher Dashboard — `/teacher`
**UI:** KPI tiles: Today's attendance status for my sections, Consecutive-absence flags (≥3 days), **Guardian absence-notice flags for my homeroom**, Upcoming assessments, **Gradebook setup status** (categories weighted to 100%?), My leave balance. Lists: today's sections, pending absence notices (with Acknowledge), quick links.
**API:** `GET /api/teacher/dashboard`.
**Business logic:** all metrics filtered to the teacher's assigned sections/homeroom.
**Acceptance:** a teacher sees only their sections; another teacher's data never appears.

### 8.2 My Classes / Roster — `/teacher/classes`
**UI:** List of assigned sections+subjects; open a section to view roster, timetable, per-student report link.
**API:** `GET /api/teacher/sections`; `GET /api/teacher/sections/{id}/roster`.
**Acceptance:** returns only sections in the teacher's `teaching_assignments`/homeroom.

### 8.3 Attendance — `/teacher/attendance`
**UI:** Date selector (default today) + section selector (scoped). **Absence-notice band** at top: guardian notices flagging students for the selected date, each with **Acknowledge**. Roster list: each student row shows name + status toggle **Present / Absent / Tardy / Excused**. Students **flagged by a notice default to Excused** with a 🚩 "notice on file" marker; teacher may switch to Present if the child attended. **Mark all present** helper. **Submit** persists the day.
**Flow:** pick date+section → roster loads with defaults applied → adjust toggles → Submit → server upserts one `attendance_records` row per student for that date → absent rows trigger guardian notification → audit. Acknowledge on a notice sets status Acknowledged (does not itself write attendance).
**Components:** date picker, section select, `<x-pill>` toggles, `<x-btn primary>` Submit.
**API:** `GET /api/teacher/attendance?section_id&date` (returns roster + applied defaults + notices); `POST /api/teacher/attendance` (section_id, date, marks[]); `POST /api/teacher/absence-notices/{id}/acknowledge`.
**Validation:** date not in the future beyond today; section must be scoped; status in enum; one entry per student; cannot submit a locked/again-submitted day without edit rights (edit allowed same term).
**Business logic:** **classify-at-attendance-time** — a day flagged by an approved-notice pre-fills Excused and links `absence_notice_id`; Submit is the moment the record is written (never pre-written). Absent → queue guardian notification via NotificationService. Consecutive-absence flag computed on read. Duplicate submit for same (student,section,date) upserts (edit), never duplicates (UQ).
**Responsive:** roster → stacked cards on phone; status toggles become a segmented control; sticky Submit bar.
**Acceptance:**
- Flagged student defaults to Excused with marker; switching to Present persists Present and keeps the notice as history.
- Submitting with an Absent → a guardian notification row is queued; audit `attendance.record`.
- Re-submitting the same date edits in place (no duplicate rows); future date → 422.
- Acknowledge → notice status Acknowledged; no attendance row created by that action.

### 8.4 Gradebook — `/teacher/gradebook`
**UI:** Class/Subject/Term selectors (scoped). **Category setup**: table of assessment categories with **weight %**, a running **Total weight** indicator (must equal 100%), **Add/Edit/Delete category**, and within each category **Add Assessment** (name, max score). **Enter scores** grid: students × assessments, numeric inputs with max-score guard, live computed category + weighted term result + letter/GPA preview. **Report-card comment** card (homeroom, per student, ≤500 chars) with Save. **Save Gradebook**, **Export Sheet**.
**Flow:** set categories (weights → 100%) → add assessments → enter scores → live preview → Save → server upserts grades, recomputes GPA. Comment saved separately.
**Components:** weight editor, score grid, `<x-form-field>` textarea (comment), `<x-btn>`.
**API:** `GET /api/teacher/gradebook?section_id&subject_id&term_id`; `POST /api/teacher/categories` / `PATCH`/`DELETE`; `POST /api/teacher/assessments` / `PATCH`/`DELETE`; `POST /api/teacher/grades` (bulk upsert); `PUT /api/teacher/report-card-comment` (student_id, term_id, comment).
**Validation:** Σ category weight = 100.00 (block save otherwise, show delta); weight 0–100; assessment max_score>0; score 0..max_score numeric; comment ≤500.
**Business logic (grade computation):** term result = Σ over categories of (category's normalized student % × weight_pct/100); letter/GPA from the department's `grade_scale_bands`; if the department's bands have `gpa_point` NULL (lower school) → produce letter/descriptive only, no GPA. Cumulative = weighted/averaged across the year's terms per the finalized rule (placeholder until sample docs). Grades are continuously visible to guardians/students as entered; a **term lock** (governance) freezes further edits — post-lock edits require the grade-change two-key flow (§11 / `grade_change_requests`).
**Responsive:** score grid gets horizontal scroll with a frozen first (student) column; on phone, switch to per-student entry (one student, all assessments) with prev/next.
**Acceptance:**
- Saving with category weights ≠ 100% → 422 with the exact delta; = 100% saves.
- Entering a score > max_score → rejected inline and 422 server-side.
- HS/MS section shows GPA; Elementary/Pre-School shows letter/descriptive with no GPA.
- After term lock, a score edit → blocked; a grade-change request is created and routed to VP→Principal.
- Report-card comment persists per (student, term) and appears on that term's report card.

### 8.5 Leave Request — `/teacher/leave`
**UI:** Leave-balance strip per type (Annual/Sick/Unpaid) showing **remaining · reserved (pending)**. Submit form: type, from/to dates, reason. "My requests" list with status pills; **Edit** and **Cancel** enabled only while **Pending**; locked once decided. Note: "Requests route to HR; pending days are reserved against your balance until decided."
**Flow:** submit → status Pending, days reserved into `leave_balances.pending` → appears in HR queue → HR decides → on Approved: pending→used and `staff_attendance` On-Leave rows auto-generated for the range; on Rejected/Cancelled: pending released.
**API:** `GET /api/teacher/leave` (balances + my requests); `POST /api/teacher/leave`; `PATCH /api/teacher/leave/{id}` (Pending only); `POST /api/teacher/leave/{id}/cancel` (Pending only).
**Validation:** type exists; to_date ≥ from_date; from_date ≥ today; **remaining = allocated − used − pending ≥ requested days** (for capped types) else 422 "insufficient balance"; edit/cancel only when status=Pending (else 409).
**Business logic:** balance reservation math (above); `submitted_by = self`. Never hard-delete — Cancel is a status change.
**Responsive:** balance strip stacks; form single-column; requests → cards.
**Acceptance:**
- Two overlapping Pending requests that together exceed the allowance → the second is refused (422).
- Editing a decided request → 409; cancelling a Pending → status Cancelled, pending days released.

### 8.6 Announcements (Teacher) — read now; class-authoring is a documented follow-up
Teacher sees announcements targeted to School/their Department/their Section (read-only in v1). `GET /api/announcements` (scoped).

---

## 9. Treasurer portal (`/treasurer/*`)

Sidebar: Dashboard, Source Prep, Import Records, Validate & Match, Imported Records, Fee Reports, History, Visibility Rules. Abilities: `fees.import`, `fees.resolve_unmatched`, `fees.batch_revert`, `fees.report`, plus manage visibility. **No transactional endpoints exist** — the module is import + display only.

### 9.1 Treasurer Dashboard — `/treasurer`
**UI:** Boundary chips ("Sun account, not Sun Plus", "No transactions"). KPIs: Upload cycle, **Matched records** (m/total), **Need review** (unmatched), Visible users (roles). Workflow strip (Sun finalized → export → correct → upload → validate → publish → portal). Operational queue: **Unmatched rows / Restricted rows / Printable reports** with next-actions. **Recent batches** table (batch, source, rows, matched, status, visibility). Collection-rate-by-quarter chart.
**API:** `GET /api/treasurer/dashboard`.
**Acceptance:** unmatched count equals rows with `match_status='Unmatched'` in non-reverted batches.

### 9.2 Source Prep — `/treasurer/source-prep`
**UI:** Guidance + template download describing the required export columns, crucially the **ISMS student-ID matching key** column. "Prepare source file" helper.
**API:** `GET /api/treasurer/import-template` (xlsx).
**Acceptance:** template contains the agreed key column and required headers.

### 9.3 Import Records — `/treasurer/import`
**UI:** `<x-file-drop>` (xlsx/csv). On select → parse preview showing detected columns mapped to fields, row count, and a first pass of matched/unmatched. **Start import** creates a batch in Draft/Review.
**Flow:** upload → parse+map → preview (matched/unmatched/restricted counts) → confirm → batch created, rows inserted with `match_status`, unmatched rows have `student_id=NULL`.
**API:** `POST /api/treasurer/imports` (multipart; Idempotency-Key). Returns batch id + summary.
**Validation:** mimes csv,xlsx; max 5MB; required headers present; amount/balance numeric; txn_date parseable; **unknown students are not silently imported** (flagged Unmatched, never dropped).
**Business logic:** matching key = `raw_account_id` → `students.student_id_number` (via agreed mapping). Re-import of a corrected row updates the existing student-period record (no duplicates) keyed by (student, period, txn_date). Restricted (SDA) rows flagged `is_restricted=1`.
**Responsive:** preview table scrolls; drop zone full-width.
**Acceptance:** file with 8 unmatched rows → batch created with unmatched_count=8, those rows `student_id=NULL, match_status=Unmatched`; malformed numeric → 422 with row numbers.

### 9.4 Validate & Match — `/treasurer/validate`
**UI:** Counters (Uploaded/Matched/Unmatched/Restricted). Row table with **Issue** (Matched / Sun-ID mismatch / Name conflict / SDA allowance / Not in ISMS) and **Action** (Accept / Manual match / Review / Restrict / Hold). **Manual-resolution drawer**: selected row → suggested ISMS record(s) → **Confirm match**. Note: "Unknown students are not silently imported; duplicates update the existing record."
**Flow:** open unmatched row → drawer suggests candidates (by name/DOB/near-ID) → confirm → row gets `student_id`, `match_status=Resolved` → counters update. Restrict toggles `is_restricted`. Hold parks a row.
**API:** `GET /api/treasurer/batches/{id}/rows?filter[match_status]`; `POST /api/treasurer/rows/{id}/match` (student_id); `PATCH /api/treasurer/rows/{id}` (restrict/hold); `POST /api/treasurer/batches/{id}/publish`.
**Validation:** manual match target must be an existing student; cannot publish while unmatched rows remain unresolved (or require explicit "publish with N held").
**Business logic:** publish flips batch → Published and makes rows portal-visible (respecting restricted). Every match/restrict/publish audited.
**Acceptance:** resolving all unmatched → publish enabled; publishing → rows visible to fee viewers; restricted rows never appear to Guardian/Student.

### 9.5 Imported Records — `/treasurer/imported`
**UI:** Searchable per-student fee table (student, period, amount, balance, account status pill, restricted?). Drill to a student's fee statement.
**API:** `GET /api/treasurer/fees?q&filter[status]&filter[period]`.
**Acceptance:** restricted rows visible to Treasurer/leadership but flagged; hidden entirely from families.

### 9.6 Fee Reports — `/treasurer/reports`
**UI:** Per-student and summary fee reports; charts (fee-status donut Paid/Partial/Outstanding, outstanding-by-department bar, collection-rate trend). **Download/print PDF**.
**API:** `GET /api/treasurer/reports/summary`; `GET /api/treasurer/reports/student/{id}/pdf`.
**Acceptance:** summary in <10s; per-student PDF hides restricted lines when generated for family distribution.

### 9.7 History — `/treasurer/history`
**UI:** Batch table (batch, source type, rows, matched, status, visibility) with **View · Revert** on published batches. Immutable audit-detail note: "Critical actions are logged: upload, validation, manual match, restricted-row setting, publish, report generation, and **batch revert. Reverting removes exactly that batch's rows and is itself recorded.**"
**Flow:** Revert → confirm dialog ("removes N rows from batch X") → server deletes only that batch's `imported_fee_records`, sets batch status Reverted, records audit. Idempotent.
**API:** `GET /api/treasurer/batches`; `POST /api/treasurer/batches/{id}/revert` (Idempotency-Key).
**Validation:** revert allowed on Published/Review batches; a re-revert is a no-op (idempotent).
**Business logic:** revert scoped strictly to that batch_id; never touches other batches; audited `fees.batch_revert`.
**Acceptance:** revert of batch X removes exactly X's rows, leaves other batches intact, sets status Reverted, writes audit; replay → no change.

### 9.8 Visibility Rules — `/treasurer/visibility`
**UI:** Read-reference of who sees imported fees (Principal/VP/Registrar/Treasurer read-only; Guardian/Student see own non-restricted) and the SDA-restricted rule. Toggle to (re)confirm restricted classification per import.
**API:** `GET /api/treasurer/visibility`.
**Acceptance:** matches the RBAC scope guards in §2.2.

---

## 10. HR portal (`/hr/*`)

Sidebar: Dashboard, Staff Records, Attendance, Leave Management. Abilities: `staff.manage`, `staff_attendance.record`, `leave.decide`, `leave.request` (on behalf).

### 10.1 HR Dashboard — `/hr`
**UI:** KPIs: Total staff by department, Active/On-leave today, Staff attendance rate, **Pending leave requests**. Lists: today's on-leave, pending queue shortcut.
**API:** `GET /api/hr/dashboard`.
**Acceptance:** on-leave-today count = staff with an approved leave spanning today (source of truth = approved leave).

### 10.2 Staff Records — `/hr/staff`
**UI:** Searchable/filterable roster (name, role_type, department, join date, status pill, phone). **Add staff** drawer; per-staff profile with **status change** (Active/On Leave/Probation/Inactive) and **offboarding = set Inactive** (never delete).
**API:** `GET /api/hr/staff?q&filter[dept]&filter[status]`; `POST /api/hr/staff`; `PATCH /api/hr/staff/{id}`; `PATCH /api/hr/staff/{id}/status`.
**Validation:** staff_id_number unique; role_type in enum; joined_date date; phone format. Creating staff also creates the paired user + Spatie role (coordinated with Admin user-create; HR creates the HR-owned profile fields).
**Business logic:** offboard = status Inactive + user deactivate; audited.
**Acceptance:** offboard → staff Inactive + user cannot log in; reactivate restores; no hard delete.

### 10.3 Staff Attendance — `/hr/attendance`
**UI:** Daily staff roster with **Present / Absent / Tardy / On-Leave**; **Mark all**, **Submit**. Subtitle: "Separate from student attendance. **On-Leave is auto-filled from approved leave** and can't be overwritten here." Rows on approved leave show On-Leave (locked) with an "auto" marker.
**Flow:** open date → rows load; approved-leave staff pre-set On-Leave (locked, linked to `leave_request_id`); HR marks the rest → Submit → upsert `staff_attendance`.
**API:** `GET /api/hr/staff-attendance?date`; `POST /api/hr/staff-attendance` (date, marks[]).
**Validation:** one row per staff/date (UQ); cannot set a non-On-Leave status on an auto On-Leave row.
**Business logic:** approved-leave On-Leave rows are generated by the leave-approval flow (§10.4) — this screen never contradicts them.
**Acceptance:** a staff member with approved leave today shows locked On-Leave linked to the request; submitting others persists; overwriting an auto On-Leave → 422.

### 10.4 Leave Management — `/hr/leave`
**UI:** Tabs **Pending / Approved / Rejected**, each a request table (staff, type, dates, days, reason, origin). Pending rows have **Approve / Reject**. **Leave balances** panel per staff (Annual/Sick with remaining = allocated − used − pending; footnote explains reservation & that a request exceeding remaining is blocked at approval). Header: "HR can enter leave **on behalf of** non-portal staff." **New request (on behalf)** button.
**Flow (decide):** Approve → validates remaining ≥ days → pending→used, status Approved, `decided_by=HR`, **auto-generate On-Leave staff_attendance rows** for the range (linked). Reject → status Rejected, release pending. **On-behalf create** → same as teacher submit but `submitted_by=HR`, `staff_id=target`.
**API:** `GET /api/hr/leave?filter[status]`; `POST /api/hr/leave/{id}/approve`; `POST /api/hr/leave/{id}/reject`; `POST /api/hr/leave` (on behalf).
**Validation:** decide only on Pending (else 409); approve blocked if remaining < days (422); on-behalf requires target staff_id + type + valid range.
**Business logic:** balance reservation (mirror §8.5) + attendance sync (§10.3). All decisions audited with actor.
**Responsive:** tabs scroll; balances panel stacks; request rows → cards.
**Acceptance:**
- Approve within balance → used += days, pending -= days, On-Leave rows created and linked; live badge decrements.
- Approve exceeding remaining → 422; Reject → pending released; decide a non-Pending → 409.
- On-behalf create → request with `submitted_by=HR`, target `staff_id`, audited.

---

## 11. Principal portal (`/principal/*`)

Sidebar: Dashboard, Approvals, Board Reports, Announcements, Setup & Controls. Abilities: `promotion.approve`, `grade_change.approve`, `transcript.issue`, `board_report.generate`, `announcement.publish`, `settings.governance`, `fees.view_readonly`, `staff.view_readonly`, `audit.view`.

### 11.1 Principal Dashboard — `/principal`
**UI:** Whole-school KPIs (enrollment by department, attendance rate, academic performance summary, imported-fee summary), **pending two-key approval queue** (promotion / transcript-&-certificate release / grade-change), registration-assist, read-only finance, **read-only Staff/HR overview card** (headcount, on-leave today, pending leave — "HR owns entry; surfaced because HR reports to the Principal"), Board reports, Announcements composer link.
**API:** `GET /api/principal/dashboard`.
**Business logic:** all figures read-only aggregates; staff card is read-only (no HR mutation from here).
**Responsive:** KPI grid 4→2→1; staff card full-width strip; approval queue → cards.
**Acceptance:** staff overview is read-only (no edit controls); approval queue counts match pending items.

### 11.2 Approvals — `/principal/approvals`
**UI:** Queue of items needing the Principal key: promotion batches (AwaitingPrincipal), transcript/certificate issuance, grade-change requests (AwaitingPrincipal). Each row: context + **Approve (co-sign)** / **Return**. Two-key items show "VP signed ✓" before the Principal can co-sign.
**Flow:** open item → review → Approve → records `principal_approved_by`; when both keys present the underlying action becomes applicable (promotion → Registrar can Apply; grade-change → applied; certificate → issuance enabled).
**API:** `POST /api/promotion-batches/{id}/approve`; `POST /api/grade-change-requests/{id}/approve`; `POST /api/documents/{id}/issue`.
**Validation:** cannot Principal-approve before VP key (409); approver must differ from preparer.
**Business logic:** enforces two distinct keys by distinct users with correct roles; all audited.
**Acceptance:** Principal approve before VP → 409; after VP → item becomes Approved and downstream action unlocks.

### 11.3 Board Reports — `/principal/board-reports`
**UI:** Generate the Board pack: **total students & by class**, **year-over-year registered-student comparison**, **religious-background summary**. Preview + **download PDF**.
**API:** `GET /api/principal/board-report?year`; `GET .../pdf`.
**Business logic:** counts from `students`/`enrollments`; YoY compares active academic years; religion summary from `students.religion`.
**Acceptance:** totals reconcile with enrollment counts; PDF renders all three sections.

### 11.4 Announcements — `/principal/announcements`
**UI:** Composer: title, body, **audience** (School / Staff / Department / Section) with dependent selectors; **Publish**. List of published announcements.
**API:** `POST /api/principal/announcements`; `GET /api/announcements`.
**Validation:** title required|max150; body required; audience in enum; department_id/section_id required_if audience is Department/Section.
**Business logic:** publish sets `published_at`, author, audience scope; delivery respects audience on read (§8.6, guardian/student notice board). Audited.
**Acceptance:** a Section-targeted announcement appears only to that section's teachers/guardians/students.

### 11.5 Setup & Controls (Governance) — `/principal/setup`
**UI:** **Governance controls** card (Principal-owned toggles): **Promotion window** open/close, **Grade lock (per term)**, **Transcript & certificate issuance**, **Release term results**. Institution profile, academic calendar, grade scale, notifications, and security are shown **read-only, labeled Admin-managed** (technical settings live in §6.7). Subtitle states the governance-vs-technical split.
**Flow:** toggle a governance control → confirm → writes `settings` (group=governance) → affects downstream gates (grade lock blocks teacher edits; results-release exposes term report cards; issuance enables certificate/transcript printing).
**API:** `GET /api/principal/governance`; `PUT /api/principal/governance/{key}`.
**Validation:** key in the governance allowlist only (Principal cannot write technical settings — 403).
**Business logic:** grade lock per term is read by the gradebook service to block edits and force grade-change requests; results-release per term gates guardian/student visibility of that term's report card if the school opts into a release step.
**Acceptance:** locking a term → teacher grade edits blocked + change-request path enabled; enabling issuance → Ready certificates become printable; a Principal PUT to a technical key → 403.

---

## 12. VP Academic portal (`/vp/*`)

Sidebar: Dashboard, Approvals, Department Performance, Fees (read-only). Abilities: `promotion.approve` (key 1), `grade_change.approve` (key 1), `subjects.manage`, `fees.view_readonly`.

### 12.1 VP Dashboard — `/vp`
**UI:** Department-level academic performance, promotion candidates, pending approvals (VP key), read-only imported fees.
**API:** `GET /api/vp/dashboard`.

### 12.2 Approvals (VP key) — `/vp/approvals`
**UI:** Items awaiting the first key: promotion batches (AwaitingVP), grade-change requests (AwaitingVP). **Approve (sign)** advances to AwaitingPrincipal.
**API:** `POST /api/promotion-batches/{id}/approve` (records `vp_approved_by`); `POST /api/grade-change-requests/{id}/approve`.
**Business logic:** VP is key 1; cannot also be the preparer; advances state to AwaitingPrincipal.
**Acceptance:** VP approve moves item to AwaitingPrincipal; VP cannot approve an item they prepared.

### 12.3 Subjects & Teaching Assignments — `/vp/subjects`
**UI:** Subject catalogue CRUD + assign subject-teachers to sections (the academic half of class structure; Registrar owns sections+homeroom).
**API:** `GET/POST/PATCH /api/vp/subjects`; `POST /api/vp/teaching-assignments`.
**Acceptance:** UQ(section,subject) enforced; only `Teacher` role_type assignable.

---

## 13. Guardian & Student portals

### 13.1 Guardian portal (`/guardian/*`) — mobile-first, read + one write
Sidebar/tabs: Dashboard, Attendance, Grades & Reports, Fees, Notices, **Notify Absence**. Ability `child.view_own` + scope to linked children; `absence_notice.submit`.

**Dashboard `/guardian`:** child selector (if multiple), tiles: attendance rate this term, latest grades by subject, current fee balance (owed/paid/outstanding — **restricted lines hidden**), status of submitted absence notices.
**Attendance `/guardian/attendance`:** per-child attendance history + rate; Excused days show the linked notice.
**Grades & Reports `/guardian/grades`:** per-term grades (continuously visible as entered), report card download (respects results-release governance); homeroom comment shown.
**Fees `/guardian/fees`:** imported fee status; **never** shows `is_restricted` rows; download statement (restricted lines excluded).
**Notices `/guardian/notices`:** announcements targeted to the child's school/department/section.
**Notify Absence `/guardian/notify-absence`:** child selector, from/to dates, reason; "This notifies your homeroom teacher and flags the date(s); Excused applies at attendance time — this is not an approval request." "My notices" history (Submitted/Acknowledged/Cancelled) with **Edit/Cancel while upcoming**.

**API:** `GET /api/guardian/dashboard`; `GET /api/guardian/children/{id}/attendance|grades|fees`; `GET /api/guardian/notices`; `POST /api/guardian/absence-notices`; `PATCH`/`POST .../cancel` (while upcoming); `GET /api/guardian/children/{id}/report-card/pdf`.
**Validation:** every child endpoint asserts the child is linked to this guardian (else 403). Absence: from_date ≥ today, to_date ≥ from_date, reason ≤255; edit/cancel only while status=Submitted and dates not passed.
**Business logic:** absence notice is a **notification** (accepted on submit, status Submitted; never approved/denied); it flags the homeroom roster; Excused is set at attendance time by the teacher. Restricted fee lines filtered at query level.
**Responsive (primary phone surface):** tab bar bottom or hamburger; tiles stack 1-per-row; tables → cards; Notify-Absence form single-column with sticky Submit.
**Acceptance:**
- A guardian requesting another family's child → 403.
- Submit absence notice → status Submitted, appears in homeroom teacher's flags; editable only while upcoming; after dates pass → edit/cancel disabled.
- Fees view never includes a restricted (SDA) line, in UI or API payload.

### 13.2 Student portal (`/student/*`) — mobile-first, self-view only
Tabs: Dashboard (GPA this term, attendance rate, upcoming assessments, recent grades), Grades, Schedule, Attendance history, **Download report card**. Ability `self.view_own` scoped to `auth user's student`.
**API:** `GET /api/student/dashboard|grades|schedule|attendance`; `GET /api/student/report-card/pdf`.
**Validation/logic:** all endpoints scoped to the authenticated student's id; no write actions; restricted fee data not exposed (students don't see fees unless the school opts in — default: no fee tab for students).
**Responsive:** identical mobile patterns as Guardian.
**Acceptance:** a student can only ever retrieve their own records; no mutating route exists for the student role.

---

## 14. Consolidated API reference

All under `/api`, session/Sanctum auth, RBAC+scope enforced server-side, envelopes per §3.7. `{id}` = numeric.

**Auth/session:** `POST /login`, `POST /logout`, `POST /forgot-password`, `POST /reset-password`, `POST /set-password`.
**Admin:** `GET /admin/dashboard`; `GET|POST /admin/users`, `PATCH /admin/users/{id}`, `POST /admin/users/{id}/reset-credentials`, `PATCH /admin/users/{id}/status`, `POST /admin/users/bulk-import`; `POST /admin/retention-actions`; `GET|POST /admin/academic-years`, `POST /admin/academic-years/{id}/activate`, `PATCH /admin/terms/{id}`; `GET|PUT /admin/grade-scales/{departmentId}`; `GET|POST|PATCH /admin/teaching-assignments`; `GET /admin/audit`, `GET /admin/audit/export`; `GET /admin/settings`, `PUT /admin/settings/{group}`, `POST /admin/export-snapshot`.
**Registrar:** `GET /registrar/dashboard`; `GET|POST /registrar/students`, `GET|PATCH /registrar/students/{id}`, `POST /registrar/students/{id}/transfer`, `POST /registrar/students/{id}/graduate`, `POST /registrar/students/{id}/certificates`; `GET|PATCH /registrar/guardians/{id}`, `POST /registrar/guardians/{id}/links`, `DELETE /registrar/guardians/{id}/links/{studentId}`; `GET|POST|PATCH /registrar/sections`, `POST /registrar/sections/{id}/homeroom`, `POST /registrar/sections/{id}/enroll`; `POST /registrar/promotion-batches`, `POST /registrar/promotion-batches/{id}/submit`, `POST /registrar/promotion-batches/{id}/apply`; `GET|POST /registrar/documents`, `GET /documents/{id}/pdf`; `PATCH /registrar/attendance/{id}/classification`.
**Teacher:** `GET /teacher/dashboard`; `GET /teacher/sections`, `GET /teacher/sections/{id}/roster`; `GET|POST /teacher/attendance`, `POST /teacher/absence-notices/{id}/acknowledge`; `GET /teacher/gradebook`, `POST|PATCH|DELETE /teacher/categories`, `POST|PATCH|DELETE /teacher/assessments`, `POST /teacher/grades`, `PUT /teacher/report-card-comment`; `GET|POST /teacher/leave`, `PATCH /teacher/leave/{id}`, `POST /teacher/leave/{id}/cancel`.
**Treasurer:** `GET /treasurer/dashboard`; `GET /treasurer/import-template`; `POST /treasurer/imports`; `GET /treasurer/batches`, `GET /treasurer/batches/{id}/rows`, `POST /treasurer/batches/{id}/publish`, `POST /treasurer/batches/{id}/revert`; `POST /treasurer/rows/{id}/match`, `PATCH /treasurer/rows/{id}`; `GET /treasurer/fees`; `GET /treasurer/reports/summary`, `GET /treasurer/reports/student/{id}/pdf`; `GET /treasurer/visibility`.
**HR:** `GET /hr/dashboard`; `GET|POST /hr/staff`, `PATCH /hr/staff/{id}`, `PATCH /hr/staff/{id}/status`; `GET|POST /hr/staff-attendance`; `GET|POST /hr/leave`, `POST /hr/leave/{id}/approve`, `POST /hr/leave/{id}/reject`.
**Principal:** `GET /principal/dashboard`; `GET /principal/board-report`, `GET /principal/board-report/pdf`; `POST /principal/announcements`; `GET /principal/governance`, `PUT /principal/governance/{key}`.
**VP:** `GET /vp/dashboard`; `GET|POST|PATCH /vp/subjects`, `POST /vp/teaching-assignments`.
**Shared approvals:** `POST /promotion-batches/{id}/approve`, `POST /grade-change-requests/{id}/approve`, `POST /documents/{id}/issue`.
**Guardian:** `GET /guardian/dashboard`; `GET /guardian/children/{id}/attendance|grades|fees`, `GET /guardian/children/{id}/report-card/pdf`; `GET /guardian/notices`; `GET|POST /guardian/absence-notices`, `PATCH /guardian/absence-notices/{id}`, `POST /guardian/absence-notices/{id}/cancel`.
**Student:** `GET /student/dashboard|grades|schedule|attendance`, `GET /student/report-card/pdf`.
**Announcements (read, scoped):** `GET /announcements`.

## 15. Cross-cutting business logic (invariants — implement once, in Services)

1. **Two-key approval (promotion, grade-change, and — as issuance — certificates):** requires `vp_approved_by` then `principal_approved_by`, two **distinct** users holding the correct roles, **neither** the preparer. State machine advances Draft→AwaitingVP→AwaitingPrincipal→Approved→Applied. Apply is idempotent.
2. **Leave balance reservation:** submit → `pending += days`; approve → `pending -= days, used += days`; reject/cancel → `pending -= days`. Accept/approve only if `allocated − used − pending ≥ days` for capped types. Prevents concurrent-pending over-spend.
3. **Leave ↔ staff-attendance sync:** approving a leave auto-generates `staff_attendance` rows (status On-Leave, `leave_request_id` set) for the range; **approved leave is the source of truth**; HR cannot overwrite an auto On-Leave row.
4. **Absence notice = notify, not approve:** accepted on submit (status Submitted), flags the homeroom roster, homeroom teacher **Acknowledges**; the day is classified **Excused at attendance time** (teacher, one tap) and links `absence_notice_id`; Registrar may correct. No attendance is written ahead of the day. Editable/cancellable by guardian only while upcoming.
5. **Attendance:** exactly one record per (student, section, date) — UQ; submit upserts; Absent queues a guardian notification; classify-at-attendance defaults for flagged students.
6. **Grade computation:** weighted by category (Σ weights = 100); letter/GPA from department `grade_scale_bands`; GPA omitted where `gpa_point` is NULL (lower school). Term lock (governance) freezes edits → post-lock changes go through the grade-change two-key flow.
7. **Finance is import-only:** no transaction endpoints; matching key resolves `raw_account_id`→`student_id_number`; unmatched rows flagged (never dropped) and resolved manually; batch revert removes exactly that batch's rows; restricted (SDA) rows hidden from Guardian/Student everywhere.
8. **Never hard-delete** users, staff, students, leave, absence notices, or audit rows — deactivate/cancel/inactive statuses instead. Audit is append-only.
9. **Every state change is audited** (§3.2) inside the same transaction, capturing the actor's role at the time.
10. **RBAC + scope on every endpoint** — ability check (matrix §2.2) then row-scope (teacher→section, guardian→child, student→self, restricted-fees filter).

## 16. Global validation reference (server-side, FormRequests)
Dates: real dates; ranges `to ≥ from`; future-guards where specified (attendance not future; absence/leave `from ≥ today`). Money/scores: numeric, non-negative, within bounds (score ≤ max_score; amounts DECIMAL). Strings: length caps per schema (§4). Enums: `in:` the exact enum set. Uniqueness: emails, staff/student IDs, (student,section,date), (student,term) comment, section+subject, weight-sum=100. Files: mimes csv,xlsx max 5MB, header validation, per-row rejection with line numbers. Every mutating request re-checks RBAC ability + row scope regardless of client state.

## 17. Non-functional acceptance criteria
- **Performance:** standard list/query p95 < 3s at 100 concurrent users over 5,000 students + 5 years history; PDF (report card/fee report) < 5s each; fee summary < 10s. Verify with a seeded load test.
- **Availability:** target 99.9% during 08:00–16:00 Mon–Sat; graceful 503 page otherwise.
- **Security:** bcrypt; TLS/HSTS; CSRF on web; 30-min idle timeout; 5-attempt lockout; RBAC+scope on 100% of endpoints (test: each role gets 403 on every other role's mutating routes); no PII/passwords in logs; restricted fees never serialized to family responses (payload-level test).
- **Audit:** every action in §3.2 produces exactly one append-only row with the actor's role; no update/delete path exists (test).
- **Responsive:** at 375px width, no horizontal scroll on any page; all tables render as cards; touch targets ≥44px; Guardian/Student fully usable one-handed.
- **Accessibility:** labels on all inputs; visible focus; ARIA on toasts/modals; color is never the only status signal (pills carry text).
- **Data integrity:** all invariants in §15 have a Pest test; import of a dirty file leaves the DB consistent (transactional).

## 18. Recommended build order & Definition of Done
**Order:** (1) Migrations + seeders (§4) → (2) Auth/RBAC/session/audit skeleton (§3.1–3.3, §2) → (3) shared components + app shell + responsive rules (§3.4–3.6) → (4) Admin (users, academic year, grade scale) → (5) Registrar (register, sections, enroll) → (6) Teacher (attendance + absence classify, gradebook) → (7) Guardian/Student read portals + notify-absence → (8) HR (staff, attendance, leave + reservation/sync) → (9) Treasurer (import → validate → publish → reports → revert) → (10) Principal/VP (two-key approvals, governance, board reports, announcements) → (11) Promotion + exit/certificates + grade-change flows → (12) PDFs, notifications, hardening, load/security tests.

**Definition of Done (per screen):** all 9 spec facets implemented; FormRequest validation with tests; RBAC+scope policy with a negative test per foreign role; audit rows asserted; loading/empty/error/permission states present; responsive at 375/768/1280 verified; Pest feature test green; no business logic outside a Service.

---
*ISMS Implementation Specification · companion to Report v1.4 · build-ready.*