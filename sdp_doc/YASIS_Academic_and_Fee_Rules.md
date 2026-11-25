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
- The report card has a column per term. See Q3 for what the "Final" column means.

Default: the "Final" column is the fourth term's result, and the yearly result for each subject is the average of the four term results. The Statement of Marks (section 7) shows the yearly result.

---

## 3. Subjects and curriculum by grade level

Store the curriculum as data (one row per subject per grade level), not in code. Suggested table `subject_offerings`:

| Column | Meaning |
|---|---|
| grade_level | KG, 1 ... 12 |
| subject_id | links to `subjects` |
| report_label | the label printed on the report card, e.g. `MORAL/VALUES EDU` |
| is_compulsory | compulsory or optional |
| elective_slot | null, or a slot name for Grade 11-12 choices (see below) |
| credit_hours | used only for the Grade 11-12 GPA |
| grading_mode | `score` (0-100) or `pass_fail` |
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

The KG-4 Statement of Marks sample lists only Moral, Myanmar, English, Mathematics, Science and MAPE, and its accumulative score (90) is the average of those six. Default: Character Building and Social Study appear on the report card but are not counted in the accumulative score. See Q4.

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

All scored out of 100, all compulsory, all counted in the average. SUPW is Socially Useful Productive Work. The Grade 5-9 statement lists SUPW with the compulsory subjects, so default: SUPW is scored out of 100 and counted. See Q5.

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

All scored out of 100. Grade 10 sits the external DHSC exam (section 5). Which subjects count towards the point total is unclear; see Q6.

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
| Biology or World History | 2 | Optional, slot A |
| Chemistry or World Geography | 2 | Optional, slot B |
| Physics or Accounting | 2 | Optional, slot C |
| SUPW | none | Compulsory, Pass/Fail, not in the GPA |

The policy document pairs the optional subjects (Biology / World History, Chemistry / World Geography, Physics / Accounting), so each student takes one subject from each slot. A student's total is therefore always 23 credit hours (17 compulsory + 3 x 2 optional).

Required: a per-student, per-year record of the chosen subject in each slot (for example `student_electives`), set by the Registrar. The report card prints all optional subjects and shows `-` for the ones the student does not take, as in the sample statement.

The sample student took Biology, Chemistry and Physics (a science combination). Whether students may mix slots (for example Biology with Accounting) is Q7. Default: each slot is chosen separately.

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

For KG-9 these five categories are school policy, not a teacher's choice. Required:

- When a gradebook is opened for a KG-9 section, it starts with these five categories and weights.
- Teachers cannot rename, delete or reweight them. The Admin can change the template for the whole school.
- Teachers still create their own items inside each category (several quizzes, several assignments, a midterm and a final exam), which fits the planned Moodle-style gradebook.
- Within a category, the category score is the total marks earned divided by the total possible marks of its items.

The attendance component. Default: it is calculated from the attendance records, not entered by the teacher. Attendance score = 10 x (days counted as attended / school days in the term). Present and Tardy count as attended. Absent does not. Excused days are left out of both the top and the bottom of the fraction, so an excused absence does not lower the mark. The teacher sees it read-only in the gradebook. See Q8.

Whether this composition also applies to Grade 10 and Grades 11-12 is Q9. Default: it applies to Grade 10 (the policy says the school decides Grade 10 exam eligibility using section 3 of the policy, which is this composition). For Grades 11-12, teachers keep the current free weighting until the school confirms.

### 4.2 Pass and class of pass

- A student passes the year when every counted subject has a yearly mark of at least 40%.
- The accumulative score is the average of the counted subjects' yearly marks.
- Class of pass comes from the accumulative score:

| Accumulative score | Class of pass |
|---|---|
| 95 and above | Distinction |
| 81 to 94 | First |
| 61 to 80 | Second |
| 40 to 60 | Third |
| below 40, or any counted subject below 40 | Fail |

Rounding (default, see Q10): marks are stored with two decimals. Round the accumulative score half up to a whole number before looking up the class, so 60.5 becomes 61 (Second). Show whole numbers on the report card and statement, as the samples do.

Worked example, from the KG-4 sample statement (Grade 2):

| Subject | Score |
|---|---|
| Moral | 89 |
| Myanmar | 90 |
| English | 91 |
| Mathematics | 82 |
| Science | 94 |
| MAPE | 94 |

Average = 540 / 6 = 90. Every subject is at least 40, so the result is PASS. 90 is in 81-94, so the class is FIRST. This matches the printed statement, so use it as a test fixture.

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
| below 40 | Fail | 0 (default; not in the document) |

Class of pass by total points:

| Total points | Class |
|---|---|
| 66-70 | Distinction |
| 56-65 | First |
| 46-55 | Second |
| 40-45 | Third |

The maximum of 70 means seven subjects at 10 points each count towards the total, but the Grade 10 report card has 13 subjects. Which seven count is Q6. Until the school answers, build the calculation so the counted subjects come from `counts_in_result`, and seed it with a clear placeholder that the Admin must confirm.

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
4. SUPW is Pass/Fail and is left out of the GPA.
5. Round the GPA to two decimals (half up).

Class of pass by GPA:

| GPA | Class |
|---|---|
| 3.68-4.00 | Distinction |
| 3.33-3.67 | First |
| 2.68-3.32 | Second |
| 2.00-2.67 | Third |
| below 2.00 | Fail |

Promotion rule: the student needs a GPA of at least 2.00, and must not fail (F) any compulsory subject. A GPA above 2.00 with a failed compulsory subject is still FAIL. Whether failing SUPW also fails the student is Q5.

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

- Header "MAX. MARKS (100)", then columns First, Second, Third, Final. The Grade 11-12 card adds an HRS column after the subject.
- One row per subject in the order in section 3.
- Then the rows G.P.A, POSITION, TOTAL NUMBER IN CLASS, DAYS ABSENT.
- Footnote, verbatim: "Any student who has more than ten per cent absence in a subject in a term may be denied permission to write the final examination. (Or) A minimum of 80% attendance is required to sit for any examination."

What the summary rows contain:

- G.P.A: for Grades 11-12, the credit-hour GPA. For KG-10 the cards also have a "G.P.A" row; default: show the term's average percentage there for KG-9 and the point total for Grade 10. See Q12.
- POSITION: the student's rank in their section for that term, by average (KG-9), point total (Grade 10) or GPA (11-12). Default: tied students share a rank and the next rank is skipped (1, 2, 2, 4). See Q13.
- TOTAL NUMBER IN CLASS: students actively enrolled in the section on the last day of the term.
- DAYS ABSENT: Absent plus Excused days in the term. See Q8.

Student's personality record:

- Five traits, each rated A, B or C: Creative Thinking, Character, Discipline, Social, Responsibility.
- Entered by the homeroom teacher. The card has one set of columns, so default: rated once per term, and the printed card shows the latest term's ratings. See Q14.

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

- Examination centre is "YANGON ADVENTIST SEMINARY INTERNATIONAL SCHOOL" on the KG-9 samples and "YANGON ADVENTIST SEMINARY" on the Grade 11-12 sample. Default: store it as a setting with the first wording, and ask (Q15).
- Date of examination and date of issue are entered by the Registrar when the statement is generated.

Body:

- KG-9: two columns of compulsory subjects with SCORE %, and the ACCUMULATIVE SCORE.
- Grade 11-12: COMPULSORY subjects (Score letter, HRS) and OPTIONAL subjects (Score letter, HRS, `-` for subjects not taken), and the GPA.
- RESULT (PASS or FAIL) and CLASS OF PASS (DISTINCTION, FIRST, SECOND, THIRD).
- Signature lines for the Registrar and the Principal.

Footer for KG-9, verbatim: "These examinations are conducted by YAS, Myanmar Union of Seventh-day Adventists operated by the Seventh-day Adventists. This mark list is awarded upon completing all subjects mentioned with a minimum of 40% marks in each subject. Classes are awarded on the following basis: 40-60% aggregate: "Third Class," 61-80% "Second Class," 81-94 % "First," and 95 and above "Distinction.""

Footer for Grade 11-12: the explanation of how the GPA is calculated, and the conversion table from the sample.

Required: add `StatementOfMarks` as a document type in the existing document request flow (Registrar prepares, Principal issues). It is generated from the year's final results. No Grade 10 statement was provided; see Q11.

---

## 8. Attendance and exam eligibility

The report card footnote states two different rules: more than 10% absence in a subject in a term, or less than 80% attendance. These are not the same threshold (90% versus 80%), see Q16.

Required:

- Store the minimum attendance percentage as a setting. Default 80%.
- Attendance is recorded once a day per section, not per subject, so default: the rule is checked on the section's daily attendance for the term.
- The system flags students below the threshold to the homeroom teacher, the VP and the Principal before the exam period. It never blocks a student automatically; a person decides.

---

## 9. Promotion recommendation

The existing promotion flow (Registrar prepares, VP and Principal approve) stays as it is. Add a calculated recommendation for each student, shown next to the decision field so the Registrar can see it while preparing the batch:

| Grade level | Recommendation |
|---|---|
| KG-9 | Promote if every counted subject is at least 40, otherwise Retain. Show the class of pass. |
| Grade 10 | Show the recorded DHSC eligibility and result. No automatic recommendation. |
| Grades 11 | Promote if GPA is at least 2.00 and no compulsory subject is F, otherwise Retain. |
| Grade 12 | Graduate on the same rule, otherwise Retain. |

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

Rules that apply to every phase: every change writes an audit entry with the actor's role in the same transaction; validation in FormRequests and logic in services; nothing hard deleted; marks stored as DECIMAL(5,2), never float.

---

## 12. Questions for the school

The defaults above let the build start, but each of these should be confirmed with the VP Academic or the Registrar.

- Q1. The grading policy provided is dated 2023-2024, while the report cards are for 2025-2026. Is it still the current policy?
- Q2. Is there a level below KG (Nursery / Pre-KG)? If so, how is it graded and which report card does it use?
  - Decided while the school's answer is pending (November 2026): Kindergarten is two levels, KG1 and KG2 (see section 1). Nursery has no grade level for now. Both are data the Admin can change on the Grade Levels page.
- Q3. On the report card, is the "Final" column the fourth term's result, or the yearly result? How is a subject's yearly mark worked out from the four terms: a simple average, a weighted average, or the final exam only?
- Q4. KG-4: do Character Building and Social Study count in the accumulative score? The sample statement leaves them out. Does Social Study start at a particular grade?
- Q5. SUPW: is it scored out of 100 for Grades 5-10 and Pass/Fail for Grades 11-12? Does failing SUPW mean the student fails the year?
- Q6. Grade 10: the class of pass goes up to 70 points, so seven subjects count, but the card has 13. Which seven count?
- Q7. Grades 11-12: may a student mix optional subjects across slots (for example Biology with Accounting), or must they take a whole science or arts combination?
- Q8. Attendance: does an excused absence count against the 10% attendance mark? Does it count in "Days absent" on the card and towards the 80% exam rule? Does Tardy count as present?
- Q9. Does the 10/10/10/10/60 mark composition apply to Grade 10 and Grades 11-12 as well, or only KG-9?
- Q10. How are marks rounded? For example, is 60.5 Third or Second, and is 84.5 a B or an A on the Grade 10 scale?
- Q11. Grade 10: how should the system record DHSC eligibility and the external exam result, and does the school issue a Grade 10 Statement of Marks?
- Q12. What should the "G.P.A" row on the KG-10 report cards show, since those grades do not use a GPA?
- Q13. How are tied positions shown in class rank?
- Q14. Are the personality traits rated every term, or once a year?
- Q15. Which examination centre name should appear on statements: "Yangon Adventist Seminary International School" or "Yangon Adventist Seminary"?
- Q16. The exam eligibility rule says both "more than 10% absence" and "a minimum of 80% attendance". Which threshold applies?
- Q17. Is Grade 9 tuition really 2,600,000 Ks (the same as Grades 11 and 12), and not 2,550,000 Ks like Grades 5-8?
- Q18. Which instalment includes the yearly fee and the refundable deposit, and are the four instalments equal?