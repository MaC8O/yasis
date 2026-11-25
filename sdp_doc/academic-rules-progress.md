# Academic and fee rules: progress

Source of truth: [YASIS_Academic_and_Fee_Rules.md](YASIS_Academic_and_Fee_Rules.md). The build order is in its section 11: one branch and one PR per phase. Each phase needs the owner's approval of its plan and migrations before any code is written, and the owner's go-ahead before the next phase starts. Nothing is merged, pushed to yasis01 or deployed by Claude.

| Phase | Status | Branch / PR |
|---|---|---|
| 1. Grade levels on sections | Merged | `grade-levels`, PR #40 |
| 2. Curriculum (`subject_offerings`, Grade 11-12 electives) | PR open, awaiting review | `curriculum`, PR #41 |
| 3. Grading schemes per grade level | Not started | |
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

## Phase 2: curriculum (done, in review)

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

## Open questions waiting on the school
All of Q1 to Q18 in the rules file are still open, as are the Treasurer questions T1 to T11. A KG1/KG2 decision has been recorded under Q2 while the school's answer is pending.

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
| T1 to T11 | T1 to T11 (same numbers) |

## Notes for the next session
- Later phases look rules up by `grade_levels.code` and `subjects.code`, never by id or name. Fee and grading ranges such as "KG to Grade 6" are ranges of `sort_order`, so they cover KG1 and KG2.
- **Curriculum as an input to later phases:**
  - Only active offerings (`archived_at` null) are counted.
  - `counts_in_result` decides what goes into an average, point total or GPA.
  - `credit_hours` is the GPA weight.
  - A row with `pending_question` set is an unconfirmed default.
  - For Grades 11-12, a student's counted subjects are the compulsory rows plus the row they chose in each slot (`student_electives`).
- **Testing migrations:**
  - Use a throwaway database: run a fresh `migrate --seed`, and test rolling back on it.
  - **Don't copy staging's database to this PC without asking the owner first** (owner's rule, 2026-11-25). If a staging copy seems necessary, explain why and wait for an answer.
