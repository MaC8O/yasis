# Academic and fee rules: progress

Source of truth: [YASIS_Academic_and_Fee_Rules.md](YASIS_Academic_and_Fee_Rules.md). The build order is in its section 11: one branch and one PR per phase. Each phase needs the owner's approval of its plan and migrations before any code is written, and the owner's go-ahead before the next phase starts. Nothing is merged, pushed to yasis01 or deployed by Claude.

| Phase | Status | Branch / PR |
|---|---|---|
| 1. Grade levels on sections | Merged | `grade-levels`, PR #40 |
| 2. Curriculum (`subject_offerings`, Grade 11-12 electives) | Merged | `curriculum`, PR #41 |
| 3. Grading schemes per grade level | Merged | `grading-schemes`, PR #42 |
| 4a. Calculation service, frozen results, promotion recommendation | Merged (with the follow-up: a batch needs a frozen year) | `results-service`, PR #43; `promotion-needs-frozen-year`, PR #44 |
| 4b. Switch the pages to the new results, retire the department grade scale, Q20 ranking setting | Merged, deployed to staging (fresh reset 2026-11-27) | `results-pages`, PR #45 |
| 5. KG1 to Grade 10 gradebook template and calculated attendance | PR open, awaiting review | `gradebook-template`, PR #46 |
| 6. Report cards | Not started | |
| 7. Statement of Marks | Not started | |
| 8. Fee schedule (reference data only) | Not started | |

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

## Phase 5: fixed gradebook template and calculated attendance (done, in review)

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

## Defaults applied so far

| Rule | Default | Question (rules file) | Where it lives |
|---|---|---|---|
| Levels | KG1, KG2, 1 to 12. Nursery has no level and no curriculum. | Q2 | `grade_levels` |
| KG-4 counting | Character Building and Social Study appear on the KG-4 card but are not counted in the accumulative score. This applies from KG1 to Grade 4. | Q4 | `subject_offerings.counts_in_result`, flagged Q4 |
| SUPW | Scored out of 100 and counted for Grades 5-9. Scored for Grade 10, whose counting is covered by the next row. Pass/Fail and not counted (not in the GPA) for Grades 11-12. | Q5 | `grading_mode`, `counts_in_result`, flagged Q5 |
| Grade 10 counted subjects | None counted until the school confirms which seven count. | Q6 | `counts_in_result` = false, all 13 rows flagged Q6 |
| Grade 11-12 options | Each slot is chosen separately, so mixing (for example Biology with Accounting) is allowed. Slot A: Biology / World History. Slot B: Chemistry / World Geography. Slot C: Physics / Accounting. | Q7 | `elective_slot`, `student_electives`, flagged Q7 |
| Grade 11-12 report order | Compulsory subjects, then Biology, Chemistry, Physics, World History, World Geography, Accounting, then SUPW. | not asked | `sort_order` |
| Report labels | The card's capitals, for example `MORAL/VALUES EDU`. | not asked | `report_label` |
| Rounding | Marks, averages and point totals are rounded to whole numbers, half up, before the band lookup (60.5 is Second). The GPA is rounded to two decimals, half up. | Q10 | `grading_schemes` rounding columns, flagged Q10 |
| Grade 10 below 40 | A subject below 40 is Fail with 0 points; a point total below 40 is Fail; one failed counted subject fails the year. | Q19 (new) | Grade 10 Fail bands and `subject_fail_rule`, flagged Q19 |
| Grade 11-12 failed subject | A failed compulsory subject fails the year, whatever the GPA. | not asked (rules file section 6) | `subject_fail_rule` = `any_compulsory` |
| Year mark | A subject's year mark is the simple average of its four rounded term marks, and there is none until every term has a mark. | Q3 | `ResultService::liveSectionYear()` |
| Building the result | The average, point total and GPA use the rounded subject marks, the figures printed on the card. | Q10 | `ResultService::combine()` |
| "G.P.A" row | It shows the average for KG1-9, the point total for Grade 10 and the GPA for 11-12, labelled that way on every page (from 4b). | Q12 | `StudentResultData::resultLabel` |
| Position | Ranked by the printed figure. Ties share a position and the next is skipped (1, 2, 2, 4). Only complete results are ranked. | Q13 | `ResultService::rank()` |
| SUPW (11-12) | Failing SUPW does not fail the year, because it is not counted. | Q5 | `any_compulsory` applies to counted compulsory subjects |
| Grade 10 result | No point total, class or position while any Grade 10 curriculum row is flagged Q6 or the scheme or its bands are flagged Q19. The page says why. | Q6, Q19 | `ResultService::BLOCKING_QUESTIONS` |
| Pass/Fail subjects | PASS when the rounded mark reaches the scheme's pass mark (the lowest band that is not a fail band). | not asked | `ResultService::subject()` |
| Grade 10 promotion | No automatic recommendation; the DHSC result decides. | Q11 | `ResultService::promotionRecommendations()` |
| Failed students' position | Ranked together with those who passed (a Grade 12 student who failed Mathematics can be 2nd). The Admin can switch a scheme to rank failed students after all who passed. | Q20 (new) | `grading_schemes.failed_ranking`, flagged Q20 |
| Attendance component | Present and Tardy count as attended, Absent counts against, Excused days are left out of both attended and counted days. Only the days the student has their own record count (missing days are flagged to the teacher). | Q8 | `gradebook_template_categories` (attendance row flagged Q8), `AttendanceScoreService` |
| Where the fixed template applies | KG1 to Grade 10. Grades 11 and 12 keep free category weighting. | Q9 | `grading_schemes.uses_gradebook_template`, G10 and G11_12 flagged Q9 |
| School days in the term | The dates with an attendance record for the section in that term. | Q8 | `AttendanceScoreService` |
| Empty categories | Left out of the weighting while the term is open. At lock, an empty Examinations category means no final mark for that subject (incomplete). | not asked (owner, 2026-11-27) | `ResultService::liveSectionTerm(final: true)` |

## Open questions waiting on the school
All of Q1 to Q20 in the rules file are still open, as are the Treasurer questions T1 to T11. A KG1/KG2 decision has been recorded under Q2 while the school's answer is pending.

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
- **Fixed after phase 5 (PR #47):** removing a gradebook item or a teacher's own category (Grades 11-12) now archives it (`archived_at`) instead of deleting its grades. Archived items and categories count in no mark, keep their grades, and can be restored by the teacher while the term is open; both actions are audited. Locked terms stay read-only. Template categories still can't be archived.
