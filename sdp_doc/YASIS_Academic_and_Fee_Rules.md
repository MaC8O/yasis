# YASIS academic and fee rules for ISMS

Source documents (provided by the school, November 2026):

- `2023-2024 Grading System.docx` (the only grading policy document provided; see question Q1)
- `YASIS KG to 4 Statement of Mark.docx`, `YASIS Grade 5 to 9 Statement of Mark.docx`, `YASIS Grade 11 & 12 Statement of Mark.docx`
- `2025-2026 KG to Grade 4 Student Personal Report Card (Blue).docx`
- `2025-2026 Grade 5 to 9 Student Personal Report Card (Green).docx`
- `2025-2026 Grade 10 Student Personal Report Card (Yellow).docx`
- `2025-2026 Grade 11 & 12 Student Personal Report Card (Yellow).docx`
- `School Fees 2026-2027` (image)

This file turns those documents into rules the system can implement. Where a document is unclear or two documents disagree, the rule below is marked as a default and the matching question is listed in section 12. Build with the defaults, keep every one of them configurable, and change them once the school answers.

The school answered most of the questions in November 2026 (`YASIS_School_Answers.md`). Answered questions are marked in section 12 and the sections below follow the answers. A default that still waits on the school carries its question number: Q2, Q10 and Q19-Q21 from the first letter, and FQ1-FQ9 for the follow-up questions in section 12.

How to use this file in Claude Code: read the whole file, then read the existing grading, gradebook, report card, promotion, curriculum and fee import code. Send a plan before writing code. Every migration needs approval. Follow the phases in section 11.

---

## 1. The key structural change

The school's rules depend on the student's grade level (KG, 1, 2, ... 12), not on the department (Pre-School, Elementary, Middle School, High School). The grading bands, the subject lists, the report card layout and the fee bands all follow grade levels, and the groupings differ between them:

| Concern | Groupings used by the school |
|---|---|
| Grading | KG-9, Grade 10, Grades 11-12 |
| Report card layout | KG-4 (Blue), 5-9 (Green), 10 (Yellow), 11-12 (Yellow) |
| Subject list | KG-4, 5-9, 10, 11-12 |
| Yearly fee | KG-6, 7-12 |
| Tuition fee | KG-4, 5-8, 10, and 9 + 11 + 12 together |

None of these line up with the four departments, so grade level has to be stored explicitly.

Required:

- Add a grade level to every section (a `grade_levels` table with codes `KG1`, `KG2`, `1` to `12`, plus a sort order, and `sections.grade_level_id`). Do not parse it from the section name.
- Kindergarten is two levels, KG1 and KG2, each with its own sort order before Grade 1, so there are 14 levels. They are separate so that promotion from KG1 to KG2 has a target, the report card's "Assigned to Grade" line can say KG2, and class position never ranks two different years together. Both still belong to the KG-4 grading and report card group, the KG-4 subject list and tuition band, and the KG-6 yearly fee band. Wherever this file says "KG" as part of a range (KG-4, KG-6, KG-9), it means KG1 and KG2.
- Grading schemes, subject offerings, report card templates and fee bands are all looked up by grade level.
- Departments stay as they are for organisation, dashboards and staff.
- This also fixes the "expected subjects per section" count from the class management feature, which currently guesses the grade from subject codes like ENG10.

Default: the school's levels are KG1, KG2 and Grades 1 to 12. The current seed data has a Nursery / Pre-School level below KG1; it is left without a grade level until the school answers. See Q2.

---

## 2. Terms and reporting periods

- The school year has four terms of nine weeks. The report cards call them First, Second, Third and Final.
- A progress report card goes to parents once per term.
- The report card has a column per term for the first three terms. The "Final" column is the whole-year result (Q3, answered).

A subject's yearly mark is the simple average of its four term marks (Q3). The year's result (average, point total or GPA), class of pass and position are worked out from the yearly marks like a term's. The Final column, the Statement of Marks and the promotion recommendation all show this same year result. The fourth term's own result is not printed on the card.

---

## 3. Subjects and curriculum by grade level

Store the curriculum as data (one row per subject per grade level), not in code. Suggested table `subject_offerings`:

| Column | Meaning |
|---|---|
| grade_level | KG, 1 ... 12 |
| subject_id | links to `subjects` |
| report_label | the label printed on the report card, e.g. `MORAL/VALUES EDU` |
| is_compulsory | compulsory or optional |
| track | null, or the track an optional subject belongs to (`science` or `arts`, Grades 10-12; see below) |
| credit_hours | used only for the Grade 11-12 GPA |
| grading_mode | `score` (0-100), `pass_fail`, or `completion` (Pass, Incomplete or Complete, no mark: SUPW) |
| counts_in_result | whether it counts in the average, GPA or point total |
| sort_order | order on the report card |

### KG to Grade 4 (Blue card)

Report card rows, in order:

1. Moral/Values Edu
2. Myanmar
3. English
4. Mathematics
5. Science
6. MAPE (Music, Art and PE taught as one subject)
7. Character Building
8. Social Study

All are scored out of 100.

Character Building counts in the accumulative score and Social Study doesn't (Q4, answered), so KG-4 averages seven subjects. Social Study stays on the report card. The KG-4 sample statement's six-subject average (90) is kept as an arithmetic test only.

### Grade 5 to Grade 9 (Green card)

1. Moral/Values Edu
2. Myanmar
3. English
4. Mathematics
5. Science
6. Social Study
7. Computer
8. PE
9. Music
10. SUPW

All compulsory. SUPW (Socially Useful Productive Work) has no mark (Q5, answered): it is Pass or Incomplete, and Complete once the work is finished, with no Fail. Complete counts as Pass. SUPW is left out of every average and GPA, so Grades 5-9 average nine subjects. The report card and the Statement of Marks print the word. The other nine are scored out of 100 and counted.

The SUPW teacher enters the word per student and term. Once a term is locked only Incomplete can still change, to Complete, because the school gives Complete when the work is finished later. The frozen word, and for the last term the year's word and any issued Statement of Marks, change in place. SUPW counts in no result, so nothing else is recalculated. The year's word is the last term's.

### Grade 10 (Yellow card)

1. Moral/Values Edu
2. Myanmar
3. English
4. Mathematics
5. Computer
6. Biology
7. Chemistry
8. Physics
9. World History
10. World Geography
11. Music
12. SUPW
13. PE

Counted subjects (Q6, answered): Moral, Myanmar, English and Mathematics, plus one track of three. The tracks are Science (Biology, Chemistry, Physics) or Arts (World History, World Geography, Music). Those seven count, for a maximum of 70 points. SUPW (a word, as in Grades 5-9) and PE are extras and don't count. Computer isn't mentioned in the answer, so by default it doesn't count; it is flagged FQ4. The card keeps its printed labels WORLD HISTORY and WORLD GEOGRAPHY and prints "-" for the other track's subjects.

Each student has one track per school year, set by the Registrar, the same record as Grades 11-12. Grade 10 sits the external DHSC exam (section 5).

### Grade 11 and Grade 12 (Yellow card)

| Subject | Hours | Type |
|---|---|---|
| Moral/Values Edu | 3 | Compulsory |
| Myanmar | 3 | Compulsory |
| English | 3 | Compulsory |
| Mathematics | 3 | Compulsory |
| Computer | 2 | Compulsory |
| Music | 2 | Compulsory |
| PE | 1 | Compulsory |
| Biology, Chemistry, Physics | 2 each | Science track |
| World History, World Geography, Accounting | 2 each | Arts track |
| SUPW | none | Compulsory, Pass / Incomplete / Complete, not in the GPA |

Each student takes one whole track, with no mixing (Q7, answered). A student's total is always 23 credit hours (17 compulsory + 6 in the track).

Required: a per-student, per-year track (`student_tracks`), set by the Registrar on the Tracks page. Grade 10 uses the same record. The report card and the statement print every track subject and show `-` for the other track's subjects. Elective choices made per slot before the answer were moved into tracks when all three were from one track. Mixed choices were left for the Registrar to set. The old choices are kept for results frozen before the change.

---

## 4. Grading for KG to Grade 9

### 4.1 How a subject's mark is made up

The policy fixes the composition of every subject's 100 marks:

| Component | Weight |
|---|---|
| Class tests / quizzes | 10% |
| Attendance | 10% |
| Assignments | 10% |
| Participation / obedience / Code of Conduct | 10% |
| Examinations | 60% |
| Total | 100% |

These five categories are school policy, not a teacher's choice, for every grade (Q9, answered). Required:

- When a gradebook is opened for a section, it starts with these five categories and weights.
- Teachers cannot rename, delete or reweight them. The Admin can change the template for the whole school.
- Teachers still create their own items inside each category (several quizzes, several assignments, a midterm and a final exam), which fits the planned Moodle-style gradebook.
- Within a category, the category score is the total marks earned divided by the total possible marks of its items.

The attendance component is calculated from the attendance records, not entered by the teacher. Attendance score = 10 x (days counted as attended / days counted in the term). The school's answers (Q8):

- Present and Tardy (late) count as attended. Absent does not.
- An excused absence counts as an absence (a report rule, `excused_absences`, flagged FQ1). The one rule covers the mark, "Days absent" and the 80% check, so they can't disagree. It can be set to leave Excused days out of both the attended and the counted days.
- Three late arrivals count as one absence (a report rule, `lates_per_absence`). Only whole multiples count: 5 lates are one absence, 6 are two. For a term's figures (the mark and Days absent) the lates are counted per term, using the term's own late total. For the year's 80% check they are counted on the year's total.
- Only the days on which the student has their own attendance record count. Section school days with no record for the student are flagged to the teacher.
- The mark is per subject, per term, but ISMS records attendance once a day per section, so each subject uses the section's daily attendance (`subject_attendance_source` = daily, FQ2). A later subject-attendance feature can switch the setting.

The teacher sees the attendance mark read-only in the gradebook, with the days ("41 of 45 days · 2 excused counted as absent · 6 late = 2 absences"). A freeze stores the attendance rules it used, so a locked term is worked out the same way if it is re-frozen.

The composition applies to every grade (Q9, answered), Grades 11-12 included since phase 9b. Their open-term gradebooks move onto the template when they are opened. Categories whose names match become template categories at the template's weight. Items in categories that match nothing move by their name, then their type, and the emptied category is archived (kept). No grade is deleted. The teacher gets a one-time notice listing every item with its old and new category, and every weight that changed. Locked terms keep their own weights.

### 4.2 Pass and class of pass

- A student passes the year when every counted subject has a yearly mark of at least 40%.
- The accumulative score is the average of the counted subjects' yearly marks: seven subjects for KG-4 (section 3), nine for Grades 5-9.
- Class of pass comes from the accumulative score:

| Accumulative score | Class of pass |
|---|---|
| 95 and above | Distinction |
| 81 to 94 | First |
| 61 to 80 | Second |
| 40 to 60 | Third |
| below 40, or any counted subject below 40 | Fail |

Rounding (default, see Q10, still open: the school sent the policy text instead of an answer): marks are stored with two decimals. Round the accumulative score half up to a whole number before looking up the class, so 60.5 becomes 61 (Second). Show whole numbers on the report card and statement, as the samples do.

Worked example, from the KG-4 sample statement (Grade 2):

| Subject | Score |
|---|---|
| Moral | 89 |
| Myanmar | 90 |
| English | 91 |
| Mathematics | 82 |
| Science | 94 |
| MAPE | 94 |

Average = 540 / 6 = 90. Every subject is at least 40, so the result is PASS. 90 is in 81-94, so the class is FIRST. This matches the printed statement. Since Character Building counts (Q4), the sample's six subjects are kept as an arithmetic test only.

Seven-subject example (the same student with Character Building 83): (540 + 83) / 7 = 89, PASS, FIRST.

---

## 5. Grading for Grade 10 (DHSC)

Grade 10 students sit the external DHSC examination. The final grade depends on that exam, but the school decides who is eligible to sit it, using the earned grades and the KG-9 composition rules.

Subject grade scale:

| Mark | Letter | Points |
|---|---|---|
| 85-100 | A | 10 |
| 76-84 | B | 9 |
| 67-75 | C+ | 8 |
| 58-66 | C | 7 |
| 49-57 | C- | 6 |
| 40-48 | D | 5 |
| below 40 | Fail | 0 (default; not in the document, see Q19) |

Class of pass by total points:

| Total points | Class |
|---|---|
| 66-70 | Distinction |
| 56-65 | First |
| 46-55 | Second |
| 40-45 | Third |
| below 40 | Fail (default; not in the document, see Q19) |

The maximum of 70 is seven subjects at 10 points: the compulsory four and the student's track of three (section 3, Q6 answered).

While Q19 is open, the point total is still shown for every student, but the class of pass is decided only where Q19 doesn't apply. When all seven counted subjects are 40 or above and the total is 40 or above, the total and its class are shown. When a counted subject is below 40, or the total is 35 to 39 (seven D grades, every subject passed), the total is shown with "class pending school answer (Q19)". These are the cases in the bands flagged Q19.

The card's G.P.A row holds the average of the seven counted subjects (Q12, answered), and Grade 10 ranks by that average. The point total still decides the class of pass.

Default for promotion to Grade 11: the system records the school's eligibility decision and the external result entered by the Registrar. The system does not calculate a DHSC pass itself. See Q11.

---

## 6. Grading for Grades 11 and 12 (credit-hour GPA)

Subject grade scale:

| Mark | Letter | Grade value |
|---|---|---|
| 90-100 | A | 4.00 |
| 85-89 | A- | 3.67 |
| 80-84 | B+ | 3.33 |
| 75-79 | B | 3.00 |
| 70-74 | B- | 2.67 |
| 65-69 | C+ | 2.33 |
| 60-64 | C | 2.00 |
| 55-59 | C- | 1.67 |
| 50-54 | D+ | 1.33 |
| 40-49 | D | 1.00 |
| 0-39 | F | 0.00 (Fail) |

GPA calculation, exactly as printed on the statement:

1. Convert each subject's mark to a letter and grade value.
2. Grade points for a subject = grade value x credit hours.
3. GPA = total grade points / total credit hours.
4. SUPW has a word (Pass, Incomplete, Complete), not a mark, and is left out of the GPA.
5. Round the GPA to two decimals (half up).

Class of pass by GPA:

| GPA | Class |
|---|---|
| 3.68-4.00 | Distinction |
| 3.33-3.67 | First |
| 2.68-3.32 | Second |
| 2.00-2.67 | Third |
| below 2.00 | Fail |

Promotion rule: the student needs a GPA of at least 2.00, and must not fail (F) any compulsory subject. A GPA above 2.00 with a failed compulsory subject is still FAIL. SUPW has no Fail (Q5). Whether SUPW Incomplete stops promotion is FQ3; by default it doesn't (section 9).

Worked example, from the Grade 12 sample statement. Use it as a test fixture:

| Subject | Letter | Value | Hours | Points |
|---|---|---|---|---|
| Moral | B | 3.00 | 3 | 9.00 |
| Myanmar | C+ | 2.33 | 3 | 6.99 |
| English | A- | 3.67 | 3 | 11.01 |
| Mathematics | C+ | 2.33 | 3 | 6.99 |
| Computer | A | 4.00 | 2 | 8.00 |
| Music | A- | 3.67 | 2 | 7.34 |
| PE | A | 4.00 | 1 | 4.00 |
| Biology | B | 3.00 | 2 | 6.00 |
| Chemistry | B- | 2.67 | 2 | 5.34 |
| Physics | B- | 2.67 | 2 | 5.34 |
| SUPW | PASS | - | - | - |
| Total | | | 23 | 70.01 |

GPA = 70.01 / 23 = 3.0439, which rounds to 3.04. 3.04 is in 2.68-3.32, so the class is SECOND, and no compulsory subject is F, so the result is PASS. This matches the printed statement.

Note: the conversion table printed on the statement groups letters into classes slightly differently and leaves out D+. Use the GPA ranges above as the rule; the statement's table is only an illustration.

---

## 7. Documents the system must produce

### 7.1 Student's Progress Report (report card), once per term

Four templates, chosen by grade level: KG-4 (Blue), 5-9 (Green), 10 (Yellow), 11-12 (Yellow). The colour is the card's accent colour. All four share this layout:

Front / header:

- School name "YANGON ADVENTIST SEMINARY INTERNATIONAL SCHOOL", address "34 Natmauk Lane (1), Bahan", phone "95-787666619", school logo.
- Student's name, grade, school year, class teacher (the homeroom teacher).
- The note to parents, verbatim: "This progress report is sent to you once in every nine week of the school year. This is to report to you and get your acknowledgement of your child's performance in the class. Please check the record carefully, and if you feel that your child needs help, or if you do not fully understand this report, contact the teacher in order to plan ways of promoting your child's progress."

Scholarship record (the marks table):

- Header "MAX. MARKS (100)", then columns First, Second, Third, Final. The Grade 11-12 card adds an HRS column after the subject. Final is the whole year (section 2), filled on the last term's report.
- One row per subject in the order in section 3.
- Then the rows G.P.A, POSITION, TOTAL NUMBER IN CLASS, DAYS ABSENT.
- Footnote, verbatim: "Any student who has more than ten per cent absence in a subject in a term may be denied permission to write the final examination. (Or) A minimum of 80% attendance is required to sit for any examination."

What the summary rows contain:

- G.P.A: for Grades 11-12, the credit-hour GPA. For KG-10 the row holds the average (Q12, answered): the term's average percentage for KG-9, and the average of the seven counted subjects for Grade 10. The Grade 10 card keeps its printed label "G.P.A".
- POSITION: the student's rank in their section for that term, by average (KG-9 and Grade 10) or GPA (11-12). Tied students share a rank and the next rank isn't skipped: 1, 2, 2, 3 (Q13, answered). Results frozen before this answer keep the old 1, 2, 2, 4. Default: students who failed are ranked together with those who passed; see Q20.
- TOTAL NUMBER IN CLASS: students actively enrolled in the section on the last day of the term; for Final, the year's class.
- DAYS ABSENT: Absent plus Excused days in the term; for Final, the four terms together. The attendance answers (Q8, Q16) are built in phase 9b.

Student's personality record:

- Five traits, each rated A, B or C: Creative Thinking, Character, Discipline, Social, Responsibility.
- Rated once a year by the homeroom teacher, on the last term, and printed on the final report only (Q14, answered). Earlier cards leave the block blank.

Acknowledgement signatures:

- For each of the four reports: Parent(s)/Guardian, Class Teacher, Principal.
- Digital version: the class teacher confirms the term's report, the Principal's existing "Release term results" governance control is the Principal's sign-off, and the guardian presses "I acknowledge this report" in the guardian portal. Store each as a row (student, term, role, user, time). The printed PDF shows the name and date where each signature goes.

Class placement next year (on the final report only):

- "Assigned to Grade ___ for School Year ___. Beginning ___", with teacher's and principal's signatures.
- Filled from the applied promotion batch: the target grade, the next academic year and its start date.

### 7.2 Statement of Marks, once per year

Header, verbatim:

- "THE DEPARTMENT OF EDUCATION"
- "Myanmar Union Mission of Seventh-day Adventists"
- "Yangon, MYANMAR"
- "STATEMENT OF MARKS"

Fields: Name, Grade, Date of birth, Examination centre, Date of examination, Date of issue.

- Examination centre: "YANGON ADVENTIST SEMINARY INTERNATIONAL SCHOOL" on every layout, the Grade 11-12 statement included (Q15, answered). It stays an Admin setting.
- Date of examination and date of issue are entered by the Registrar when the statement is generated.

Body:

- KG-9: two columns of compulsory subjects with SCORE %, and the ACCUMULATIVE SCORE.
- Grade 11-12: COMPULSORY subjects (Score letter, HRS; SUPW's word with HRS "-") and OPTIONAL subjects (both tracks: Score letter, HRS, `-` for the other track's subjects), and the GPA.
- RESULT (PASS or FAIL) and CLASS OF PASS (DISTINCTION, FIRST, SECOND, THIRD).
- Signature lines for the Registrar and the Principal.

Footer for KG-9, verbatim: "These examinations are conducted by YAS, Myanmar Union of Seventh-day Adventists operated by the Seventh-day Adventists. This mark list is awarded upon completing all subjects mentioned with a minimum of 40% marks in each subject. Classes are awarded on the following basis: 40-60% aggregate: "Third Class," 61-80% "Second Class," 81-94 % "First," and 95 and above "Distinction.""

Footer for Grade 11-12: the explanation of how the GPA is calculated, and the conversion table from the sample. The system generates the table from the Grade 11-12 grading scheme's bands (letter, grade value, and points for 3 and 2 hours, D+ included) and prints the class-of-pass GPA ranges under it, because the sample's table placed D in Third and left out D+. The Note paragraph is printed word for word.

Subjects: KG-9 lists every counted subject, so the listed scores make up the accumulative score: Character Building for KG-4 (not Social Study), and PE in Grades 5-9 (see Q21). Grades 5-9 then list SUPW with its word. Labels are the curriculum's report labels.

Required: add `StatementOfMarks` as a document type in the existing document request flow (Registrar prepares, Principal issues). It is generated from the year's final results. The school issues no Grade 10 statement: MYUM's Education Department keeps the DHSC marks and issues it (Q11, answered).

---

## 8. Attendance and exam eligibility

The report card footnote states two rules: more than 10% absence in a subject in a term, or less than 80% attendance. Both apply (Q16, answered): the 80% rule over the whole school year, and the 10% rule per subject, per term.

Rules, each a report rule the Admin edits and the Principal reads:

- **Minimum attendance:** 80% (`min_attendance_pct`) over the school year (`attendance_check_period` = year; it can be set to each term). A student's absences are their counted days minus their attended days, with the excused and late-arrival rules of section 4.1; for the year, late arrivals are converted on the year's total.
- **Absence in a subject:** more than 10% (`max_subject_absence_pct`) absent in a term. ISMS has no attendance per subject, so it uses the section's daily attendance and is the same for every subject (FQ2).
- **Measured against the whole period** (`flag_basis` = whole_period, the default): the rules are about a whole term and a whole year, so a student is flagged only once the breach is certain:
  - the 10% rule, when the term's absences are more than 10% of the term's school days;
  - the 80% rule, when the year's absences so far are more than 20% of the year's school days (or the term's, with the check set to each term).
  For example, a 45-day term allows 4 absences: the 5th flags the student, however early in the term. The attendance so far is shown beside each flagged student as information. Set to `so_far`, the flags fire on the days recorded so far instead, as before.
- **School days:** each term from its start date to its end date, on the days the school meets (`school_week`: Monday to Friday by default, or Monday to Saturday, or Sunday to Thursday), minus the published Academic Calendar events of type Holiday or Term Break inside it. Exams, meetings and activities are school days. The year is its terms added up, so the gaps between terms don't count. A holiday added to the calendar later lowers the open terms' school days; locked terms keep their frozen figures.
- **Days absent** on the report card: absent days, excused absences (by the same rule), and absences from late arrivals counted per term with the term's own lates. The Final column adds up the four terms. For a locked term, the card and the homeroom list both show the figures frozen with the term.

The system flags students on the homeroom teacher's Report Cards page, on the Attendance Check page (VP and Principal) and on the VP and Principal dashboards. It never blocks a student automatically; a person decides.

---

## 9. Promotion recommendation

The existing promotion flow (Registrar prepares, VP and Principal approve) stays as it is. Add a calculated recommendation for each student, shown next to the decision field so the Registrar can see it while preparing the batch:

| Grade level | Recommendation |
|---|---|
| KG-9 | Promote if every counted subject is at least 40, otherwise Retain. Show the class of pass. |
| Grade 10 | Show the recorded DHSC eligibility and result. No automatic recommendation. |
| Grades 11 | Promote if GPA is at least 2.00 and no compulsory subject is F, otherwise Retain. |
| Grade 12 | Graduate on the same rule, otherwise Retain. |

SUPW Incomplete at the end of the year is shown next to the recommendation ("SUPW Incomplete"). By default it doesn't change the recommendation; a report rule can make it Retain (FQ3). A late Complete updates the year's recommendation only when that rule is on.

The Registrar may choose a different decision than the recommendation, but must write a reason, and the audit entry records both.

---

## 10. Fees for 2026-2027

The finance module stays import-only. The school's accounting process is still the source of truth for what was paid, and the ISMS never records payments. The fee schedule is reference data: it lets the system show families the published fees and check imported records for obvious mistakes.

Yearly fee:

| Grade levels | Amount |
|---|---|
| KG to Grade 6 | 250,000 Ks |
| Grade 7 to Grade 12 | 300,000 Ks |

Refundable deposit: 20,000 Ks, charged once, for new students only.

Tuition for one year:

| Grade levels | Amount |
|---|---|
| KG to Grade 4 | 2,400,000 Ks |
| Grade 5 to Grade 8 | 2,550,000 Ks |
| Grade 10 | 2,500,000 Ks |
| Grades 9, 11 and 12 | 2,600,000 Ks |

Grade 9 is grouped with Grades 11 and 12, not with Grades 5-8. This matches the school's sheet; confirm it is not a typing error (Q17).

Notes from the sheet:

- Tuition is paid in four instalments, starting when the child is first registered.
- Payment is preferably made at the school in cash, by MMQR, or by bank transfer to the school's accounts.

Required:

- A `fee_schedules` table keyed by academic year and grade level (or a grade range), with fee type (yearly, tuition, deposit), amount and number of instalments. Amounts are DECIMAL, currency MMK.
- The Treasurer manages it, versioned per academic year, and every change is audited.
- Guardians see the published schedule for their child's grade on the Fees page, alongside their imported records.
- On import, flag (do not reject) a student whose imported annual charge does not match the schedule for their grade, for the Treasurer to review.

How the yearly fee and deposit fall into the four instalments is not stated. Default: the schedule shows tuition per instalment as the annual amount divided by four (600,000, 637,500, 625,000 and 650,000 Ks), and the yearly fee and deposit as separate one-time lines. See Q18.

---

## 11. Build order

Each phase is its own branch and PR, with tests, and needs approval before merging.

1. Grade levels. Add the grade level to sections, backfill existing sections, and update the section create/edit forms and the "expected subjects" count.
2. Curriculum. The `subject_offerings` table, seeded with the subject lists in section 3, plus Grade 11-12 elective slots and the Registrar screen to set each student's electives. Admin can edit the curriculum.
3. Grading schemes. Replace the department grade scales with schemes per grade level: bands (letter, minimum, maximum, value) and class-of-pass bands, seeded with sections 4 to 6. Admin edits them; the Principal sees them read-only. This replaces the Principal's grade scale card, which currently lists every department.
4. Calculation service. One service that computes subject results, averages, Grade 10 point totals, Grade 11-12 GPAs, class of pass, pass/fail, position and the promotion recommendation. Write unit tests first, including the two worked examples in sections 4.2 and 6, boundary values (39.5, 40, 60.5, 94.5, 2.675, 3.675) and a student with a failed compulsory subject but a GPA above 2.00.
5. KG-9 gradebook template. The fixed five categories, the calculated attendance component, and teacher-created items inside each category. Coordinate with the planned Moodle-style gradebook work so the two are built once.
6. Report cards. The four templates, personality ratings, acknowledgements, days absent and the attendance eligibility flag.
7. Statement of Marks. The new document type and its two layouts.
8. Fee schedule. The table, the Treasurer screen, the guardian view and the import check.
9. The school's answers. 9a: curriculum and results (KG-4 counting, SUPW's words, Grade 10 and Grades 11-12 tracks, the Final column, dense ranking, the Grade 10 G.P.A row, personality once a year, the Statement of Marks). 9b: the gradebook and attendance (the Grades 11-12 template, and the attendance rules of Q8 and Q16).

Rules that apply to every phase: every change writes an audit entry with the actor's role in the same transaction; validation in FormRequests and logic in services; nothing hard deleted; marks stored as DECIMAL(5,2), never float.

---

## 12. Questions for the school

The school answered the first letter in November 2026 (`YASIS_School_Answers.md`; the letter skipped Q2, so the school's numbers are one lower from Q3). Each answered question is marked, with what changed.

- Q1. The grading policy provided is dated 2023-2024, while the report cards are for 2025-2026. Is it still the current policy?
  - **Answered:** yes, it is still current.
- Q2. Is there a level below KG (Nursery / Pre-KG)? If so, how is it graded and which report card does it use?
  - Not asked (the letter left it out). Decided while it is open (November 2026): Kindergarten is two levels, KG1 and KG2 (see section 1). Nursery has no grade level for now. Both are data the Admin can change on the Grade Levels page.
- Q3. On the report card, is the "Final" column the fourth term's result, or the yearly result? How is a subject's yearly mark worked out from the four terms: a simple average, a weighted average, or the final exam only?
  - **Answered:** the whole-year result, the simple average of the four terms (section 2). Built in 9a.
- Q4. KG-4: do Character Building and Social Study count in the accumulative score? The sample statement leaves them out. Does Social Study start at a particular grade?
  - **Answered:** Character Building counts, Social Study doesn't (section 3). Built in 9a.
- Q5. SUPW: is it scored out of 100 for Grades 5-10 and Pass/Fail for Grades 11-12? Does failing SUPW mean the student fails the year?
  - **Answered:** no mark: Pass or Incomplete, then Complete once the work is done; no Fail (section 3). Built in 9a. Whether Incomplete stops promotion is FQ3.
- Q6. Grade 10: the class of pass goes up to 70 points, so seven subjects count, but the card has 13. Which seven count?
  - **Answered:** Moral, Myanmar, English, Mathematics and one track of three (section 3). Built in 9a. Computer is FQ4.
- Q7. Grades 11-12: may a student mix optional subjects across slots (for example Biology with Accounting), or must they take a whole science or arts combination?
  - **Answered:** a whole track, no mixing (section 3). Built in 9a.
- Q8. Attendance: does an excused absence count against the 10% attendance mark? Does it count in "Days absent" on the card and towards the 80% exam rule? Does Tardy count as present?
  - **Answered:** excused absences count; three late arrivals are one absence. Read as an absence for the mark, days absent and the 80% rule (FQ1 confirms). Built in 9b (sections 4.1 and 8).
- Q9. Does the 10/10/10/10/60 mark composition apply to Grade 10 and Grades 11-12 as well, or only KG-9?
  - **Answered:** every grade (section 4.1). Grades 11-12 moved onto it in 9b.
- Q10. How are marks rounded? For example, is 60.5 Third or Second, and is 84.5 a B or an A on the Grade 10 scale?
  - Not answered (the school pasted the policy text). Half up stays. Still open.
- Q11. Grade 10: how should the system record DHSC eligibility and the external exam result, and does the school issue a Grade 10 Statement of Marks?
  - **Answered:** no school statement for Grade 10; MYUM's Education Department keeps the DHSC marks and issues it. As built.
- Q12. What should the "G.P.A" row on the KG-10 report cards show, since those grades do not use a GPA?
  - **Answered:** an average. Unchanged for KG-9; Grade 10 shows its average, not the point total (section 7.1). Built in 9a.
- Q13. How are tied positions shown in class rank?
  - **Answered:** tied students share a rank and the next isn't skipped: 1, 2, 2, 3 (section 7.1). Built in 9a.
- Q14. Are the personality traits rated every term, or once a year?
  - **Answered:** once a year, printed on the final report (section 7.1). Built in 9a.
- Q15. Which examination centre name should appear on statements: "Yangon Adventist Seminary International School" or "Yangon Adventist Seminary"?
  - **Answered:** "Yangon Adventist Seminary International School", the Grade 11-12 statement included. Flag cleared in 9a.
- Q16. The exam eligibility rule says both "more than 10% absence" and "a minimum of 80% attendance". Which threshold applies?
  - **Answered:** both: the 80% rule over the whole school year, and the 10%-absence rule per subject, per term. Built in 9b (section 8; FQ2 for subject attendance).
- Q17. Is Grade 9 tuition really 2,600,000 Ks (the same as Grades 11 and 12), and not 2,550,000 Ks like Grades 5-8?
  - **Answered:** yes, 2,600,000 Ks. The flag is cleared in the fee PR.
- Q18. Which instalment includes the yearly fee and the refundable deposit, and are the four instalments equal?
  - Partly answered by the fee documents: four instalments, not equal (June, August, October, January); the amounts are FQ6. Built in the fee PR.
- Q19. Grade 10: what is the result when a subject is below 40 marks? And what is the class of pass when the point total is below 40? For example, a student with D (40-48) in all seven counted subjects passes every subject but has only 35 points.
  - Not sent yet. Meanwhile the total is shown with "class pending school answer (Q19)" in those cases (section 5).
- Q20. When ranking students for their position in class, are students who failed ranked together with those who passed, or are they placed after all passing students?
  - Not sent yet.
- Q21. The Grade 5-9 sample statement leaves out PE, but PE counts in the average. Should PE appear on the statement?
  - Not sent yet.

### Open questions

Follow-up questions for the VP (numbered FQ1 to FQ9 in the data and the code):

- FQ1. Does an excused absence count as an absence for the 10% mark and the 80% rule? (9b default: yes.)
- FQ2. Does each subject teacher take attendance every lesson today? Would daily attendance be acceptable for the 10% subject attendance mark? (9b default: daily attendance.)
- FQ3. Does SUPW "Incomplete" stop a student being promoted? (Default: no; a report rule, `supw_incomplete_blocks_promotion`.)
- FQ4. Does Grade 10 Computer count? (Default: no; the curriculum row is flagged FQ4.)
- FQ5. Is Pre KG the same as KG1, and is KG1 tuition 1,600,000 or 2,400,000? (Fee PR.)
- FQ6. What are the four 2026-2027 instalment amounts for each grade? (Fee PR.)
- FQ7. Is the earlier rule that SDA discounts aren't shown to families now withdrawn? (8b-2 follows the Treasurer: families see every line.)
- FQ8. Should ISMS hold back report cards when fees are owing, or only warn the Registrar? (Not built.)
- FQ9. Can a student's account code be given to a new student after they leave? (8b-2 handles either answer.)

Still to send from the first letter: Q19, Q20 and Q21 (above). Q2 and Q10 are also still open.
