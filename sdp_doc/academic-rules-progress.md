# Academic and fee rules: progress

Source of truth: [YASIS_Academic_and_Fee_Rules.md](YASIS_Academic_and_Fee_Rules.md). The build order is in its section 11: one branch and one PR per phase. Each phase needs the owner's approval of its plan and migrations before any code is written, and the owner's go-ahead before the next phase starts. Nothing is merged, pushed to yasis01 or deployed by Claude.

| Phase | Status | Branch / PR |
|---|---|---|
| 1. Grade levels on sections | Merged | `grade-levels`, PR #40 |
| 2. Curriculum (`subject_offerings`, Grade 11-12 electives) | Merged | `curriculum`, PR #41 |
| 3. Grading schemes per grade level | PR open, awaiting review | `grading-schemes`, PR #42 |
| 4. Calculation service (tests first) | Not started | |
| 5. KG-9 gradebook template | Not started | |
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

## Phase 3: grading schemes (done, in review)

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

## Open questions waiting on the school
All of Q1 to Q19 in the rules file are still open, as are the Treasurer questions T1 to T11. A KG1/KG2 decision has been recorded under Q2 while the school's answer is pending.

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
- **Phase 4 requirements from phase 3 (owner, 2026-11-26):**
  - **A term's results must be calculated with the band set that was in force when the term was locked**, so editing bands later doesn't change past report cards. Use `GradingSchemeBand::inForceAt($lockedAt)`; the archived sets keep `created_at` and `archived_at` for this. Phase 4 needs to find (or start recording) when each term was locked.
  - **The scheme's own settings are not versioned:** `rounding_mode`, `mark_decimals`, `result_decimals`, `result_max` and `subject_fail_rule` are edited in place, with before and after only in the audit log. A change after a term was locked would change that term's results. Phase 4 has to decide how to handle this, for example by snapshotting the settings with each locked term.
  - Grade 10: still refuse a point total while any Grade 10 curriculum row is flagged Q6 (phase 2 decision).
  - `any_compulsory` for 11-12: SUPW is compulsory but pass/fail and not counted. Whether failing it fails the year is Q5; phase 4 has to apply that default explicitly.
  - Round with the scheme's `rounding_mode` and decimals before each band lookup, never with floats.
- **What reads the old department grade scale (`grade_scale_bands`), for phase 4 to switch over and retire:**
  1. `GradeService::bandFor()` via `computeStudentResult()` (the `letter` and `gpa` of a subject result). It is used by the teacher gradebook (`TeacherGradebookController`), the student dashboard, grades page and report-card PDF, and the guardian dashboard, grades page and report-card PDF. The "Term GPA" and "Latest GPA" tiles average the bands' `gpa_point`.
  2. `TeacherGradebookController` sends `bandsByDept` to the gradebook's JavaScript (`teacher/gradebook/index.blade.php`), which shows the letter live as scores are typed.
  3. The Admin page `GradeScaleController` (4 routes, a nav item and a dashboard tile).
  4. Seeders: `DatabaseSeeder` and `K12StructureSeeder`; the test helper `SeedsCoreData::seedDepartment()`; `PrincipalGradeScaleTest`.
  5. `Department::gradeScaleBands()`, the `GradeScaleBand` model and its entry in `AuditLog::ENTITY_CATEGORY`.
- **Known bug, for phase 4 to deal with when it retires the page:** `GradeScaleController` hard-deletes bands, against the no-hard-delete rule: `destroy()` deletes a band, and `loadDefaults()` deletes a department's bands before re-creating them. It isn't fixed in phase 3 because the page is being retired.
