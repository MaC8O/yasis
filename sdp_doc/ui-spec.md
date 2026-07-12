# ISMS Figma — Screen-by-Screen Design Description

**File:** `ISMS — Wireframe` (`sb8nkliPG7kbiaveKlJhpb`), single page `0:1`, **71 frames**.
**Canvas:** every frame is **1280 × 1024** (desktop; guardian/student portals are drawn at desktop width — their mobile behavior is documented in §7, not as separate frames).
**Design idea referenced:** the "Management system Prototype" (Figma Make) — a **shadcn/ui** component vocabulary on a **warm-paper ground with deep pine-green ink and a warm-gold academic accent** (chart palette pine → gold → teal → clay → sage). This Figma wireframe implements that same aesthetic natively.

---

## 1. Design-system foundations (applies to every screen)

### 1.1 Layout grid & structure
Every authenticated screen uses one **two-zone shell**:
- **Sidebar** — fixed left column, **0–252px** (nav rail is 214px wide inside a 252px dark panel), full-height `1024`, near-black `#131B1B`.
- **Content** — starts at **x≈280**, spans to **x≈1230** (a ~950px content column with ~50px right margin). Vertical rhythm: **header band at y=24–106**, then content from **y≈132** down.

The Login screen (`6:576`) is the only non-shell layout: a single centered card on the paper ground.

### 1.2 Spacing scale
Consistent 8-based rhythm observed across frames:
- Page gutter (content start) **x=280**; card inner padding **24px** (text insets at +26 from card edge).
- Card-to-card horizontal gap **20px**; vertical gap **16–20px**.
- KPI tiles: **205px wide × 104px tall**, 20px gaps, label at +18/+15 inset, value ~+40.
- Sidebar nav items: **214 × 42**, **50px** vertical step, first item at y≈108–112.
- Table rows ~**40–44px**; section dividers are 1–2px lines in `#E1E6E1`.
- Buttons: height **40–44px**, radius 8, horizontal padding ~16px; pills **22–26px** tall, radius 11–13.

### 1.3 Color tokens (exact hex)
| Token | Hex | Use |
|---|---|---|
| Pine green (primary) | `#1F573D` | active nav pill, primary buttons, key values, selected toggles |
| Sidebar ink | `#131B1B` | sidebar panel |
| Paper | `#F6F7F2` | app background |
| Ink | `#131B1B` | headings/body |
| Mute | `#576661` | captions, secondary text, table sub-labels |
| Card border | `#DFE4DF` / `#B2BFB2` | card & table outlines |
| Line | `#E1E6E1` | dividers |
| Gold | `#C9A227` / ink `#8A6D10` | academic accent, warnings/gov chips |
| Tint green | `#D6EDDE` | positive KPI tint, success pill bg |
| Tint blue | `#D6E5F7` (ink `#2E5AAC`) | info / Excused |
| Tint amber | `#FAEDC2` (ink `#8A6D10`) | pending/attention |
| Tint red | `#F2D9D4` (ink `#B0392B`) | negative/outstanding/inactive |

Status→color mapping is consistent everywhere: **green** = Approved/Present/Paid/Active/Published; **amber** = Pending/Submitted/Tardy/Partial/On-Leave/Review; **red** = Rejected/Absent/Outstanding/Inactive; **blue** = Excused/info; **neutral grey** = draft/default.

### 1.4 Typography
Font **Inter** (the wireframe's native equivalent of the prototype's Libre Franklin). Scale: page title **24px/700**, card title **18px/600**, KPI value **28px/700**, body **13–14px/400**, caption/label **11–12px** (labels often UPPERCASE `#576661`), pill text 11px/600. Numerals used for KPIs are tabular.

### 1.5 Elevation & shape
Flat design: white cards (`#FFFFFF`) on paper, **1px** border, **radius 16–18**, no drop shadows (borders + tint provide separation). Modals/drawers get a scrim overlay.

---

## 2. Global navigation model
Each portal has its own **sidebar nav set**; the active item is a **pine-green filled pill** with white text, others are transparent with muted text. Top of sidebar: "YASIS ISMS" wordmark + portal label ("Admin Portal", "Treasurer Portal", etc.). Bottom of sidebar: a **Sign out** button (full-width, y≈930). Some nav items carry a **count badge** (small chip) — e.g., Teacher → Attendance shows unacknowledged absence-notice count; HR → Leave shows pending count.

**Nav sets per portal:**
- **Login** — none (public).
- **Admin** — Dashboard · User Management · Teacher Class Assignment · Academic Year · Grade Scale · Audit Logs · System Settings.
- **Registrar** — Dashboard · Register Student · Students · Guardians · Sections · Teacher Assignment · Transcripts · Announcements.
- **Teacher** — Dashboard · Classes · Attendance ⟨badge⟩ · Gradebook · Announcements · Leave Request.
- **Treasurer** — Dashboard · Source Prep · Import Records · Validate & Match · Imported Records · Fee Reports · History · Visibility Rules.
- **HR** — Dashboard · Staff Records · Attendance · Leave Management ⟨badge⟩.
- **Principal** — Dashboard · Approvals ⟨badge⟩ · Board Reports · Announcements · Setup & Controls.
- **Guardian** — Dashboard · Attendance · Grades & Reports · Fees · Notices · Notify Absence (+ a child switcher when multiple children).
- **Student** — Dashboard · My Grades · Attendance · Timetable · Notices.

**Prototype clickthrough:** frames are wired with `ON_CLICK → NAVIGATE` reactions between sidebar items and their target frames (a frame never links to itself, so the active item is inert). This is what makes the Figma prototype clickable.

---

## 3. Shared component catalog (as drawn)
- **KPI tile** — tinted rounded rectangle; UPPERCASE label (top), large value, sub-caption. Tint carries meaning (green/blue/amber/red/neutral).
- **Card** — white, bordered, radius 16, optional title + muted subtitle header; the primary content container.
- **Data table** — header row (muted labels), 1px divider, body rows with a status **pill** column and a right-aligned **action** column (text-link actions like Edit · Resend · View, or menu). Empty/зero states show a muted line.
- **Pill / badge** — status chip, tinted per §1.3.
- **Toggle switch** — 38×22 track + 16px knob; ON = pine-green track, knob right; OFF = grey track, knob left. Used for settings/governance.
- **Segmented status control** — the attendance Present/Absent/Tardy/Excused buttons; selected = pine-green filled, others = soft.
- **Buttons** — primary (pine fill, white text), soft (light fill), danger (red), ghost/text.
- **Chips row** — small rounded reference chips (e.g., finance boundary "No transactions", governance "RBAC on").
- **Drawer / modal** — right drawer for create/edit (users, staff, manual-match); confirm dialogs for destructive actions.
- **Charts** — donut (fee-status), horizontal bars (outstanding-by-dept, collection-rate-by-quarter) using the pine/gold/teal palette.
- **Workflow strip** — a horizontal chip→arrow→chip sequence (finance pipeline, promotion flow).
- **File-drop** — dashed drop zone (Treasurer import).

---
## 4. Responsive behavior model
The frames are authored at the **1280 desktop canvas**; the wireframe encodes intent, and the responsive reflow below is the build target (also in the implementation spec §3.5):
- **≥1024 (lg):** exactly as drawn — sidebar visible (252px), KPI tiles in a 4–5 row, tables full-width, two/three-column card rows.
- **768–1023 (md):** sidebar collapses to an icon rail or hamburger drawer; KPI tiles wrap to 2 columns; multi-column card rows stack to 1–2; wide tables scroll horizontally.
- **<768 (phones — the Guardian/Student primary surface):** sidebar → top hamburger drawer; KPI tiles stack 1-per-row; **every data table becomes a stack of cards** (each row → a card of label:value pairs, actions as full-width buttons); forms single-column with a sticky bottom submit; modals become full-screen sheets; charts collapse to a labeled bar list. Touch targets ≥44px, no horizontal page scroll.

The Guardian/Student "**Menu — (Htoo/Paw)**", "**(Child 2)**", "**(Guardian B)**" frames are *content-state variants* (different child/guardian selected, or the nav-drawer open state) rendered on the same desktop canvas — they demonstrate the **child switcher** and **menu-open** states rather than a narrower breakpoint.

---

## 5. Login — `6:576` ("00 · Login")
**Layout:** full paper background; a single **centered card** (~420px) vertically centered. No sidebar.
**Spacing:** card padding 32px; fields stacked with ~16px gaps; generous whitespace around the card.
**Components:** wordmark "YASIS ISMS" + tagline; **email** field, **password** field (with show/hide), "Remember me" checkbox, full-width **Sign in** primary button (pine green); "Forgot password?" text link; a small TLS/lock indicator; a **demo-accounts** panel listing one login per role (each a clickable chip that fills the form).
**Colors:** pine-green primary button; muted labels; paper ground; subtle card border.
**Interactions:** click a demo chip → fills credentials; Sign in → (prototype) navigates to that role's dashboard frame; error state = red inline banner above fields.
**Navigation:** entry point; routes to the selected role's portal.
**Responsive:** card becomes full-width with 16px gutters on phones; inputs 44px tall; button full-width (already).

---

## 6. Admin portal (7 screens)

### 6.1 Admin — Dashboard `61:2`
**Layout:** header ("Admin Dashboard" + subtitle) → **4 KPI tiles** row (Active users / Accounts to action / Login activity / Last backup) → a two-column band: **"System access controls"** card (chips: RBAC on, session 30m, lockout 5, *Retention policy set*) on the left and a **shortcuts** panel (User Management, Academic Year, Audit Logs, Backup & Export) on the right → **"Recent login activity"** table (user, role, time, IP).
**Spacing:** tiles 205×104 @20px gaps; cards 24px padding.
**Components:** KPI tiles (amber tint on "Accounts to action"), chips, shortcut buttons, data table.
**Colors:** amber tint flags attention; green chips for healthy security posture.
**Interactions:** tiles/shortcuts deep-link (e.g., "Accounts to action" → User Management pre-filtered).
**Responsive:** tiles 4→2→1; recent-activity table → cards.

### 6.2 Admin — User Management `67:2`
**Layout:** header with **Add User** + **Bulk Import** buttons (top-right) → searchable/filterable **users table** (Name, Email, Role pill, Status pill, Last login, **Action**) → bottom action row (Access Control · Data Retention · View Audit Trail) → a **legend** line listing per-user actions.
**Components:** data table with per-row text actions (Edit · Reset/Resend · Deactivate/Reactivate · Audit), create/edit **drawer** (name, email, role select, department, status), confirm dialogs, file-drop for bulk.
**Colors:** role pills use neutral/green; status pills green(Active)/amber(pending)/red(Inactive).
**Interactions:** Add User → right drawer; Reset → confirm → toast; Deactivate → confirm; Data Retention → modal.
**Responsive:** table → cards; drawer → full-screen sheet.

### 6.3 Admin — Teacher Class Assignment `2090:583`
**Layout:** sections table with homeroom teacher + subject-teacher assignments; assign via drawer. Labeled as the initial technical-setup surface (operational ownership is the Registrar).
**Components:** table + assignment drawer + teacher combobox.
**Interactions:** assign/reassign; duplicate subject-per-section blocked.

### 6.4 Admin — Academic Year `68:2`
**Layout:** list of academic years (label, dates, **Active** badge) → term editor sub-table (4 terms with dates) → "Create year" form.
**Components:** table, active pill (green), inline date fields, **Activate** button.
**Interactions:** create year auto-creates 4 terms; Activate flips the single active year.
**Colors:** active year highlighted with green pill.

### 6.5 Admin — Grade Scale `68:145`
**Layout:** per-department **band editor** (letter, min%, max%, GPA point) as an editable table; a validation banner appears if bands overlap/gap.
**Components:** editable table rows, add-band button, validation banner (red if invalid).
**Interactions:** save validates contiguity/coverage 0–100; lower-school departments allow GPA-null descriptive bands.

### 6.6 Admin — Audit Logs `68:285`
**Layout:** filter bar (date range, user, action, entity) → **read-only** log table (When, User, Role, Action, Entity, ID, IP) → Export CSV.
**Components:** filter controls, dense table, export button. **No edit/delete controls anywhere** (immutable trail).
**Colors:** neutral; action text may be mono-styled.
**Interactions:** filter narrows rows; export downloads CSV.

### 6.7 Admin — System Settings / Access Control `68:497`
**Layout:** sectioned page: **Access Control** (read-only RBAC permission matrix: rows = capabilities, columns = roles, ✓ marks) → **Security rules** card (session timeout 30m, failed-login lockout 5) → **Immutable audit logging** card (records user, role, timestamp) → a note that Settings also covers Notifications & SMTP, Academic calendar, Backups & Export → bottom buttons (Manage Users, View Audit Logs, **Backups & Export**).
**Components:** matrix table with ✓ cells, security cards, toggle switches, chips.
**Colors:** ✓ marks in pine green; cards bordered; governance-vs-technical note in muted text.
**Interactions:** matrix is reference (non-editable here); channel/security toggles persist; Backups & Export button.

---
## 7. Registrar portal (9 screens)

### 7.1 Registrar — Dashboard `62:2`
**Layout:** header → **4 KPI tiles** (Active students / Pending registrations / Guardian links % / **Documents queue**) → a two-panel band: **Work queue** (New applications / Missing documents / **Promotion list → Prepare**) on the left, **Quick actions** panel (Students, Guardians, Sections, Transcripts, Announcements) with a summary line "Graduation/exit queue: N · Certificate requests: N" on the right → **Recent record activity** → an **absence-classification correction** card at the bottom.
**Components:** KPI tiles, work-queue rows with action links, quick-action buttons, correction card.
**Interactions:** "Prepare" opens promotion staging; correction card links to attendance fix.
**Responsive:** tiles 4→2→1; queues → cards.

### 7.2 Registrar — Register Student `2065:63`
**Layout:** a long **multi-section form**: Personal (ID, names, DOB, gender, department, religion, admission date) → Guardian linking (search existing / add new: name, email, phone, relationship, primary) → Academic (assign to section). Primary **Save & Enroll** at the bottom.
**Components:** `<x-form-field>` inputs/selects, guardian search combobox, primary button; inline validation hints.
**Colors:** required markers; error text red beneath fields.
**Interactions:** save creates student + guardian link + enrollment; missing fields block submit with inline errors.
**Responsive:** sections stack single-column; sticky Save bar on phone.

### 7.3 Registrar — Students `2073:2`
**Layout:** searchable **students table** (ID, Name, Dept, Section, Status pill, Guardian?), row → profile with tabs (Overview, Guardians, Enrollment, Attendance, Grades, Exit & Certificates).
**Interactions:** row opens profile; actions Edit, Transfer/Drop, Graduate, Enrollment Certificate.

### 7.4 Registrar — Guardians `2073:3`
**Layout:** guardian table (name, phone, email, #children); edit contact; link/unlink students; set primary.
**Interactions:** setting a new primary flips the previous; can't leave a student guardian-less without a warning.

### 7.5 Registrar — Sections `2073:4`
**Layout:** per department-year **sections table** (name, homeroom teacher, roster size, subjects) → **Add Section**, **Assign Homeroom Teacher**, place students → an **Ownership** note card ("Registrar creates sections & assigns homeroom; VP owns subjects; promotion is Registrar-prepared, co-approved").
**Interactions:** create section (unique per year+dept); assign homeroom (must be a Teacher); enroll students.

### 7.6 Registrar — Teacher Class Assignment `2090:584`
**Layout:** the Registrar-accessible teacher/section assignment view (parallels the Admin one; ownership sits here operationally).
**Interactions:** assign subject-teachers/homeroom to sections.

### 7.7 Registrar — Transcripts & Certificates `2073:5`
**Layout:** header "Transcripts & Certificates" → **Document type** selector (Transcript · Report Card · Transfer/Leaving · Completion · Enrollment) with a types caption → **status counters** (Queued / Ready to print / Needs approval / Returned) → **request table** (student, document, status pill, action) → workflow note (Registrar prepares → Principal/VP approve → PDF print/export).
**Components:** dropdown, counter chips, table, print/export buttons.
**Interactions:** prepare a document → routes to approval → becomes Ready → print. Different document types share the queue.

### 7.8 Registrar — Announcements `2104:2`
**Layout:** registrar-scoped announcement composer/list (parity with Principal composer; role-scoped audience).

### 7.9 Registrar — Dashboard cross-links
The exit/certificate flow (`Student Exit & Certificates`) is reached from the Students profile (§7.3) rather than a standalone frame.

---

## 8. Teacher portal (6 screens)

### 8.1 Teacher — Dashboard `2090:2`
**Layout:** header → **KPI tiles** (Today's attendance / Consecutive absences / **Absence-notice flags** / Upcoming assessments / **Gradebook setup status** / My leave balance) → today's sections list → pending absence-notice list with **Acknowledge** buttons.
**Interactions:** everything scoped to the teacher's sections; Acknowledge marks a notice seen.

### 8.2 Teacher — Classes `2090:3`
**Layout:** list of assigned sections+subjects → open a section for roster/timetable/student-report links.

### 8.3 Teacher — Attendance `2090:4`
**Layout:** date + section selectors → an **absence-notice band** at top (guardian notices flagging students today, each with **Acknowledge**) → **roster** list, each row = student + a **Present/Absent/Tardy/Excused** segmented control; flagged students default to **Excused** with a 🚩 "notice on file" marker → **Mark all present** + **Submit**.
**Components:** date picker, section select, segmented status control (selected = pine fill), flag markers, submit button.
**Colors:** Excused = blue pill/selection; Absent = red; Present = green; Tardy = amber.
**Interactions:** classify-at-attendance — flagged rows pre-fill Excused; teacher can switch to Present; Submit persists the day; Acknowledge is separate from marking.
**Responsive:** roster → stacked cards; segmented control wraps; sticky Submit.

### 8.4 Teacher — Gradebook `2090:5`
**Layout:** Class/Subject/Term selectors → **category setup** table (category, **weight %**, edit/del) with a **Total weight: 100%** indicator and **Add Category** → **Enter scores** grid (students × categories) with live weighted result + letter/GPA preview → **Report-card comment (homeroom)** card (input + Save) → **Save Gradebook** / **Export Sheet**.
**Components:** weight editor, score grid, comment textarea, buttons; a note that weights are visible to students/guardians read-only.
**Interactions:** weights must total 100% to save; scores capped at max; comment saved per student/term.
**Responsive:** score grid scrolls with frozen student column; phone → per-student entry.

### 8.5 Teacher — Announcements `2090:6`
**Layout:** announcements list (school/department/section scoped; read in v1, class-authoring is a documented follow-up).

### 8.6 Teacher — Leave Request `4076:2`
**Layout:** **leave-balance strip** per type (remaining · reserved-pending) → submit form (type, from/to, reason) → **My requests** list with status pills; **Edit/Cancel** enabled only while **Pending**, locked once decided → note "routes to HR; pending days are reserved."
**Components:** balance cards, form, request rows with conditional (disabled) action buttons.
**Colors:** Pending amber, Approved green, Rejected red.
**Interactions:** submit reserves pending days; edit/cancel gated to Pending; decided rows show locked/disabled controls.

---
## 9. Treasurer portal (9 screens)

### 9.1 Treasurer — Dashboard `2099:2`
**Layout:** header with **boundary chips** ("Sun account, not Sun Plus", "No transactions") → **4 KPI tiles** (Upload cycle / Matched records m/total / Need review / Visible users) → a **questionnaire-aligned workflow strip** (Sun finalized → export → correct → upload → validate → publish → portal) → two panels: **"Finance office reality"** (chips + Prepare source file) and **"Operational queue"** (Unmatched rows / Restricted rows / Printable reports with next-actions) → **Recent batches** table → **Collection-rate-by-quarter** bar chart.
**Components:** chips, KPI tiles, workflow strip (chip→arrow→chip), queue table, bar chart.
**Colors:** amber for "Need review"; pine/gold bars in the chart.
**Interactions:** queue next-actions deep-link to Validate/Reports; chart is read-only.

### 9.2 Treasurer — Source Preparation `2101:2`
**Layout:** guidance card describing required export columns incl. the **ISMS student-ID matching key**, plus a template download.
**Interactions:** download import template.

### 9.3 Treasurer — Import Fee Records `2099:3`
**Layout:** **file-drop** (xlsx/csv) → parse **preview** (detected columns mapped, row count, matched/unmatched/restricted) → **Start import**.
**Components:** dashed drop zone, preview table, primary button.
**Interactions:** upload → preview → confirm creates a Draft/Review batch; unknown students flagged (not dropped).

### 9.4 Treasurer — Validate & Match Import `2099:4`
**Layout:** **counters** (Uploaded / Matched / Unmatched / Restricted) → row table with **Issue** (Matched / Sun-ID mismatch / Name conflict / SDA allowance / Not in ISMS) and **Action** (Accept / Manual match / Review / Restrict / Hold) → **manual-resolution drawer** (selected row → suggested ISMS record → Confirm match) → note "unknown students not silently imported; duplicates update existing record."
**Components:** counters, issue/action table, resolution drawer.
**Colors:** issue tags tinted (amber/red); restricted rows flagged.
**Interactions:** open unmatched row → drawer suggests candidates → confirm maps to a student; Restrict/Hold toggles; **Publish** when resolved.

### 9.5 Treasurer — Imported Fee Records `2099:5`
**Layout:** searchable per-student fee table (student, period, amount, balance, **account-status pill**, restricted?) → drill to a student's statement.
**Colors:** Paid green / Partial amber / Outstanding red pills.

### 9.6 Treasurer — Fee Reports `2099:6`
**Layout:** per-student + summary reports → **charts**: fee-status **donut** (Paid/Partial/Outstanding), **outstanding-by-department bar**, collection-rate trend → **download/print PDF**.
**Components:** donut + bar charts (pine/gold/teal), report table, export buttons.
**Interactions:** generate/download; family-facing exports exclude restricted lines.

### 9.7 Treasurer — Import History `2099:7`
**Layout:** **batch table** (batch, source type, rows, matched, status pill, visibility) with **View · Revert** on published batches → an **audit-detail** note listing logged critical actions incl. "batch revert removes exactly that batch's rows and is itself recorded."
**Interactions:** Revert → confirm dialog ("removes N rows from batch X") → status Reverted; idempotent.

### 9.8 Treasurer — Student Fee Detail `2101:3`
**Layout:** a single student's fee statement (transaction lines, running balance, status) — the drill-down target from Imported Records/Reports.

### 9.9 Treasurer — Visibility Rules `2101:4`
**Layout:** reference of who sees imported fees (Principal/VP/Registrar/Treasurer read-only; Guardian/Student see own non-restricted) and the SDA-restricted rule; per-import restricted (re)confirmation toggle.

---

## 10. HR portal (6 screens)

### 10.1 HR — Dashboard `4070:2`
**Layout:** header → KPIs (Total staff by dept / Active-on-leave today / Staff attendance rate / **Pending leave**) → today's on-leave list → pending-queue shortcut.
**Interactions:** on-leave-today derives from approved leave (source of truth).

### 10.2 HR — Staff Records `4070:32`
**Layout:** searchable/filterable **staff roster** (name, role, department, join date, **status pill**, phone) → **Add staff** drawer → per-staff profile with **status change** and **offboard = Inactive** (never delete).
**Colors:** status pills Active green / On Leave amber / Probation neutral / Inactive red.
**Interactions:** add/edit via drawer; offboard sets Inactive + deactivates login.

### 10.3 HR — Attendance `4070:62`
**Layout:** daily **staff roster** with **Present/Absent/Tardy/On-Leave** segmented controls, **Mark all**, **Submit** → subtitle "separate from student attendance; **On-Leave auto-filled from approved leave**." Rows on approved leave show **On-Leave (locked)** with an "auto" marker.
**Interactions:** approved-leave rows are locked On-Leave (linked to the request); HR marks the rest; overwriting an auto row is blocked.

### 10.4 HR — Leave Management (3 tab states) `4070:92` (Pending) · `4072:34` (Approved) · `4072:175` (Rejected)
**Layout:** **tabs** Pending / Approved / Rejected → request table (staff, type, dates, days, reason, **origin** — self vs "entered by HR on behalf") with **Approve/Reject** on Pending → a **Leave balances** panel (per staff: Annual/Sick remaining = allocated − used − pending, with a reservation footnote) → header note "HR can enter leave on behalf of non-portal staff" + **New request (on behalf)**.
**Components:** tab bar, request table, approve/reject buttons, balances panel, footnote.
**Colors:** Pending amber, Approved green, Rejected red pills; tab active = pine underline/pill.
**Interactions:** Approve validates remaining ≥ days (else blocked), moves pending→used, auto-creates On-Leave attendance; Reject releases pending; the three frames are the tab-swap states of one screen.
**Responsive:** tabs scroll; balances panel stacks; rows → cards.

---
## 11. Principal portal (5 screens)

### 11.1 Principal — Dashboard `2103:2`
**Layout:** header → **5 KPI tiles** (Total enrollment 900 / Attendance 94.8% / Academic avg 88.6% / Fee collection 83% / Pending approvals) → mid band: **Enrollment by department** table + **Approval queue** (Promotion / **Transcript & certificate release** / Grade-change, each "VP reviewed") → three bottom cards (Assist registration / Finance oversight / Communication with **Create announcement**) → a full-width **read-only Staff/HR overview** card (Staff headcount 62 / On leave today 3 / Pending leave 2, "HR owns entry; surfaced because HR reports to the Principal") with a **Read-only** chip.
**Components:** KPI tiles, tables, approval-queue rows, HR summary card with tinted stat blocks.
**Colors:** approval items amber (awaiting); HR card neutral with a blue "Read-only" chip.
**Interactions:** approval rows open the co-sign action; Create announcement → composer.
**Responsive:** KPI grid 5→2→1; HR card full-width strip; queue → cards.

### 11.2 Principal — Approvals `2103:3`
**Layout:** queue of items needing the **Principal key** (promotion batches, transcript/certificate issuance, grade-change), each row with context + **Approve (co-sign)** / **Return**; two-key items show "VP signed ✓" first.
**Interactions:** Principal-approve only enabled after VP key; approving unlocks the downstream action (Registrar Apply, certificate issuance, grade change).

### 11.3 Principal — Board Reports `2103:4`
**Layout:** generator for the Board pack — **total students & by class**, **year-over-year registered-student comparison**, **religious-background summary** — with a preview and **download PDF**.
**Components:** report section cards, YoY comparison table/bars, download button.
**Interactions:** generate → preview → PDF.

### 11.4 Principal — Announcements `2103:5`
**Layout:** **composer** (title, body, **audience**: School/Staff/Department/Section with dependent selectors) + **Publish** → list of published announcements.
**Interactions:** publish scopes delivery by audience; Section target reaches only that section.

### 11.5 Principal — Setup & Controls `2103:6`
**Layout:** **Governance controls** card (Principal-owned toggles: **Promotion window**, **Grade lock — current term**, **Transcript & certificate issuance**, **Release term results**, plus two-key & assist toggles) → Institution profile, Academic calendar, Grade scale, Security cards shown **read-only, "Admin-managed"** → subtitle stating governance-vs-technical split.
**Components:** toggle switches (governance = editable; technical = read-only/labeled), cards.
**Colors:** editable governance toggles pine when ON; Admin-managed cards muted.
**Interactions:** governance toggles gate downstream behavior (grade lock blocks teacher edits; results-release exposes report cards; issuance enables printing); technical settings are display-only here.

---

## 12. Guardian portal (mobile-first; 6 base screens + content-state variants)

The **base guardian set** (`6:2` Dashboard, `6:154` Attendance, `6:264` Grades & Reports, `6:391` Fees, `6:487` Notices, `4080:2` Notify Absence). Variant frames — `21:2/34:*` **(Child 2)**, `21:149` **(Guardian B, 1 child)**, `39:2…1204` **Menu — (Htoo)** and **(Paw)** — are the *same screens* showing a different selected child / guardian / the nav-drawer-open state, used to demonstrate the **child switcher** and menu.

### 12.1 Guardian — Dashboard `6:2` (+ variants)
**Layout:** top bar with **child switcher** (name + avatar) → summary tiles: **attendance rate this term**, **latest grades by subject**, **current fee balance** (owed/paid/outstanding — restricted lines hidden) → status of submitted **absence notices** → notice feed.
**Components:** child switcher, KPI tiles, mini grade list, fee summary, notice list.
**Colors:** green/amber/red fee status; blue Excused markers.
**Interactions:** switch child → all tiles re-scope; tiles deep-link to sub-pages.
**Responsive:** the guardian portal is the **primary phone surface** — tiles stack, tables → cards, bottom-sticky actions, hamburger nav (the "Menu —" frames show the open drawer).

### 12.2 Guardian — Attendance `6:154`
Per-child attendance history + rate; **Excused** days show the linked notice; calendar/list of daily statuses with colored markers.

### 12.3 Guardian — Grades & Reports `6:264`
Per-term grades (continuously visible as entered), subject breakdown, **report card download** (respects results-release), homeroom comment shown.

### 12.4 Guardian — Fees `6:391`
Imported fee status per period (amount/paid/balance, status pill); **never shows restricted (SDA) lines**; download statement (restricted excluded).

### 12.5 Guardian — Notices `6:487`
Announcements targeted to the child's school/department/section, as a reverse-chronological list.

### 12.6 Guardian — Notify School of Absence `4080:2`
**Layout:** header pill "Guardian · Read + Notify" → an info banner ("a notification, not an approval; flags the date(s); Excused applies at attendance time") → child selector + from/to dates + reason → **Submit** ("your teacher will be notified right away") → **My notices** history (Submitted / Acknowledged / Cancelled) with **Edit/Cancel while upcoming**.
**Interactions:** submit creates a notice (status Submitted); edit/cancel only while upcoming; history rows show status pills.
**Colors:** Submitted amber, Acknowledged green, Cancelled neutral.

---

## 13. Student portal (self-view; 6 screens)

- **Student — Dashboard `40:2`** — tiles: **GPA this term**, attendance rate, upcoming assessments, recent grades.
- **Student — My Grades `40:154`** (and **Grades & Breakdown `2086:2`**) — per-subject grades with category breakdown; drill to a subject.
- **Student — Math Detail `45:2`** — a subject drill-down: assessments, scores vs max, weighted contribution, letter/GPA.
- **Student — Attendance `40:272`** — personal attendance history + rate, colored day markers.
- **Student — Timetable `40:373`** — weekly class schedule grid.
- **Student — Notices `40:503`** — announcements targeted to the student's section/department/school.

**Layout/colors/components:** identical patterns to the Guardian portal (tiles, cards, colored status), but **strictly read-only, self-scoped** — no write actions, no fee tab by default. **Responsive:** mobile-first, same reflow as Guardian.

---

## 14. Summary inventory (71 frames)
| Portal | Count | Frames |
|---|---|---|
| Login | 1 | 6:576 |
| Admin | 7 | 61:2, 67:2, 2090:583, 68:2, 68:145, 68:285, 68:497 |
| Registrar | 9 | 62:2, 2065:63, 2073:2, 2073:3, 2073:4, 2090:584, 2073:5, 2104:2 (+profile/exit sub-views) |
| Teacher | 6 | 2090:2, 2090:3, 2090:4, 2090:5, 2090:6, 4076:2 |
| Treasurer | 9 | 2099:2, 2101:2, 2099:3, 2099:4, 2099:5, 2099:6, 2099:7, 2101:3, 2101:4 |
| HR | 6 | 4070:2, 4070:32, 4070:62, 4070:92, 4072:34, 4072:175 |
| Principal | 5 | 2103:2, 2103:3, 2103:4, 2103:5, 2103:6 |
| Guardian | 6 base + ~11 variants | 6:2/154/264/391/487, 4080:2, + 21:*, 34:*, 39:* content-state variants |
| Student | 6 | 40:2/154/272/373/503, 45:2, 2086:2 |

All screens share the one shell, spacing scale, token palette, component catalog, and status-color language defined in §1–§4; the warm-paper / pine-green / gold shadcn aesthetic from the referenced prototype is applied consistently throughout.

*ISMS Figma screen description · file sb8nkliPG7kbiaveKlJhpb · 71 frames.*