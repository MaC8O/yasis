# YASIS school answers: analysis and next steps
 
As of 2026-11-27. Sources: the school's answers to the questions letter, the Treasurer's answers (T1 to T11), `School Fees for 2026-2027 Final`, `Tuition Discount Schemes` and `Fees Structure.xlsx`.
 
The school's answers settle most of the open questions. Several of them overturn defaults that are already built, though, and two Treasurer answers change phase 8b-2.
 
Numbering: the school answered using the numbers from the letter, which skipped file Q2 (Nursery). So letter Q2 is file Q3, and so on up to letter Q17, which is file Q18. This file refers to topics rather than numbers.
 
## Confirmed as built
 
- The 2023-2024 grading policy is still current.
- The examination centre is "Yangon Adventist Seminary International School", including on the Grade 11-12 statement.
- Grade 10 gets no school Statement of Marks. MYUM's Education Department keeps the DHSC marks and issues that statement, which matches phase 7.
- Grade 9 tuition is 2,600,000 Ks. The final fee sheet and the spreadsheet both show it, so the Q17 flag can be cleared.
- The 10/10/10/10/60 make-up of each subject's marks applies to every grade.
## Answers that change built code
 
Ten answers reverse or fill a default that is already built. The rounding question (60.5, 84.5) wasn't answered, since the school pasted the policy text instead, so the half-up default stays.
 
| Topic | School's answer | What changes | Phases |
| --- | --- | --- | --- |
| Report card "Final" column | Whole-year result, the simple average of the four terms | Reverses "Final = Term 4". The fourth term's own mark no longer appears on the card | 4, 6 |
| KG-4 counted subjects | Character Building counts, Social Study doesn't | KG-4 averages seven subjects. The 90 worked example (six subjects) stays as an arithmetic test only | 2, 7 |
| SUPW, Grades 5-12 | No mark: Pass or Incomplete, then Complete once the work is done. No Fail | Left out of every average. Grade 5-9 averages nine subjects, not ten. Whether Incomplete blocks promotion is open | 2, 3, 4, 7 |
| Grade 10 counted subjects | Compulsory: Moral, Myanmar, English, Maths. One track of three: Science (Biology, Chemistry, Physics) or Arts (History, Geography, Music). SUPW and PE are extras | Seven counted subjects, max 70 points. Grade 10 needs a track choice. Computer isn't mentioned, so treat it as not counted and ask | 2, 4 |
| Grades 11-12 options | A whole track: Science (Biology, Chemistry, Physics) or Arts (World History, World Geography, Accounting). No mixing | The per-slot choice becomes one track choice | 2 |
| Grades 11-12 gradebook | The 10/10/10/10/60 make-up applies to all grades | Fixed five-category template, so teachers' free weighting goes away | 5 |
| G.P.A row, KG-10 cards | Use an average | No change for KG-9. Grade 10 shows the average, not the point total | 6 |
| Ties in class position | Tied students share a rank, the next rank isn't skipped | 1, 2, 2, 3 instead of 1, 2, 2, 4 | 4 |
| Personality traits | Rated once a year | Entered once, printed on the final report | 6 |
| Attendance | Excused absences count. Three late arrivals = one absence. The 80% rule is over the whole school year. The 10% mark and the 10%-absence exam rule are per subject, per term | Excused counts against attendance (read as an absence). Lates convert. The 80% check becomes yearly. Subject attendance doesn't exist yet (see the decision below) | 5, 6, 8 |
 
## The fee documents
 
The four fee documents don't fully agree with each other.
 
- Instalments aren't equal. The payment table in the discount document shows four instalments in June, August, October and January, with a smaller first one. For Grade 12 that's 250,000 and then 750,000 three times, and the yearly fee is paid in June. But the same table shows Grade 10 tuition at 2,450,000 and a yearly fee of 250,000 for Grades 10 and 12, where the final sheet says 2,500,000 and 300,000. So the amounts look like last year's or an example, while the structure is probably right. Phase 8a splits tuition equally, so it will need instalment amounts and due months instead.
- KG1 tuition is unclear. The spreadsheet's monthly figures rebuild every tuition amount exactly. For example, Grade 5-8 is 205,000 instructional + 25,000 electricity + 10,000 water + 15,000 computer = 255,000 a month, times 10 months = 2,550,000. On the same basis KG1 ("Pre School", 160,000 a month) comes to 1,600,000, not the 2,400,000 we seeded. The final sheet also says "Pre KG to Grade 6" for the yearly fee, so Pre KG probably means KG1. Ask before families see the KG1 figure.
- Discounts. Second child 5%, third child 10%, fourth child free. Paying the full year at registration gives 10% off, and paying six months gives 5%. The spreadsheet shows SDA students paying 45%, and SDA workers' children 40% of that. In Sun, discounts show up as allowance lines against full charges, so the import check still works and nothing needs building. At most, the guardian fee card could show the discount notes as text.
- Due dates. Fees must be paid by the 15th of the month.
- Report cards held for unpaid fees. Report cards and transcripts are released only when all invoices are paid. ISMS is import-only and its data can be weeks old, so it shouldn't block anything automatically. A warning to the Registrar when issuing a statement, with a person deciding, fits the rest of the system.
## The Treasurer's answers
 
| Question | Answer | Effect |
| --- | --- | --- |
| T1 Same report on demand | Yes | Confirmed |
| T2 Period 01 is April | Yes | Confirmed |
| T3 Codes reused | Yes, a code can go to a different student | 8b-1 assumes the opposite: a link is permanent and auto-matches every year. The two years of data show no reuse (all 887 accounts carried over), so this may be a misread. Build for the safe case anyway: links get a year range, and a code whose printed name changes is held for the Treasurer to confirm |
| T4, T5 What families see | Families see every transaction | Reverses "hide allowances". Netting into charges is no longer needed, and the leadership switch mostly stops mattering. It contradicts the original rule that SDA discounts aren't shown to guardians, so the VP should confirm that rule is withdrawn |
| T6 Credit balance | Payment in advance or overpayment, shown as "In credit" | Confirmed |
| T7 Student Control | Carries charges as well as receipts | Relabel "Unallocated in Student Control" as "Student Control balance" |
| T8 Opening balance differences | Adjustments after printing are possible | Keep the warning |
| T9 Ferry fee | Still charged monthly | Keep the transport category |
| T10 Instalments | Four instalments, as on the fee sheet | Confirmed |
| T11 The star | Not sure | Keep storing it, no special handling |
 
## Follow-up questions for the VP
 
1. Does an excused absence count as an absence for the 10% mark and the 80% rule?
2. Does each subject teacher take attendance every lesson today? Would daily attendance be acceptable for the 10% subject attendance mark?
3. Does SUPW "Incomplete" stop a student being promoted?
4. Does Grade 10 Computer count?
5. Is Pre KG the same as KG1, and is KG1 tuition 1,600,000 or 2,400,000?
6. What are the four 2026-2027 instalment amounts for each grade?
7. Is the earlier rule that SDA discounts aren't shown to families now withdrawn?
8. Should ISMS hold back report cards when fees are owing, or only warn the Registrar?
9. Can a student's account code be given to a new student after they leave?
Q19, Q20 and Q21 (the Grade 10 35-point case, ranking failed students, and PE on the Grade 5-9 statement) haven't been sent yet. They can go in the same message.
 
## Decision: subject attendance
 
Recommendation: keep daily attendance for every subject's 10% for now, and ask follow-up question 2. The school's rule needs attendance per subject, and ISMS records it once a day per section.
 
| Option | How it works | Cost | Fit with the school's rule |
| --- | --- | --- | --- |
| Daily attendance for every subject | As now | None | Doesn't follow the stated rule |
| Build subject attendance | Each subject teacher takes attendance per lesson, pre-filled from that day's daily attendance, so a normal day is one tap | About one PR | Follows it |
 
Building subject attendance only pays off if teachers already take attendance per lesson on paper.
 
## Suggested order
 
1. 8b-2, revised as in the prompt below.
2. A "school answers" PR for the academic changes above.
3. A small fee PR for instalment amounts and due months, plus the KG1 flag.
4. The teacher-scope PR (QA #4 and homeroom classes across years).
5. A staging reset.
 