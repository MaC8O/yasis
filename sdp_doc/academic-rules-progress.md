# Academic and fee rules: progress

Source of truth: [YASIS_Academic_and_Fee_Rules.md](YASIS_Academic_and_Fee_Rules.md). The build order is in its section 11: one branch and one PR per phase. Each phase needs the owner's approval of its plan and migrations before any code is written, and the owner's go-ahead before the next phase starts. Nothing is merged, pushed to yasis01 or deployed by Claude.

| Phase | Status | Branch / PR |
|---|---|---|
| 1. Grade levels on sections | Merged | `grade-levels`, PR #40 |
| 2. Curriculum (`subject_offerings`, Grade 11-12 electives) | Merged | `curriculum`, PR #41 |
| 3. Grading schemes per grade level | Merged | `grading-schemes`, PR #42 |
| 4a. Calculation service, frozen results, promotion recommendation | Merged (with the follow-up: a batch needs a frozen year) | `results-service`, PR #43; `promotion-needs-frozen-year`, PR #44 |
| 4b. Switch the pages to the new results, retire the department grade scale, Q20 ranking setting | Merged, deployed to staging (fresh reset 2026-11-27) | `results-pages`, PR #45 |
| 5. KG1 to Grade 10 gradebook template and calculated attendance | Merged (with the follow-up: archive instead of delete) | `gradebook-template`, PR #46; `archive-gradebook-items`, PR #47 |
| 6. Report cards | Merged | `report-cards`, PR #48 |
| 7. Statement of Marks | Merged | `statement-of-marks`, PR #49 |
| 8a. Fee schedule (reference data only) | Merged | `fee-schedule`, PR #50 |
| 8b-1. Sun Accounts ledger import: parser, import, validation, matching, overrides, re-import, revert, Treasurer screens | Merged | `sun-ledger-import`, PR #51 |
| 8b-2. Family statements, reused codes (T3), guardian and student Fees pages, reports and dashboards, retiring the CSV upload, demo seeder | Merged | `sun-family-view`, PR #52 |
| 9a. The school's answers: curriculum and results (KG-4 counting, SUPW words, Grade 10 and 11-12 tracks, Final column, dense ranking, Grade 10 G.P.A row, personality once a year, Statement of Marks) | PR open, awaiting review | `school-answers-results`, PR #53 |
| 9b. The school's answers: the Grades 11-12 gradebook template and the attendance rules | Plan to be sent after 9a's PR | |

## Phase 1: grade levels (merged)

**Built:**
- **`grade_levels` table:** `code` (fixed key), `name`, unique `sort_order`, `archived_at`. It holds 14 levels in this order: KG1, KG2, then 1 to 12.
- **`sections.grade_level_id`:** a nullable foreign key. A level that sections use can't be deleted.
- **Backfill from section names:**
  - "Grade N" (N from 1 to 12) gets level N.
  - "Kindergarten 1" / "KG1" / "KG-1" gets KG1, and the same pattern gives KG2.
  - Anything else stays empty. On staging that is only **Nursery**.
- **Registrar's section form:**
  - The grade level is required for a new section.
  - On edit, a level that has been set can't be cleared.
  - A section with no level can still be saved.
  - Changing the level is audited by code, for example 9 → 10.
- **Sorting:** sections are listed by grade level, with sections that have no level last.
- **Admin page (Grade Levels):**
  - Add a level (it goes at the end), rename, move up or down, retire and restore.
  - Retiring is refused while any section, including an archived one, uses the level.
  - Codes can't be changed after a level is created.
  - Every change is audited with role `admin`.
- **Principal:** a read-only "Grade levels" card on Setup & Controls.

**Decisions (owner, 2026-11-25):**
- KG1 and KG2 are separate levels, not one shared KG. Promotion from KG1 to KG2 needs a target, the report card's "Assigned to Grade" line needs to be able to say KG2, and class position must not rank two years together. Both levels still belong to the KG-4 grading and report card group and the KG-6 fee band.
- Nursery stays unset until the school answers Q2.
- Department and grade level stay independent: no check that the pair matches.
- The rules file is committed. `YASIS_Sun_Finance_Import.md` and any `.docx` in `sdp_doc/` are gitignored, because the repository is public.

## Phase 2: curriculum (merged)

**Built:**
- **School-wide subjects:** 18 of them, with no department, created by a migration. `subjects.department_id` is now nullable.
  - The codes are MORAL, MYAN, ENG, MATH, SCI, MAPE, CHAR, SOCST, COMP, PE, MUSIC, SUPW, BIO, CHEM, PHYS, WHIST, WGEO and ACCT.
  - Grade-level differences live on the curriculum rows, not on the subject.
- **`subject_offerings` table:** one row per subject per grade level, 139 in all, seeded by migration. Each row holds:
  - the report label and its position (unique per level)
  - compulsory or optional, and the elective slot
  - credit hours, DECIMAL(3,1)
  - the grading mode (`score` or `pass_fail`) and whether it counts in the result
  - `pending_question`, a flag such as "Q6" that stays until the Admin ticks "Confirmed by the school"
  - `archived_at`: rows are retired, never deleted
- **`student_electives` table:** one subject per slot per student per academic year. A choice can be changed but not cleared.
- **Admin page (Curriculum):**
  - Pick a grade level, then edit a row, add a catalogue subject, move a row up or down, and retire or restore it.
  - Retiring is refused while a student has chosen that row this year.
  - Every change is audited with role `admin`.
- **Principal:** the same page, read-only.
- **Registrar page (Electives):**
  - It lists the active year's sections whose grade level has slots, with the number of missing choices.
  - The page for a section has one dropdown per student per slot, saved together. Each new or changed choice is audited.
- **Expected subjects:** a section expects its grade level's active curriculum, and a section without a grade level expects none. The code-suffix rule (ENG10 meaning Grade 10) is deleted.
- **VP subject catalogue:** shows "School-wide" for these subjects, and refuses to remove a subject that is in a curriculum.
- **Seeders:** both use the curriculum subjects, and the invented demo catalogue is dropped. Every class gets its grade level's curriculum (Nursery gets none). Some Grade 11-12 electives are seeded.

**Decisions (owner, 2026-11-26):**
- The school's subjects are shared and school-wide; they are not per department.
- **Grade 10 placeholder:** none of the 13 subjects is counted, and all are flagged Q6. **Phase 4 must refuse to calculate a Grade 10 point total until the Admin has confirmed the counted subjects**, that is, until no Grade 10 row is still flagged Q6.
- The Q4, Q5 and Q7 rows are flagged as well as Q6.
- The Principal gets the read-only Curriculum page in this phase.
- **Staging's existing demo data is not migrated.** Its teaching assignments and grades stay on the old demo subjects, which are kept for their history. Staging will show those as "not in this grade's curriculum" until it is reset.

## Phase 3: grading schemes (merged)

**Built:**
- **`grading_schemes` table:** one row per scheme, three in all, created by a migration.
  - `code` (`KG_9`, `G10`, `G11_12`) and `result_basis` (`average`, `point_total`, `gpa`) are fixed: the calculation branches on them.
  - `result_max`: the top of the class-of-pass range (100, 70, 4.00). It is editable for the point total and the GPA.
  - The rounding rule: `mark_decimals`, `result_decimals` and `rounding_mode` (`half_up` or `down`). In every scheme, marks are rounded to whole numbers, half up, before the band lookup; the average and the point total are rounded to whole numbers, and the GPA to two decimals.
  - `subject_fail_rule`: `any_counted` (KG-9 and Grade 10), `any_compulsory` (11-12) or `none`.
  - `pending_question`: Q10 on every scheme (rounding), plus Q19 on Grade 10 (the subject-fail rule).
- **`grading_scheme_bands` table:** two sets per scheme, `mark` (mark to letter, with points for Grade 10 and the grade value for 11-12) and `result` (class of pass). Each band has a label, an inclusive `min` and `max`, an optional `value`, `is_fail` and `pending_question`.
  - KG-9 has a mark set too: PASS 40-100, FAIL 0-39, so every scheme finds the 40% pass mark the same way (the lowest band that is not a fail band).
  - The Grade 10 Fail mark band (0 points) and the Grade 10 Fail class band (below 40 points) are flagged Q19.
- **`grade_levels.grading_scheme_id`:** KG1, KG2 and 1-9 use `KG_9`, 10 uses `G10`, and 11-12 use `G11_12`. A level added later has none until the Admin picks one.
- **Admin page (Grading Schemes):**
  - Pick a scheme. Edit its name, rounding mode and subject-fail rule, and tick "Confirmed by the school" to clear its flag.
  - Edit each band set as a whole: the bands, the decimal places values are rounded to before the lookup and, for the class-of-pass set of the point total and GPA, the top of the range.
  - The set must cover 0 to the top with no gaps and no overlaps at the rounding step (1, 0.1 or 0.01). The fail bands must be the lowest, labels must be unique, and points or grade values can't go up as the mark goes down.
  - Choose the scheme each grade level uses. The page asks for confirmation, and the audit row records the scheme before and after.
  - Every change is audited with role `admin`.
- **Principal:** the same page, read-only, and a summary card on Setup & Controls (each scheme, its grade levels and class-of-pass bands). This replaces the department grade-scale card, which listed Canteen and Finance too.
- **The department grade scale is untouched:** the old Admin page is renamed "Grade Scale (current, until phase 4)" and points to Grading Schemes. `GradeService` and the report cards still use it.

**Change from the approved plan:** the decimal places (and the point total's and GPA's range top) are edited with each band set, not in the scheme's settings. A separate setting would deadlock: the current bands are invalid on the new step, and bands for the new step are invalid on the old one.

**Decisions (owner, 2026-11-26):**
- Grade 10 below 40 marks or 40 points: both seeded as Fail and flagged with the new **Q19** in the rules file, which gives the 35-point example.
- Grade 10 subject-fail rule: `any_counted`, flagged Q19.
- The old Admin Grade Scale page keeps working until phase 4.

**Band history:** saving a set archives the old rows (`archived_at`) and inserts the new rows with `created_at` at the same moment, so nothing is deleted. `GradingSchemeBand::inForceAt($time)` returns the set in force at a given moment: rows with `created_at` <= T and (`archived_at` null or > T).

## Phase 4a: result calculation and frozen results (merged)

Phase 4 is split in two (owner, 2026-11-26). 4a adds the calculation, the frozen results and the promotion recommendation, while every page families and teachers see keeps the old calculation. 4b switches the pages over and retires the department grade scale.

**Built:**
- **`ResultService`:** for a section and a term, or the year, it gives each student:
  - each subject's mark (two decimals), the mark as printed, and its letter and points or grade value from the scheme's mark bands
  - the average (KG1-9), point total (Grade 10) or GPA (11-12)
  - the class of pass, pass or fail with the scheme's subject-fail rule, and the position in the section
  - for the year, the section 9 recommendation
  - Every rule comes from the grading scheme of the section's grade level and from the curriculum. Arithmetic uses `Brick\Math\BigDecimal`, never floats.
- **How a subject mark is made:** `GradeService::sectionMarks()` uses the same formula as today's gradebook, in exact decimals. Each category is the mean of its item percentages, and the mark is the weighted mean of the graded categories. The fixed categories are phase 5.
- **Status of a result:**
  - `complete`
  - `incomplete`: a counted subject has no mark, or an 11-12 student has no elective in a slot. For a year, a counted subject is missing a mark in some term.
  - `blocked`: no grade level, no scheme, or the level waits on a blocking question (`ResultService::BLOCKING_QUESTIONS`: Q6 on the curriculum, Q19 on the scheme).
  - Only a complete result has a value, class, pass/fail and position. Each subject's own mark always shows.
- **Frozen results:**
  - **Tables:** `result_freezes` (term, or null for the year; `rules` and `reason`; frozen and lifted, by whom), `result_freeze_schemes` (a copy of every scheme's settings and both band sets), `student_results`, `student_subject_results` (with a copy of each curriculum row).
  - **Lock:** the Principal's grade lock calls `ResultFreezeService::lock()`, which freezes every section of the term in one transaction.
  - **Year:** when every term of the year is locked, the year is frozen too, from the frozen term marks and the last term's rules.
  - **Unlock:** sets `lifted_at` on the term's freeze and the year's, and keeps their rows.
  - **Relock:** by default reuses the rules of the term's previous freeze (its first, unless it was recalculated since). The Principal can choose "Recalculate with the current rules" instead; this needs a written reason and is audited with the rules before and after.
  - **Grade change on a locked term:** an applied change re-freezes that section with the freeze's own rules. The old rows are kept with `superseded_at` and the reason "Grade change #id applied", and positions are recalculated.
  - **Readers:** a locked term with a freeze is read from it and never recalculated. An unlocked term is calculated live. A term locked before this release, with no freeze, is calculated live and flagged until `results:freeze-locked-terms` freezes it.
- **Promotion recommendation (section 9):**
  - The Registrar's "Prepare promotion batch" page shows each student's recommendation and what it is based on (for example "Average 80 · Second · provisional"), and pre-selects it.
  - A different decision needs a reason. The item keeps `recommended_action`, `recommendation_basis` and `override_reason`, and the audit row lists every override.
  - The VP's and Principal's approval pages show each overridden decision and its reason.
  - A recommendation is "provisional" until the year is frozen.
  - Grade 10 gets no recommendation (Q11), and nor does an incomplete or blocked result.
- **Commands:**
  - `php artisan results:freeze-locked-terms --actor=<principal or admin email>` freezes locked terms that have no freeze, and is safe to run again.
  - `php artisan results:compare "<section>" "<term>"` prints, read-only, the old figures (department scale) beside the new ones, per subject and per student.
- **Seeder:** after the gradebook is seeded, it freezes the locked Term 1 as the Principal.

**Decisions (owner, 2026-11-26):**
- **Relock:** reuse the rules of the earlier freeze by default, plus an explicit "Recalculate with the current rules" option with a written reason, audited with the rules before and after. It lets the school correct a locked term once Q10 is answered.
  - Built as "the term's previous freeze" rather than literally the first: they are the same unless the Principal recalculated. This way a later unlock and relock doesn't quietly undo a correction. **Approved by the owner (2026-11-26).**
- The average, point total and GPA are built from the rounded subject marks (Q10).
- Students are ranked by the printed figure (Q13).
- There is no overall result, class or position until every counted subject has a mark. Subject marks still show.

**Follow-up (PR #44, owner, 2026-11-26): a promotion batch needs a frozen year.**
- The batch page still shows the recommendations while the year isn't frozen, marked provisional.
  - A banner explains why the batch can't be submitted and names the terms not locked yet.
  - The "Prepare batch" button is disabled and points to that explanation.
- The server refuses a submit too, before any other check, with the same explanation (`PreparePromotionBatchRequest::authorize()`, `ResultService::promotionBlocker()`). A closed promotion window is still a 403.
- The year freezes when the Principal has locked every term.

**Deploy (staging) for 4a:**
1. `php artisan migrate`: 5 migrations, all new tables except three nullable columns on `promotion_batch_items`.
2. `php artisan results:freeze-locked-terms --actor=<principal email>`, because staging's Term 1 is locked.
3. Run `php artisan results:compare "<section>" "<term>"` on a few sections and compare before going ahead with 4b.

No asset build is needed beyond the usual: the new classes are on the Registrar and Principal pages.

## Phase 4b: pages switched to the new results (merged)

**Built:**
- **Every page that shows grades reads `ResultService`:**
  - the teacher gradebook and its live preview
  - the student and guardian dashboards and grade pages
  - the report-card PDF
- **`ResultService::studentTerm()`:** gives a `StudentTermReport`, which every family page and the PDF read. So the student, the guardian and the report card show the same numbers for the same student and term; a test compares all three.
- **Final and Provisional:** a locked term shows its frozen figures, marked "Final". An unlocked term is calculated live and marked "Provisional".
- **Labels (Q12):** the result is labelled Term average (shown as a percentage), Term point total or Term GPA. The dashboard tiles say "Term …" for the student and "Latest …" for the guardian.
- **Subjects:** each shows its mark as printed and its letter: "B · 3.00" for 11-12, "A · 10 points" for Grade 10, PASS or FAIL for KG-9 and pass/fail subjects. Electives the student doesn't take aren't listed. A subject taught outside the curriculum shows its mark with no letter.
- **Grade 10:** each subject shows its mark and letter with points. The blocked reason (Q6, Q19) replaces the point total and class.
- **Gradebook:**
  - The live column previews the printed mark and the letter with the section's scheme: its bands, decimals and rounding mode.
  - A locked term shows the frozen mark and letter, labelled "Final (frozen …)".
- **Retired:** the Admin Grade Scale page with its hard-delete code (fixing the known bug), its routes, nav item and dashboard tile; `GradeService::computeStudentResult()` and `bandFor()`; and the 4a `results:compare` command, which depended on them.
  - The seeders and the test helper no longer write department bands.
  - The `grade_scale_bands` table, its rows and the `GradeScaleBand` model are kept for past records.
- **Q20 (new):** each grading scheme has a "Position in class" setting:
  - `together` (the default, today's behaviour) ranks everyone by their result.
  - `failed_last` ranks every student who failed after all who passed.
  - It is flagged Q20 on every scheme, copied into each freeze (older freezes read as `together`), and editable on Grading Schemes.
  - Migration `2026_11_28_090000_add_failed_ranking_to_grading_schemes` adds the column and the flag, and widens `pending_question` to 20 characters for "Q10,Q19,Q20".

**Out of scope:** the Principal and VP dashboards average raw `Grade` scores without any scale. They are unchanged.

## Phase 5: fixed gradebook template and calculated attendance (merged)

**Built:**
- **`gradebook_template_categories`:** the school's five categories, all editable by the Admin (name and weight, which must total 100) and read-only for the Principal:
  - Class tests / quizzes 10%
  - Attendance 10% (no items, calculated; flagged Q8)
  - Assignments 10%
  - Participation / obedience / Code of Conduct 10%
  - Examinations 60%
  - Every change is audited and applied to the open terms' gradebooks in the same transaction. Locked terms keep theirs.
- **`grading_schemes.uses_gradebook_template`:** KG_9 and G10 use the template; G11_12 doesn't. Q9 is flagged on G10 and G11_12. It is set by migration and shown read-only.
- **`assessment_categories`:** two new columns:
  - `template_key`: which template category this is (null for a teacher's own category).
  - `archived_at`: a category taken out of use, kept with its items and grades but not counted.
- **Scoring (`GradeService`):**
  - A template category scores marks earned / marks possible over the items the student has a mark for.
  - Attendance comes from `AttendanceScoreService`.
  - A teacher's own category (Grades 11-12) keeps the mean of its item percentages.
  - Categories with nothing graded are left out of the weighting while the term is open.
  - A subject has no mark until one of its items is graded: Attendance alone, being the same for every subject, doesn't make one.
- **Locking (owner's decision 4):**
  - The Principal sees every KG1 to Grade 10 section and subject with an empty items category, and must tick "Lock anyway".
  - In the frozen result, a subject whose Examinations category has no items has no final mark ("No examination was set in …"), so the student's overall result is incomplete.
  - Attendance with no records stays left out.
- **Teacher gradebook (KG1 to Grade 10):**
  - Opening it puts the gradebook on the template.
  - The categories can't be added, renamed, reweighted or deleted (refused on the server too).
  - Items are added inside them, except Attendance, which shows read-only per student with its days.
  - Items can be moved between categories with their grades, audited.
  - Archived categories are listed with their items and a "Move to…" action.
- **Missing attendance records (owner's decision 1):** each student with section school days that have no record for them is listed, with the count, in the gradebook and on the teacher's Attendance page.
- **Existing gradebooks:** `php artisan gradebook:adopt-template --actor=<admin or principal>` moves open-term KG1 to Grade 10 gradebooks onto the template. It is safe to run again and never touches a locked term; the same happens when a teacher opens a gradebook.
  1. A category whose name matches becomes the template category.
  2. A second match is merged into the first.
  3. Items in a category that matches nothing move by their name (exam, mid-term, final → Examinations), then their type (quiz or test → Class tests / quizzes, anything else → Assignments).
  4. A hand-entered Attendance category with items is archived.
  5. Missing categories are created.
  - No grade is deleted.
  - Each change is audited, and each affected teacher gets a one-time notice listing every moved item (shown the next time they open the gradebook, and kept under My notifications).
- **Seeder (`Term1ResultsSeeder`):** complete Term 1 marks and attendance (every weekday) for three sections; Term 1 is then frozen.
  - Grade 9-A (template): 7 complete results, averages 73-81.
  - Grade 10-A (template): blocked by Q6/Q19.
  - Grade 12-A (own categories): 4 GPAs; the fifth student has no elective chosen.
  - The old Quiz/Test demo gradebook is replaced.

**Decisions (owner, 2026-11-27):**
1. Only the days on which the student has their own attendance record count. Section school days with no record for a student are flagged, with the count.
2. Unmatched items move by name and type, and each affected teacher gets a one-time notice.
3. The Admin can rename and reweight only. Use per scheme stays set by migration.
4. While a term is open, empty categories are left out of the weighting. At lock, there is a warning listing empty items categories, and an empty Examinations category means no final mark (incomplete).

**Pages whose numbers change (open terms, KG1 to Grade 10):**
- the teacher gradebook (subtotals, the Attendance column, result and preview)
- the student and guardian grade pages (marks, breakdown, term average) and dashboards
- the provisional report card
- the Registrar's promotion recommendations

Frozen terms and Grades 11-12 don't change.

**Deploy (staging):**
1. `php artisan migrate` (3 migrations).
2. An asset build.
3. `php artisan gradebook:adopt-template --actor=<admin email>`.

A fresh reset with the seeder is the alternative, as last time.

## Phase 6: report cards (merged)

**Built:**
- **Four cards, chosen by `grade_levels.report_card_layout`:** KG1-4 blue, 5-9 green, Grade 10 yellow, 11-12 yellow. They replace the single report card from 4b.
  - **On screen:** one responsive column. Staff open it from Report Cards, students and guardians from Grades.
  - **PDF:** the school's booklet, A4 landscape, two columns per page, two pages, for double-sided printing and folding. Page 1: personality record and class placement | cover. Page 2: acknowledgements | scholarship record.
- **Figures from `ResultService` (`ReportCardService`):** frozen for a locked term, live and marked "Provisional" for an open one.
  - Subjects in curriculum order, with HRS for 11-12, "-" for an option not taken, and PASS/FAIL for a pass/fail subject.
  - First, Second, Third and Final columns. **Final is Term 4's own result** (owner, option B).
  - The G.P.A, Position, Total number in class and Days absent rows. The footnote is printed word for word.
- **Total number in class:** the frozen roster for a locked term, the current roster for an open term.
- **Days absent** is frozen with the term (`student_results.days_absent`, `attendance_pct`), so later attendance edits don't change a locked card.
- **Personality record (`personality_ratings`):**
  - The homeroom teacher rates five traits A, B or C per student per term.
  - Editable until the term is locked, and audited.
  - The card shows the latest rated term.
- **Acknowledgements (`report_acknowledgements`):** the name and date print where each signature goes.
  - **Class teacher:** "Confirm reports" works only once the Principal has locked the term. Before that the button is disabled with the reason, and the server refuses it.
  - **Principal:** turning on "Release term results" is the Principal's sign-off: one row per term, printed on every card. Turning it off withdraws the row (kept, with `withdrawn_at`). Releasing with unconfirmed cards shows a warning listing the classes, and doesn't block.
  - **Guardian:** "I acknowledge this report", on released terms only, once per student and term.
- **Class placement:** on the Final report only, from the applied promotion batch's Promote item (grade, school year, first day of that year's first term).
- **Attendance eligibility (section 8):** `report_rules.min_attendance_pct` (default 80). Students below it are flagged on the homeroom teacher's Report Cards page, on the VP and Principal dashboards, and on the Attendance Check list. Nobody is blocked.
- **Report Card Rules page (`report_rules`):** edited by the Admin and read-only for the Principal, with a "Confirmed by the school" tick to clear each flag. Changes are audited with before and after values.
  - Minimum attendance (Q16)
  - What Days absent counts (Q8)
  - Which term's personality ratings print (Q14)
- **Seeder:** Term 1 of Grade 9-A, 10-A and 12-A has personality ratings, the class teacher's confirmation, the Principal's sign-off and the first guardian's acknowledgement, so those cards print complete.

**Access:**
- **Teacher:** own homeroom classes only; rates and confirms.
- **Registrar, VP and Principal:** every class, read-only.
- **Attendance Check:** VP and Principal.
- **Students and guardians:** released terms only.

**Tests:** `ReportCardTest` (13) and the updated `FamilyResultsTest`. The full suite passes (548).

**Checked in the browser (fresh seed, 1280 px and 390 px):**
- **Homeroom teacher:** ratings, confirm disabled on an open term, the locked term read-only.
- **Guardian:** acknowledge, then the name prints.
- **Student:** same card, no acknowledge button.
- **Registrar:** read-only.
- **VP:** dashboard flag and list.
- **Principal:** release warning, read-only rules.
- **Admin:** rules edit.
- **PDF:** A4 landscape, 2 pages.

**Deploy (staging), when asked:**
1. `php artisan migrate` (5 migrations; the layouts and the three rules are filled in by the migrations).
2. An asset build.

Terms that are already locked have no frozen days absent: their cards fall back to the live count until a re-freeze. A fresh reset with the seeder avoids this.

## Phase 7: Statement of Marks (merged)

**Built:**
- **A new document type, "Statement of Marks",** in the Registrar's Transcripts & Certificates page.
  - The Registrar enters the student ID, the school year, the date of examination (free text, printed as typed in capitals) and the date of issue. This creates a Draft (`document_requests` + `statements_of_marks`), and it is audited.
  - While it is a Draft, its two dates can be changed (audited), and "Preview draft" gives the PDF with a "DRAFT – NOT ISSUED" watermark.
  - "Print (issue)" works only while the Principal's "Transcript & certificate issuance" control is on. Until then the button is disabled with the reason, and the server refuses it. There is no VP/Principal two-key approval: the control is the Principal's go-ahead (owner, 2026-11-27).
  - The first print issues it. The checks run again, the content is saved with the year freeze it came from (`statements_of_marks.content`, `result_freeze_id`), and it is audited. Every reprint is that saved copy and is audited too.
  - If the year is re-frozen after issue (an approved grade change), the record says so and a new statement should be prepared.
- **Made from the frozen year result only:** the year freeze exists once the Principal has locked all four terms.
- **Refusals, each with its reason shown to the Registrar:**
  - The year isn't frozen (the terms not locked yet are listed).
  - Grade 10 (Q11).
  - A level without a layout.
  - An incomplete or blocked year result (the result's own reasons).
  - A student who withdrew that year, or wasn't enrolled.
  - A second draft for the same student and year.
- **Two layouts (`grade_levels.statement_layout`), A4 portrait, one page, laid out like the school's samples:**
  - **KG1 to Grade 9 (`kg9`):** every counted subject with its year score, the first four (Moral, Myanmar, English, Mathematics) in the left column and the rest on the right. Then the accumulative score, RESULT and CLASS OF PASS, and the footer word for word. KG-4's Character Building and Social Study aren't counted (Q4), so they aren't listed, as on the sample.
  - **Grades 11 and 12 (`g11_12`):**
    - Compulsory subjects with letter and HRS. SUPW prints PASS or FAIL with HRS "-".
    - Optional subjects with letter and HRS, "-" for the ones not taken.
    - The GPA, RESULT and CLASS OF PASS.
    - The footer, described next.
- **The 11-12 footer:**
  - The heading "How are the accumulated GPA calculated and CP grouped?".
  - A conversion table generated from the grading scheme's bands, as saved with the year freeze: letter, grade value, and points for 3 and 2 hours, with D+ and F included.
  - The class-of-pass GPA ranges, from the scheme's result bands.
  - The Note paragraph word for word.
  - **Corrected from the sample:** the sample's table placed D in Third and left out D+ (owner, 2026-11-27).
- **Printed text:**
  - The header word for word, the name in capitals, the grade as the level code ("2", "12"), the date of birth as "JAN 25, 2016" and the date of issue as "MARCH 26, 2027".
  - The subject labels are the curriculum's report labels (owner).
  - A student who failed gets a statement with RESULT FAIL and CLASS OF PASS "—" (owner).
  - The Registrar and Principal signature lines are blank: the seal and the signatures stay manual, so no names or dates are printed.
- **Examination centre:** a text rule on the Admin's Report Card Rules page, read-only for the Principal, flagged Q15. The rule values now hold up to 120 characters.
- **Seeder (`PastYearResultsSeeder`):** creates 2025-2026 (inactive) with four terms.
  - **Grade 8-A (KG-9 layout):** the students now in Grade 9-A.
  - **Grade 11-A (11-12 layout):** those now in Grade 12-A, with their option choices.
  - Their enrollments are Completed, every term has a full gradebook and attendance, and the Principal locks all four terms through `ResultFreezeService`, so the year freeze is real.
  - One Grade 11-A student has no option choices (as in this year's seed), so their statement is refused with the reason, which shows the refusal on staging.
  - The sections have no homeroom teacher and no teaching assignments, so this year's teacher and student pages don't change (see "Out of phase" below).
  - The Term 1 seeder now shares its gradebook and attendance code with it (`Database\Seeders\Concerns\SeedsTermResults`); Term 1's marks and attendance are unchanged (marks in a subject with no teaching assignment are now recorded as entered by the homeroom teacher).

**Migrations (4):**
1. Add "Statement of Marks" to `document_requests.type`.
2. Create `statements_of_marks`.
3. Add `grade_levels.statement_layout` (kg9 for KG1-9, g11_12 for 11-12, null for Grade 10).
4. Widen `report_rules.value` to 120 and add the `examination_centre` rule (Q15).

Each rollback is refused while any statement exists, so none is lost.

**Tests:** `StatementOfMarksTest` (11):
- Both rules-file worked examples: Grade 2 = 90, PASS, FIRST; Grade 12 GPA 3.04, PASS, SECOND, with "-" options, SUPW PASS and the generated table.
- A failed student.
- Each refusal.
- The issuance control and the draft preview.
- The issued copy never changing after a re-freeze, with the outdated notice.
- Draft dates and their audit.
- The Q15 rule.
- Access.

The phase 6 rules test now sends the new rule. The full suite passes (559).

**Checked in the browser (fresh seed, 1280 px and 390 px):**
- **Registrar:** every refusal with its reason, preparing both layouts, changing a draft's dates, the preview PDFs (A4 portrait, one page), Print disabled while the control is off, then issued and Re-print only.
- **Principal:** turned the control on in Setup & Controls; the examination centre rule shows read-only.
- **Admin:** the examination centre text rule.
- **Student, guardian and teacher pages:** nothing from last year shows.

**Deploy (staging), when asked:**
1. `php artisan migrate` (4 migrations).
2. An asset build.

To have statements to generate, a fresh reset with the seeder creates the frozen 2025-2026 year. On existing data, a year must have all four terms locked.

## Phase 8a: fee schedule (merged)

Reference data only: the ISMS never records payments.

**Built:**
- **`fee_schedules`** (one per school year, unpublished until the Treasurer publishes it) and **`fee_schedule_items`**: one row per grade level and fee type.
  - **Yearly fee:** one-time.
  - **Tuition:** the year's amount and its number of instalments.
  - **Refundable deposit:** one-time, new students only.
  - Amounts are DECIMAL in MMK, entered as whole kyats.
  - Rows are never deleted; a fee that isn't charged is 0.
  - Nursery has no grade level, so it has no fees, as on the sheet.
- **The 2026-2027 sheet (`App\Support\FeeSheet2026`):**
  - The data migration loads it unpublished when the 2026-2027 year exists (staging), and the Treasurer checks it and publishes.
  - On a fresh seed the year is created after the migrations, so the seeder loads it and publishes it, so the guardian card shows after a reset.
  - Rolling back the data migration removes only that schedule.
- **Instalments:** whole kyats (BigDecimal). Each is the year's tuition divided by the count, rounded down; the last takes the remainder (2,550,001 in 4 is 637,500 × 3 and 637,501). The 2026-2027 amounts divide exactly: 600,000, 637,500, 625,000 and 650,000 Ks.
- **Treasurer, Fee Schedule page:**
  - A year picker.
  - A summary grouped like the school's sheet ("Kindergarten 1 to Grade 6: 250,000 Ks").
  - A grid by grade with the per-instalment amounts.
  - Publish and Unpublish.
  - Saving:
    - Every changed cell is audited with its before and after values, in one transaction.
    - Once the schedule is published, a short reason is required and goes into the audit entry (owner, 2026-11-27). There is no approval step.
    - One "Confirmed by the school" tick per question (Q17, Q18) clears that question from every row and leaves the others.
- **"Start the schedule for <year>":**
  - Copies the latest earlier year's amounts, unpublished and with no question flags (Q17 and Q18 are about 2026-2027 only; owner, 2026-11-27).
  - With no earlier schedule, every grade starts at 0.
  - Audited.
- **Read-only copies:** the same page for the Principal, VP and Registrar (`<role>.fee-schedule.index`), with the flags and no save.
- **Guardian Fees page:** a "2026-2027 fees for Grade 9" card for the child's grade this year.
  - Published schedules only.
  - The yearly fee and the deposit as one-time lines, tuition "in 4 instalments of 650,000 Ks", and the sheet's two payment notes.
  - No question flags.
  - The card is a shared partial (`fee-schedule.partials.family-card`) for the 8b student Fees page.
- **For 8b:** `FeeScheduleService::forLevel(level, year)` gives the expected amounts by fee type, for the import check.

**Not in 8a:**
- **The import check** (an imported annual charge that doesn't match the schedule) is built in 8b on the Sun ledger's categorised lines. The current CSV import has free-text descriptions with no fee type. With no schedule for the ledger's year (2019-2020 and 2020-2021 have none), the check reports "no schedule for this year" and flags nothing.
- **The student Fees page** with the schedule card is in 8b (owner, 2026-11-27).

**Migrations (3):**
1. `fee_schedules`.
2. `fee_schedule_items`.
3. Load the 2026-2027 sheet.

They roll back and reapply cleanly on a fresh seed. Re-migrating with the year present loads the sheet unpublished, as on staging.

**Tests:** `FeeScheduleTest` (8):
- The sheet: every grade, instalments and flags; loaded once.
- Whole-kyat instalments with the remainder.
- Saves audited with before and after.
- Validation.
- The reason required on a published schedule.
- Confirming Q18 leaves Grade 9's Q17.
- A started year copied unpublished and unflagged.
- Families see only a published schedule, without flags.
- Read-only for the Principal, VP and Registrar; teachers and guardians refused.

The full suite passes (567).

**Checked in the browser (fresh seed, 1280 px and 390 px):**
- **Treasurer:** summary, grid, a save while unpublished showing a remainder, publish, a save refused without a reason, then saved with one.
- **Guardian:** the Grade 9 card.
- **Principal:** read-only.
- **VP and Registrar:** read-only, and the Treasurer's routes refused.

**Deploy (staging), when asked:**
1. `php artisan migrate` (3 migrations; the sheet loads unpublished).
2. An asset build.
3. The Treasurer checks the amounts and publishes.

## Phase 8b-1: Sun Accounts ledger import (merged)

The spec is `sdp_doc/YASIS_Sun_Finance_Import.md`, gitignored, so read it locally. Section 8 of it was updated locally for the `sun_accounts` table (owner, 2026-11-27).

**Built:**
- **New tables beside the CSV import, which is untouched (owner, 2026-11-27):**
  - `sun_import_batches`
  - `sun_accounts`: every code, its linked student (unique), `is_control`, `not_isms_student`, the name as printed
  - `sun_ledger_lines`: signed DECIMAL(14,2), §9 unique key
  - `sun_account_balances`
  - `sun_ledger_versions`: what each import created or changed
  - `fee_categories` and `fee_category_rules`
  - three settings
- **Nothing is hard-deleted.** A reverted import's lines are marked `removed_at`. `imported_fee_records` and `import_batches` aren't changed; once 8b-2 is in, no screen reads them, and dropping them can be a later clean-up PR.
- **Parser (`App\Services\Sun\AccountListingParser`):**
  - Follows the §2 patterns, reads Latin-1, skips page headers (including a page break between a line and its memo), and uses BigDecimal, with debit positive and credit negative.
  - Every §3 rejection refuses the whole file, and nothing is saved.
  - Errors carry line numbers and counts only, never names, amounts or memos.
- **Upload flow:**
  - Upload, then a summary page before anything is saved (counts, matched and unmatched, new codes, "Unallocated in Student Control", the §3 warnings), then Import or Cancel.
  - The upload waits on the private disk (`storage/app/private/fee-imports/pending`). Import or Cancel deletes it, and it expires after an hour (`sun:purge-pending`, scheduled hourly, and also run on every upload and page load).
  - Import parses the same file again, rather than keeping a parsed copy, and writes in one transaction. The file is kept at `fee-imports/<year>/<sha256>.prn`, downloadable only by the Treasurer and the Admin.
- **Dates (§4):** the month comes from the accounting period. Period 01 = April is a setting (T2). The typed date is kept, and `date_suspect` is set when it's before the start of the month before the period or after the end of the month after it.
- **Categories (§5):**
  - Rules are data (`fee_categories`, `fee_category_rules`), edited by the Treasurer on Fee Categories: keywords switched off, never deleted, plus a new keyword and "Re-apply to imported lines".
  - The billed month is read from memos such as "for Jan 2020".
  - **One change to §5's order:** the "any credit in a CR journal" rule is checked right after Brought forward, not at Payment's place. §5's own example "Payment monthly fee for Jan 2020" is a cash receipt that the literal order would call tuition (because of "monthly"). The payment keywords ("payment", "paid", "on a/c") stay last, so a charge mentioning "unpaid" isn't taken for a payment.
  - Per-line category and visibility overrides survive re-imports and re-applying the rules, and are audited.
- **Matching (§8):**
  - By code only; names only suggest candidates.
  - A student has at most one code, and a code stays with its student in later years (T3).
  - The control account (a setting, default 1000285005, T7) is never linked.
  - "Not an ISMS student" takes an account out of the unmatched list.
  - Unlinking is refused while the account has lines in a published import.
  - All audited.
- **Re-import (§10):**
  - An existing line (by unique key) is updated, keeping the overrides, and a changed amount is listed.
  - New lines are inserted.
  - Lines in the ISMS that the new file doesn't have are marked `missing_from_latest`, not deleted.
- **Revert:**
  - Restores exactly what an import created or changed.
  - Refused when a newer import has since changed any of the same lines or balances; the message names the newer import (owner, 2026-11-27).
  - A re-import after a revert brings the lines back.
- **Publishing:** per import, audited. Families see nothing until 8b-2.
- **Fee schedule check (§10 of the rules file):**
  - Per student and year, tuition, yearly fee and deposit charges (debits) are compared with the schedule for the student's grade that year. Flagged on Ledger Review, never refused.
  - Tuition matches the year's amount or a whole number of instalments, so a student who joined mid-year isn't flagged. No monthly or instalment pattern is assumed (T10).
  - A year with no schedule shows "No fee schedule for <year>" and flags nothing (owner).
- **Treasurer screens:**
  - Ledger Import (upload, summary, imports with publish, revert and download; cards on phones).
  - Ledger Review (accounts to match with suggestions, the schedule check, unclassified lines, suspect dates, lines missing from the latest file, amounts changed by the latest import, renamed accounts, opening balances that differ from last year).
  - Ledger Accounts and the account page (lines with category and visibility, link, unlink, not an ISMS student).
  - Fee Categories.
- **Privacy (§0):**
  - Files stay on the private disk.
  - The Admin's Data & Backup page lists and downloads them.
  - Nothing from a file reaches `laravel.log` (tested).
  - Erasing a student clears their ledger account name and line memos; amounts stay.

**Migrations (3):**
1. The ledger tables.
2. The category tables, seeded with §5 and T4.
3. The settings: T2, T7, and T5 for 8b-2.

They roll back and reapply cleanly on a fresh seed, and the old CSV tables are untouched.

**Tests:** `SunLedgerImportTest` (20 cases, synthetic fixtures from `Tests\Support\SunListingBuilder` with invented names), covering:
- Every line type across page breaks.
- Each of the seven §3 rejections, with nothing saved and the upload deleted.
- The summary, then import.
- Cancel and expiry.
- Categories, including the misspellings.
- Overrides surviving a re-import.
- Re-import: no duplicates, a changed amount, a missing line, a new line.
- Revert, including the newer-import refusal.
- The opening balance and rename warnings.
- Matching by code only; the control account never linked.
- The schedule check, with and without a schedule.
- Access: the Treasurer imports; the Treasurer and Admin download; everyone else refused.
- Nothing in the log.
- Retention.

The full suite passes (587).

**The real files, read-only, parsed locally (counts only, never imported or copied):**

| | 2019-2020 | 2020-2021 |
|---|---|---|
| Accounts | 887 | 1,021 |
| Lines | 14,702 | 7,270 |
| Failed checks | 0 | 0 |
| Closing balances | sum 0.00, equal to Report Balance | sum 0.00, equal to Report Balance |
| Suspect dates | 447 | 21 |
| Not allocated | 0 | 15 |
| Unclassified student lines | 18 | 31 |
| CR credits counted as payments | 4,754 of 4,754 | 2,367 of 2,367 |
| Parse time | 0.23 s | 0.16 s |
| Parse memory | 28 MB | 19 MB |

Between the two years: 887 accounts carried over, 134 new, 16 opening balances differ from the previous closing, and 9 accounts were renamed. All match the owner's figures and the Sun file.

**Import time:** a synthetic listing the size of the 2019-2020 file (887 accounts, about 15,900 lines) imports in about 4.5 s and re-imports in about 6 s on this PC. The whole process peaks at about 90 MB (the import itself adds about 66 MB), well inside a 30-second request limit. Staging's PHP memory limit hasn't been checked yet.

**Checked in the browser (fresh seed, 1280 px and 390 px), with a synthetic 2026-2027 listing using seeded invented names:**
- Upload, the summary with warnings, then import.
- Matching from a suggestion. Two seeded students share a name, which shows why names only suggest.
- Not an ISMS student.
- The schedule check flagging a 350,000 yearly fee against 300,000.
- A category set by hand, and a line hidden from families by hand.
- A new keyword.
- Publishing.
- The Admin's ledger files list.

**Deploy (staging), when asked:**
1. `php artisan migrate` (3 migrations).
2. An asset build.
3. A cron entry for `php artisan schedule:run` (pending uploads also expire on every upload, without it).
4. Back up `storage/app/private/fee-imports` with the database.

The upload limit must allow 10 MB.

## Phase 8b-2: family statements, reused codes, reports, retiring the CSV upload (merged)

Built on the Treasurer's answers (owner, 2026-11-28; the answers are in [YASIS_School_Answers.md](YASIS_School_Answers.md)). Section 6, 7 and 8 of the local Sun file were updated to match.

**What families see (T4, T5):**
- Families see every line, allowances included. The allowance category is no longer hidden by default, and there is no netting: the T5 setting is gone.
- The Treasurer can still hide a single line by hand. A family sees the year's hand-hidden lines as one "Adjustments" line, so the statement still adds up to the ledger (tested for every fixture account).
- The leadership switch on Visibility Rules still works for hand-hidden lines: with it on, leadership sees them marked "hidden from families"; with it off, the Adjustments line.
- **Statements are built when an import is published** (`sun_family_statements`, one per account, year and import). Families read the latest published, non-reverted import for each year. An unpublished import changes nothing for them; unpublishing or reverting the newer import falls back to the older one. Publishing an older import while a newer one exists for the same year is refused, naming the newer one.
- Hiding or showing a line, changing its category and linking or relinking an account update the saved statements in place.

**Reused codes (T3):**
- `sun_account_links` gives each link a year range (`from_fiscal_year`, `to_fiscal_year`). The database allows at most one open link per code and per student (stored generated columns with unique indexes). Lines are matched to the link covering their year.
- When a linked code is printed under a different name, its lines for that year are held: matched to nobody, shown to no family, listed as a "possible reused code" on Ledger Review and the Treasurer dashboard. Names are compared after trimming, collapsing spaces and ignoring case.
- The Treasurer either confirms **same student** (the new name is recorded as accepted, so a re-import of the same year doesn't ask again) or links the **new student**, which ends the old link at the previous year (or voids it if it started that year) and links the new student from this year. Both are audited.
- This replaces the 8b-1 "renamed accounts" warning. Unlinking now voids the link (kept, not deleted) and is still refused while published lines exist in its years.
- The 8b-1 links moved into `sun_account_links` from each account's first year; `sun_accounts.student_id` was dropped (restored on rollback).

**Student Control (T7):** labelled "Student Control balance" everywhere, since it carries charges as well as receipts. Still never matched and never shown to families. Fee Reports lists its balance at each import.

**Pages:**
- Guardian and student Fees pages: a statement per year (opening, lines by month, Adjustments, charged, paid and credited, closing with Owing, Settled or In credit), the fee schedule card, and a statement PDF. Opening the PDF is audited. The guardian dashboard tile reads the latest statement.
- Leadership Fee Records (Principal, VPs, Registrar) and the Principal dashboard's donut read the published statements, the same figures families see.
- The Treasurer's dashboard and Fee Reports read the live ledger, published or not: by grade, department, month and category, a CSV of students owing, and a family-copy PDF from the account page.
- The Treasurer's account page shows the links history and a held notice. Opening it is logged as "Viewed student financial record" (it replaces the retired Student Accounts page).
- **The CSV upload is retired.** Import Records, Validate & Match, Student Accounts and History are gone from the menu; their addresses redirect to the ledger pages; the CSV POST routes, the template and `FeeSummaryService` are removed. `imported_fee_records` and `import_batches` keep their rows; nothing reads them now, and dropping them can be a later clean-up PR.
- Fixed the `Data retention &amp; erasure` heading on the Admin's Data & Backup page (found in 8b-1).

**Erasure:** covers every code ever linked to the student: the printed name on the account, balances, reviews (previous and printed names) and links, the line memos, and the names and memos inside saved statements.

**Demo data:** `SunLedgerDemoSeeder` writes a synthetic 2026-2027 listing (invented codes 1000290010 onwards, Grade 9-A and 12-A, one unmatched code), imports it through the importer, links it and publishes it: 88 lines, 12 links, 13 statements. The CSV fee seeding is removed. The listing writer moved from the tests to `App\Support\Sun\ListingWriter`.

**Migrations (4):**
1. `sun_account_links` (moves the 8b-1 links, drops `sun_accounts.student_id`).
2. `sun_account_reviews`.
3. Allowances shown to families (T4) and the T5 setting removed.
4. `sun_family_statements`.

On a fresh seed they roll back and reapply cleanly; the rollback puts the links back in `sun_accounts.student_id`, hides allowances again and restores the T5 setting.

**Tests:**
- `SunAccountLinksTest` (6): held from every family with only the Treasurer seeing it; same student recorded so a re-import doesn't ask again; spacing and case don't open a review; a different student ends the old link; one open link per code and per student enforced by the database; unlinking voids the link.
- `SunFamilyStatementsTest` (6): totals equal the ledger; allowances shown and a hand-hidden line only as Adjustments; the leadership switch; unpublished, unpublish and revert; an older import refused; the student page, PDF and guardian tile; leadership pages and the Principal dashboard.
- `SunFeePagesTest` (4): Treasurer dashboard, reports and the owing CSV; the old CSV addresses redirecting; Visibility Rules and the backup heading; the listing writer round trip through the parser.
- `SunLedgerImportTest` (20) updated, including erasure of review names.
- Five tests of the retired CSV pages removed. The full suite passes (561).

**Checked in the browser (fresh seed, 1280 px and 390 px):**
- Guardian: the statement with the Adjustments line, a child with no records, the PDF.
- Student: Fees and the PDF.
- Principal, VP and Registrar: Fee Records and a student's statement; the Principal's donut.
- Treasurer: dashboard and Fee Reports; a synthetic 2027-2028 listing with one renamed code. Its lines were held after publishing (neither of the guardian's children saw them), then linked to a different student: the old link ended at 2026-2027, the 2027-2028 statement moved to the new student, and the audit row was written.

**The real files:** not run again. Under these rules the 9 accounts found renamed between the two years in 8b-1 would each open a possible reused code review.

**Deploy (staging), when asked:** `php artisan migrate` (4 migrations) and an asset build. The demo ledger comes only with a fresh seed.

## Phase 9a: the school's answers, curriculum and results (done, in review)

Built on the school's answers (owner, 2026-11-28; `YASIS_School_Answers.md`, "Answers that change built code"). The rules file is updated to match: every answered question is marked, sections 2, 3, 4.2, 5, 6, 7 and 9 follow the answers, and the open list is follow-up questions FQ1-FQ9 plus Q19-Q21 (Q2 and Q10 are also still open).

**Owner's decisions (2026-11-28):**
1. The Final column's result rows hold the year result as already built (from the subjects' year marks), so the card, the statement and the recommendation agree.
2. The year's SUPW word is the last term's. Incomplete can become Complete on a locked term, in that direction only: the SUPW teacher enters it, it is audited, and the frozen word and the issued statements change in place. Nothing else is recalculated; the recommendation changes only if the FQ3 rule is on.
3. Grade 10 keeps the card's WORLD HISTORY and WORLD GEOGRAPHY, and its printed row label "G.P.A" with the average in it.
4. Grade 10's point total and class aren't held back for everyone while Q19 is open (see below).
5. KG-4's locked Term 1 keeps excluding Character Building while the later terms include it: accepted, since there is no real school data yet and the Principal can recalculate a locked term with the current rules.

**Built:**
- **Curriculum (Q4-Q7):**
  - KG-4 counts Character Building, not Social Study (seven subjects).
  - SUPW, Grades 5-12, uses the new `completion` grading mode: no mark, in no average or GPA. Grades 5-9 average nine subjects.
  - Grade 10 counts Moral, Myanmar, English, Mathematics and one track of three: Science (Biology, Chemistry, Physics) or Arts (World History, World Geography, Music). Computer isn't counted, flagged **FQ4**. PE and SUPW are extras.
  - Grades 11-12 have one track per student: Science (Biology, Chemistry, Physics) or Arts (World History, World Geography, Accounting).
  - `subject_offerings.track` replaces the elective slot, and the Admin's Curriculum page edits it. A level's tracks are the tracks on its rows.
- **Tracks (`student_tracks`):**
  - One per student per year, set by the Registrar on **Tracks** (replaces Electives; `/registrar/electives` redirects). Grade 10 uses the same record.
  - Each change is audited. A track can be changed but not cleared.
  - No track means an incomplete result, with the reason. The other track's subjects print "-".
- **SUPW words (`subject_statuses`):**
  - The SUPW teacher's gradebook for SUPW shows one Pass / Incomplete / Complete per student, with no categories; the template is never applied to it.
  - On a locked term only Incomplete → Complete is accepted. The term's frozen word changes in place; for the last term, so do the year's frozen word and the issued Statements of Marks.
  - Audited as "Recorded SUPW words" or "Completed SUPW after the term was locked".
- **Results:**
  - Ties are dense: 1, 2, 2, 3 (`grading_schemes.tie_ranking`; freezes made before read as the old 1, 2, 2, 4).
  - Grade 10 ranks by the average of its seven counted subjects (`rank_basis`), stored as `student_results.average_value`.
- **Q19 no longer blocks Grade 10.** The point total is shown for everyone. The class is decided unless a counted subject or the total falls in a band flagged Q19 (a subject below 40, or 35-39 points); then it shows "class pending school answer (Q19)" (`student_results.class_pending`, `ResultService::CLASS_QUESTIONS`). Q6 stays a blocking question in the code for frozen results that waited on it.
- **Frozen results keep their curriculum:** each freeze stores the curriculum it used (`result_freezes.curriculum`; existing freezes got theirs from their frozen subject rows). A re-freeze after a grade change, or a relock with the previous rules, uses it. Only "Recalculate with the current rules" picks up a changed curriculum. Before 9a, both used the live curriculum.
- **Report cards:**
  - Final is the whole year: the subjects' year marks, the year's G.P.A, position and class size, and Days absent as the four terms added up. It's provisional until the year is frozen.
  - The Grade 10 G.P.A row holds its average, under the label "G.P.A".
  - SUPW prints its word.
- **Personality traits:** rated once a year by the homeroom teacher, on the last term, and printed on the final report only (`report_rules.personality_shown` = `final_report`, Q14 cleared). Earlier terms show where to rate, and their cards leave the block blank. Ratings already given for earlier terms are kept, not printed.
- **Statement of Marks:** KG-4 lists Character Building. Grades 5-9 list SUPW's word after the counted subjects. Grades 11-12 split compulsory and optional by track. Q15 is cleared.
- **Promotion:** "SUPW Incomplete" is shown next to the recommendation. The new report rule `supw_incomplete_blocks_promotion` (default no, flagged **FQ3**) makes it Retain when set.
- **Seeders:** tracks instead of elective choices; SUPW words (about one in seven Incomplete) instead of marks; last year's Term 4 personality ratings; no Term 1 ratings this year.

**Migrations (7):**
1. `subject_offerings.track`, the `completion` grading mode, `student_subject_results.track`.
2. `student_tracks`.
3. The curriculum answers, moving one-track elective choices into tracks (mixed ones get none) and clearing Q4-Q7. Rollback restores the seeded rows.
4. `subject_statuses`.
5. `grading_schemes.tie_ranking` and `rank_basis`, and `student_results.average_value` and `class_pending`.
6. `result_freezes.curriculum`, filled for existing freezes.
7. The report rules: the FQ3 rule, `personality_shown` = `final_report`, and the Q14 and Q15 flags cleared.

Rolling back 2-4 is refused once the Registrar has set a track or a SUPW word has been entered. On a fresh seed the refusal works; with the seeded words and tracks cleared, all seven roll back and reapply.

**Tests:**
- `SchoolAnswersResultsTest` (6):
  - SUPW words and access.
  - Incomplete → Complete after the year is locked, with the frozen words, the issued statement and the card.
  - The FQ3 rule.
  - A re-freeze keeping the frozen curriculum.
  - The migration moving elective choices into tracks.
  - The Grade 10 card.
- `StudentTrackTest` (7) replaces `StudentElectiveTest`.
- `ResultServiceTest`:
  - The six-subject KG worked example kept as an arithmetic test, and a seven-subject example (Character Building 83 → 89).
  - Arts and science tracks.
  - SUPW words.
  - Grade 10 with seven subjects, and the Q19 pending cases.
  - Dense ties.
- Also updated: `CurriculumTest`, `ReportCardTest` (the Final column as the year), `FamilyResultsTest`, `StatementOfMarksTest`, `ResultFreezeTest`, `PromotionRecommendationTest`.
- The full suite passes (567).
- **Test-run fix (out of phase, tests only):** `SunLedgerImporter` (8b-1) calls `set_time_limit(120)` for web requests. In a test run that limit stayed on for the rest of the PHPUnit process, and one full run died with "Maximum execution time of 120 seconds exceeded" once the remaining tests took longer than that. `tests/TestCase.php` now resets the limit before each test; the app code is unchanged.

**Checked in the browser (fresh seed, 1280 px and 390 px):**
- **SUPW teacher (Grade 12-A, locked Term 1):** Pass rows read-only, Incomplete → Complete saved, and the frozen card then showed COMPLETE.
- **Homeroom teacher:** Term 3 shows "rated once a year, on Term 4"; ratings saved on Term 4 and printed on the Term 4 card.
- **Registrar:**
  - Tracks list and section page; set a missing track.
  - Read-only cards.
  - A Statement of Marks prepared for last year's Grade 8-A: nine scores, then SUPW's word.
- **Admin:** Curriculum for Grade 10 (tracks, FQ4) and Grade 11; the Report Card Rules options.
- **Principal and VP:** Grade 10-A cards read-only, with averages in the G.P.A row and dense positions.
- **Student and guardian:** last year's final card (year marks in Final, SUPW's word, days absent summed, personality printed); the SUPW word on the grade page.
- **Screenshots:** they stopped working partway through (Chrome's capture timed out). The Admin rules page, the Principal, VP, student and guardian were checked by reading the rendered pages and measuring the 390 px frame, not visually.

**Not in 9a:**
- The Grades 11-12 gradebook template and the attendance rules (9b).
- The fee documents (instalment amounts, KG1 tuition).
- The teacher-scope fix.

**Deploy (staging), when asked:** `php artisan migrate` (7 migrations) and an asset build. Existing elective choices become tracks where they are all one track, and the Registrar sets the rest on Tracks. A fresh reset with the seeder is the alternative.

## Defaults applied so far

| Rule | Default | Question (rules file) | Where it lives |
|---|---|---|---|
| Levels | KG1, KG2, 1 to 12. Nursery has no level and no curriculum. | Q2 | `grade_levels` |
| KG-4 counting | Character Building counts, Social Study doesn't (seven subjects). | Q4 (answered, 9a) | `subject_offerings.counts_in_result` |
| SUPW | No mark, Grades 5-12: Pass, Incomplete or Complete; in no average or GPA. The year's word is the last term's. | Q5 (answered, 9a) | `grading_mode` = `completion`, `subject_statuses` |
| SUPW Incomplete and promotion | Shown beside the recommendation; doesn't change it. | FQ3 | `report_rules.supw_incomplete_blocks_promotion` = no, flagged FQ3 |
| Grade 10 counted subjects | Moral, Myanmar, English, Mathematics and one track of three. | Q6 (answered, 9a) | `subject_offerings.track`, `student_tracks` |
| Grade 10 Computer | Not counted. | FQ4 | Grade 10 COMP row flagged FQ4 |
| Grade 11-12 options | One whole track per student: Science (Biology, Chemistry, Physics) or Arts (World History, World Geography, Accounting). | Q7 (answered, 9a) | `subject_offerings.track`, `student_tracks` |
| Grade 11-12 report order | Compulsory subjects, then Biology, Chemistry, Physics, World History, World Geography, Accounting, then SUPW. | not asked | `sort_order` |
| Report labels | The card's capitals, for example `MORAL/VALUES EDU`. | not asked | `report_label` |
| Rounding | Marks, averages and point totals are rounded to whole numbers, half up, before the band lookup (60.5 is Second). The GPA is rounded to two decimals, half up. | Q10 | `grading_schemes` rounding columns, flagged Q10 |
| Grade 10 below 40 | The bands stay (Fail 0 points; below 40 points Fail), flagged Q19. Since 9a a student in a flagged band gets the point total with "class pending school answer (Q19)"; everyone else gets the total and the class. | Q19 (new, not sent) | Grade 10 Fail bands flagged Q19, `ResultService::CLASS_QUESTIONS` |
| Grade 11-12 failed subject | A failed compulsory subject fails the year, whatever the GPA. | not asked (rules file section 6) | `subject_fail_rule` = `any_compulsory` |
| Year mark | A subject's year mark is the simple average of its four rounded term marks, and there is none until every term has a mark. | Q3 (answered) | `ResultService::liveSectionYear()` |
| Building the result | The average, point total and GPA use the rounded subject marks, the figures printed on the card. | Q10 | `ResultService::combine()` |
| "G.P.A" row | The average for KG1-10 (Grade 10: of its seven counted subjects) and the GPA for 11-12. The family pages still label Grade 10's result "Point total". | Q12 (answered, 9a) | `StudentResultData::average` |
| Position | Ranked by the printed figure (Grade 10 by its average). Ties share a position and the next follows on (1, 2, 2, 3). Only complete results are ranked. Freezes made before 9a keep 1, 2, 2, 4. | Q13 (answered, 9a) | `grading_schemes.tie_ranking`, `rank_basis` |
| SUPW (11-12) | No Fail; it counts in nothing. | Q5 (answered) | `completion` grading mode |
| Grade 10 result | A point total, position and (outside the Q19 cases) class for every student with a track. | Q6 (answered), Q19 | `ResultService` |
| Pass/Fail subjects | PASS when the rounded mark reaches the scheme's pass mark (the lowest band that is not a fail band). | not asked | `ResultService::subject()` |
| Grade 10 promotion | No automatic recommendation; the DHSC result decides. | Q11 | `ResultService::promotionRecommendations()` |
| Failed students' position | Ranked together with those who passed (a Grade 12 student who failed Mathematics can be 2nd). The Admin can switch a scheme to rank failed students after all who passed. | Q20 (new) | `grading_schemes.failed_ranking`, flagged Q20 |
| Attendance component | Present and Tardy count as attended, Absent counts against, Excused days are left out of both attended and counted days. Only the days the student has their own record count (missing days are flagged to the teacher). | Q8 | `gradebook_template_categories` (attendance row flagged Q8), `AttendanceScoreService` |
| Where the fixed template applies | KG1 to Grade 10. Grades 11 and 12 keep free category weighting until 9b (the school says every grade). | Q9 (answered, 9b builds it) | `grading_schemes.uses_gradebook_template`, G10 and G11_12 flagged Q9 |
| School days in the term | The dates with an attendance record for the section in that term. | Q8 | `AttendanceScoreService` |
| Empty categories | Left out of the weighting while the term is open. At lock, an empty Examinations category means no final mark for that subject (incomplete). | not asked (owner, 2026-11-27) | `ResultService::liveSectionTerm(final: true)` |
| Report card "Final" column | The whole year: year marks, the year's G.P.A, position and class size, days absent of the four terms. Since 9a; it reverses phase 6's "Term 4's own result". | Q3 (answered, 9a) | `ReportCardService::build()` |
| Report card "G.P.A" row | "G.P.A (AVERAGE %)" for KG1-9; "G.P.A" with the average for Grade 10 (the card's printed label, owner 2026-11-28); "G.P.A" for 11-12. | Q12 (answered, 9a) | `ReportCardService::build()` |
| Report card position | The ranked position from the results (1, 2, 2, 3). | Q13 (answered, 9a) | `ResultService::rank()` |
| Days absent | Absent plus Excused days in the term. The Admin can switch it to Absent only. | Q8 | `report_rules.days_absent_counts`, flagged Q8 |
| Personality record | Rated once a year on the last term, printed on the final report only. The rule can still be set to the latest rated term or the report's own term. | Q14 (answered, 9a) | `report_rules.personality_shown` = `final_report` |
| Minimum attendance | 80% to sit the examinations, checked on the section's daily attendance (attended over counted days, Excused left out). Flagged, never blocked. | Q16 | `report_rules.min_attendance_pct`, flagged Q16 |
| Examination centre | "YANGON ADVENTIST SEMINARY INTERNATIONAL SCHOOL" on every statement; the Admin can change it. | Q15 (answered, 9a) | `report_rules.examination_centre` |
| Grade 10 statement | None: MYUM's Education Department issues it. | Q11 (answered) | `grade_levels.statement_layout` null for Grade 10 |
| SUPW on the statement | Its word (PASS, INCOMPLETE, COMPLETE): after the counted subjects for Grades 5-9, among the compulsory subjects with HRS "-" for 11-12. | Q5 (answered, 9a) | `StatementOfMarksService::content()` |
| PE on the Grade 5-9 statement | Listed, because PE counts in the average; the sample leaves it out. | Q21 (new) | KG-9 lists every counted subject |
| No statement | An incomplete or blocked year result gets no statement, with the reason. | not asked | `StatementOfMarksService::source()` |
| Failed students | Issued with RESULT FAIL and CLASS OF PASS "—". | not asked (owner, 2026-11-27) | `StatementOfMarksService::content()` |
| 11-12 conversion table | Generated from the scheme's bands, D+ included, with the class-of-pass GPA ranges under it. The sample's table placed D in Third and left out D+. | not asked (owner, 2026-11-27) | `StatementOfMarksService::conversion()`, `classes()` |
| Grade 9 tuition | 2,600,000 Ks, as printed on the fee sheet (grouped with Grades 11-12, not 5-8). | Q17 | `fee_schedule_items.pending_question` on Grade 9 tuition |
| Instalments | Tuition in four equal instalments (whole kyats, the last takes any remainder); the yearly fee and the refundable deposit as separate one-time lines. | Q18 | `fee_schedule_items.instalments`, every 2026-2027 row flagged Q18 |
| Sun period 01 | April (period 12 is March; the year is the one the financial year ends in). Confirmed by the Treasurer. | T2 (confirmed) | setting `fees.sun_period_one_month` = 4 |
| Account codes | The Treasurer says a code can go to a different student (8b-1 assumed it never would). Links have a year range; a linked code printed under a different name is held until the Treasurer confirms the same student or links the new one. A student has at most one open code. | T3 (answered; follow-up 9 open) | `sun_account_links`, `sun_account_reviews` |
| Hidden from families | Nothing by default: families see every line, allowances included. The Treasurer can hide a single line by hand; families see those as "Adjustments". | T4 (answered; follow-up 7 open) | `fee_categories.restricted_by_default`, flagged T4 |
| Hidden allowances | No netting: allowances are shown as they are. | T5 (answered with T4) | the T5 setting was removed in 8b-2 |
| Credit balances | Shown as "In credit" (Owing above 0, Settled at 0, In credit below 0): payment in advance or overpayment. Confirmed by the Treasurer. | T6 (confirmed) | `SunAccountBalance::status()` |
| Student Control | Account 1000285005. It carries charges as well as receipts, so it is labelled "Student Control balance". Never matched, never shown to families. | T7 (answered) | setting `fees.sun_control_account` |
| Charging pattern | Four instalments, as on the fee sheet (confirmed). The import still reads what the ledger has, and the schedule check compares annual totals. | T10 (confirmed) | `SunScheduleCheck` |

## Open questions waiting on the school
The school's answers are in [YASIS_School_Answers.md](YASIS_School_Answers.md). A KG1/KG2 decision has been recorded under Q2 while the school's answer is pending.

**The Treasurer's answers (T1 to T11), applied in 8b-2:**
- Confirmed: T1 (same report on demand), T2 (period 01 is April), T6 (credit balance), T10 (four instalments).
- Changed the build: T3 (codes can be reused), T4 and T5 (families see every transaction, no netting), T7 (Student Control carries charges too).
- Noted, no change: T8 (adjustments after printing are possible, so the opening balance warning stays), T9 (the ferry fee is still charged monthly; the transport category stays), T11 (not sure about the star; it is stored, with no special handling).

**The academic answers (Q1, Q3-Q9, Q11-Q17):** applied in 9a, except the attendance answers (Q8, Q16) and the Grades 11-12 template (Q9), which are 9b, and Q17 and the instalments (Q18), which are the fee PR. Q10 (rounding) wasn't answered. The rules file's section 12 marks each one.

**Follow-up questions for the VP still open (numbered FQ1-FQ9 in the data):**
- FQ1. Does an excused absence count as an absence for the 10% mark and the 80% rule? (9b default.)
- FQ2. Does each subject teacher take attendance every lesson today? Would daily attendance be acceptable for the 10% subject attendance mark? (9b default.)
- FQ3. Does SUPW "Incomplete" stop a student being promoted? (Default no: `report_rules.supw_incomplete_blocks_promotion`, flagged FQ3.)
- FQ4. Does Grade 10 Computer count? (Default no: the Grade 10 COMP curriculum row, flagged FQ4.)
- FQ5. Is Pre KG the same as KG1, and is KG1 tuition 1,600,000 or 2,400,000? (Fee PR.)
- FQ6. What are the four 2026-2027 instalment amounts for each grade? (Fee PR.)
- FQ7. Is the earlier rule that SDA discounts aren't shown to families now withdrawn? (8b-2 follows the Treasurer: families see every line.)
- FQ8. Should ISMS hold back report cards when fees are owing, or only warn the Registrar? (Not built.)
- FQ9. Can a student's account code be given to a new student after they leave? (8b-2 handles either answer.)

**Not sent yet:** Q19, Q20, Q21. **Still open:** Q2 (never asked) and Q10.

### Question numbers: letter to the school vs rules file
The letter sent to the school left out Q2 (Nursery), so from Q2 onwards each letter number is one less than the rules file's number. **The rules file's numbers are the ones used in the code, the data (`pending_question`) and this file.**

| Letter | Rules file |
|---|---|
| Q1 | Q1 |
| — (not asked) | Q2 |
| Q2 | Q3 |
| Q3 | Q4 |
| Q4 | Q5 |
| Q5 | Q6 |
| Q6 | Q7 |
| Q7 | Q8 |
| Q8 | Q9 |
| Q9 | Q10 |
| Q10 | Q11 |
| Q11 | Q12 |
| Q12 | Q13 |
| Q13 | Q14 |
| Q14 | Q15 |
| Q15 | Q16 |
| Q16 | Q17 |
| Q17 | Q18 |
| — (not sent yet) | Q19 (added 2026-11-26) |
| — (not sent yet) | Q20 (added 2026-11-26) |
| — (not sent yet) | Q21 (added 2026-11-27) |
| T1 to T11 | T1 to T11 (same numbers) |

## Notes for the next session
- Later phases look rules up by `grade_levels.code` and `subjects.code`, never by id or name. Fee ranges such as "KG to Grade 6" are ranges of `sort_order`, so they cover KG1 and KG2. Grading is not a range: each level points at its scheme (`grade_levels.grading_scheme_id`).
- **Curriculum as an input to later phases:**
  - Only active offerings (`archived_at` null) are counted.
  - `counts_in_result` decides what goes into an average, point total or GPA.
  - `credit_hours` is the GPA weight.
  - A row with `pending_question` set is an unconfirmed default.
  - For Grades 11-12, a student's counted subjects are the compulsory rows plus the row they chose in each slot (`student_electives`).
- **Testing migrations:**
  - Use a throwaway database: run a fresh `migrate --seed`, and test rolling back on it.
  - **Don't copy staging's database to this PC without asking the owner first** (owner's rule, 2026-11-25). If a staging copy seems necessary, explain why and wait for an answer.
- **Phase 3's requirements for phase 4, done in 4a:**
  - Each term is calculated with the rules in force when it was locked: the freeze copies every scheme's settings and both band sets, so neither band edits nor unversioned setting edits reach a frozen term. `GradingSchemeBand::inForceAt()` is not needed for this.
  - Grade 10 is refused while Q6 (or Q19) is flagged.
  - SUPW's Q5 default is applied explicitly.
  - Rounding uses the scheme's mode and decimals, with no floats.
- **The old department grade scale is retired (4b):** everything that read it now reads `ResultService`, and its hard-deleting Admin page is gone. Only the `grade_scale_bands` table, its rows, the `GradeScaleBand` model, `Department::gradeScaleBands()` and its `AuditLog` category remain, for past records.
- **Out of phase:** the Principal and VP dashboards average raw `Grade` scores without any scale; they read neither the old nor the new results.
- **Fixed in phase 8b-2 (found in 8b-1):** the Admin's Data & Backup page showed the heading `Data retention &amp; erasure` with a literal `&amp;`.
- **Treasurer questions:** all of T1 to T11 are answered (see Open questions). Follow-up questions FQ7 and FQ9 are still open.
- **KG-4's locked Term 1 (2026-2027) was frozen before Character Building counted** (accepted, owner 2026-11-28): its First column averages six subjects, the later terms and the Final seven. There is no real school data yet, and the Principal can recalculate a locked term with the current rules (Setup & Controls, with a reason).
- **Old SUPW marks:** SUPW items and marks entered before 9a stay in the gradebook tables but aren't read. Frozen results keep the SUPW score or PASS/FAIL they were frozen with.
- **A late SUPW Complete** can only be entered for the active school year's terms (the gradebook lists those). A Complete for an earlier year would need a manual correction.
- **Old CSV fee tables:** `imported_fee_records` and `import_batches` keep their rows, but nothing reads them since 8b-2. Dropping them can be a later clean-up PR, after a backup.
- **Out of phase (found in phase 7):** the teacher's Dashboard, My Classes and Attendance pages list the teacher's homeroom sections of every school year (`StaffProfile::homeroomSections` has no year filter), so after a year rollover last year's class shows beside this year's. Not fixed; the past-year seeder leaves its sections without a homeroom teacher so the demo isn't affected.
- **Phase 6 and the report card:** the year result is not on the report card (Q3, option B). Phase 7's Statement of Marks is where `ResultService::sectionYear()` is printed.
- **Fixed after phase 5 (PR #47):** removing a gradebook item or a teacher's own category (Grades 11-12) now archives it (`archived_at`) instead of deleting its grades. Archived items and categories count in no mark, keep their grades, and can be restored by the teacher while the term is open; both actions are audited. Locked terms stay read-only. Template categories still can't be archived.
