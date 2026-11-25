# Academic and fee rules: progress

Source of truth: [YASIS_Academic_and_Fee_Rules.md](YASIS_Academic_and_Fee_Rules.md). The build order is in its section 11: one branch and one PR per phase. Each phase needs the owner's approval of its plan and migrations before any code is written, and the owner's go-ahead before the next phase starts. Nothing is merged, pushed to yasis01 or deployed by Claude.

| Phase | Status | Branch / PR |
|---|---|---|
| 1. Grade levels on sections | PR open, awaiting review | `grade-levels`, PR #40 |
| 2. Curriculum (`subject_offerings`, Grade 11-12 electives) | Not started | |
| 3. Grading schemes per grade level | Not started | |
| 4. Calculation service (tests first) | Not started | |
| 5. KG-9 gradebook template | Not started | |
| 6. Report cards | Not started | |
| 7. Statement of Marks | Not started | |
| 8. Fee schedule (reference data only) | Not started | |

## Phase 1: grade levels (done, in review)

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
- **Sorting and expected subjects:**
  - Sections are listed by grade level, with sections that have no level last.
  - The "expected subjects" count uses the stored level. `Section::gradeNumber()`, which parsed the name, is removed.
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

**Carried into phase 2:** the expected-subjects count still decides the *subject* side from code suffixes, so ENG10 counts as Grade 10. Phase 2's `subject_offerings` replaces that with curriculum rows per grade level, and the code-suffix rule in `SectionService::expectedSubjects` should then be deleted.

## Defaults applied so far

| Rule | Default | Question |
|---|---|---|
| Levels | KG1, KG2, 1 to 12. Nursery has no level. | Q2 |

## Open questions waiting on the school
All of Q1 to Q18 in the rules file are still open. A KG1/KG2 decision has been recorded under Q2 while the school's answer is pending.

## Notes for the next session
- Later phases look rules up by `grade_levels.code`, never by id or name. Fee and grading ranges such as "KG to Grade 6" are ranges of `sort_order`, so they cover KG1 and KG2.
- **Testing migrations:**
  - On a fresh `migrate --seed`: run it against a throwaway database.
  - On staging's data: stream a dump over SSH and restore it into a throwaway local database. Strip the dump's first line (`/*M!999999\- enable the sandbox mode */`) or the local MariaDB client rejects it. Delete the database and the dump afterwards.
