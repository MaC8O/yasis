<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The school's curriculum (sdp_doc/YASIS_Academic_and_Fee_Rules.md §3), seeded here so every
     * environment has it without running a seeder. Creates the 18 school-wide subjects (a subject
     * that already has one of these codes is reused) and one offering per subject per grade level.
     * Existing rows are not changed. Rows that carry a default awaiting the school are flagged in
     * `pending_question` with the rules file's question number.
     */
    public const SUBJECTS = [
        'MORAL' => ['Moral/Values Education', 'MORAL/VALUES EDU'],
        'MYAN' => ['Myanmar', 'MYANMAR'],
        'ENG' => ['English', 'ENGLISH'],
        'MATH' => ['Mathematics', 'MATHEMATICS'],
        'SCI' => ['Science', 'SCIENCE'],
        'MAPE' => ['MAPE (Music, Art and PE)', 'MAPE'],
        'CHAR' => ['Character Building', 'CHARACTER BUILDING'],
        'SOCST' => ['Social Study', 'SOCIAL STUDY'],
        'COMP' => ['Computer', 'COMPUTER'],
        'PE' => ['Physical Education', 'PE'],
        'MUSIC' => ['Music', 'MUSIC'],
        'SUPW' => ['SUPW (Socially Useful Productive Work)', 'SUPW'],
        'BIO' => ['Biology', 'BIOLOGY'],
        'CHEM' => ['Chemistry', 'CHEMISTRY'],
        'PHYS' => ['Physics', 'PHYSICS'],
        'WHIST' => ['World History', 'WORLD HISTORY'],
        'WGEO' => ['World Geography', 'WORLD GEOGRAPHY'],
        'ACCT' => ['Accounting', 'ACCOUNTING'],
    ];

    /**
     * Offerings per grade level, in report-card order. Each row: subject code => overrides of
     * the defaults (compulsory, score out of 100, counted, no slot, no hours, no flag).
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function curriculum(): array
    {
        // KG-4 (Blue card). Q4 default: Character Building and Social Study are on the card but
        // not counted in the accumulative score.
        $kgTo4 = [
            'MORAL' => [], 'MYAN' => [], 'ENG' => [], 'MATH' => [], 'SCI' => [], 'MAPE' => [],
            'CHAR' => ['counts_in_result' => false, 'pending_question' => 'Q4'],
            'SOCST' => ['counts_in_result' => false, 'pending_question' => 'Q4'],
        ];

        // Grades 5-9 (Green card): all compulsory and counted. Q5 default: SUPW scored and counted.
        $fiveTo9 = [
            'MORAL' => [], 'MYAN' => [], 'ENG' => [], 'MATH' => [], 'SCI' => [], 'SOCST' => [],
            'COMP' => [], 'PE' => [], 'MUSIC' => [], 'SUPW' => ['pending_question' => 'Q5'],
        ];

        // Grade 10 (Yellow card). Q6 placeholder: which seven subjects count towards the DHSC point
        // total is unknown, so none is counted until the Admin confirms.
        $ten = [];
        foreach (['MORAL', 'MYAN', 'ENG', 'MATH', 'COMP', 'BIO', 'CHEM', 'PHYS', 'WHIST', 'WGEO', 'MUSIC', 'SUPW', 'PE'] as $code) {
            $ten[$code] = ['counts_in_result' => false, 'pending_question' => $code === 'SUPW' ? 'Q5,Q6' : 'Q6'];
        }

        // Grades 11-12 (Yellow card, credit-hour GPA): 17 compulsory hours plus one 2-hour subject
        // from each slot. Q7 default: each slot is chosen separately. Q5 default: SUPW is
        // Pass/Fail and left out of the GPA.
        $optional = fn (string $slot) => ['is_compulsory' => false, 'elective_slot' => $slot, 'credit_hours' => 2, 'pending_question' => 'Q7'];
        $elevenTo12 = [
            'MORAL' => ['credit_hours' => 3], 'MYAN' => ['credit_hours' => 3], 'ENG' => ['credit_hours' => 3],
            'MATH' => ['credit_hours' => 3], 'COMP' => ['credit_hours' => 2], 'MUSIC' => ['credit_hours' => 2],
            'PE' => ['credit_hours' => 1],
            'BIO' => $optional('A'), 'CHEM' => $optional('B'), 'PHYS' => $optional('C'),
            'WHIST' => $optional('A'), 'WGEO' => $optional('B'), 'ACCT' => $optional('C'),
            'SUPW' => ['grading_mode' => 'pass_fail', 'counts_in_result' => false, 'pending_question' => 'Q5'],
        ];

        return [
            'KG1' => $kgTo4, 'KG2' => $kgTo4, '1' => $kgTo4, '2' => $kgTo4, '3' => $kgTo4, '4' => $kgTo4,
            '5' => $fiveTo9, '6' => $fiveTo9, '7' => $fiveTo9, '8' => $fiveTo9, '9' => $fiveTo9,
            '10' => $ten,
            '11' => $elevenTo12, '12' => $elevenTo12,
        ];
    }

    public function up(): void
    {
        $now = now();
        $subjectIds = [];

        foreach (self::SUBJECTS as $code => [$name]) {
            $subjectIds[$code] = DB::table('subjects')->where('code', $code)->value('id')
                ?? DB::table('subjects')->insertGetId(['code' => $code, 'name' => $name, 'department_id' => null, 'created_at' => $now, 'updated_at' => $now]);
        }

        $levels = DB::table('grade_levels')->pluck('id', 'code');
        $rows = [];

        foreach ($this->curriculum() as $levelCode => $offerings) {
            if (! $levels->has($levelCode)) {
                continue;
            }
            $order = 0;
            foreach ($offerings as $code => $overrides) {
                $rows[] = array_merge([
                    'grade_level_id' => $levels[$levelCode],
                    'subject_id' => $subjectIds[$code],
                    'report_label' => self::SUBJECTS[$code][1],
                    'is_compulsory' => true,
                    'elective_slot' => null,
                    'credit_hours' => null,
                    'grading_mode' => 'score',
                    'counts_in_result' => true,
                    'sort_order' => ++$order,
                    'pending_question' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $overrides);
            }
        }

        DB::table('subject_offerings')->insert($rows);
    }

    /**
     * Removes the curriculum rows and the school-wide subjects this migration created. Refuses,
     * before deleting anything, if teachers, gradebooks or lesson plans already use one of those
     * subjects. Offerings and elective choices go too: the rest of this rollback drops their tables.
     */
    public function down(): void
    {
        $created = DB::table('subjects')->whereIn('code', array_keys(self::SUBJECTS))->whereNull('department_id')->pluck('id');

        $inUse = DB::table('teaching_assignments')->whereIn('subject_id', $created)->exists()
            || DB::table('assessment_categories')->whereIn('subject_id', $created)->exists()
            || DB::table('lesson_plans')->whereIn('subject_id', $created)->exists();
        if ($inUse) {
            throw new RuntimeException('The curriculum subjects already have teaching assignments, gradebook data or lesson plans; not rolling back.');
        }

        DB::table('student_electives')->delete();
        DB::table('subject_offerings')->delete();
        DB::table('subjects')->whereIn('id', $created)->delete();
    }
};
