<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The school's three grading schemes (sdp_doc/YASIS_Academic_and_Fee_Rules.md §4-6), seeded
     * here so every environment has them without running a seeder, and assigned to the grade
     * levels by code. A level with another code (one the Admin added) stays unassigned. Defaults
     * awaiting the school are flagged with the rules file's question number: the rounding rule on
     * every scheme (Q10), and Grade 10's handling below 40 marks or 40 points (Q19).
     *
     * Each band: [label, min, max, value, is_fail, pending_question].
     */
    public const SCHEMES = [
        'KG_9' => [
            'name' => 'KG1 to Grade 9',
            'result_basis' => 'average',
            'result_max' => '100.00',
            'mark_decimals' => 0,
            'result_decimals' => 0,
            'subject_fail_rule' => 'any_counted',
            'pending_question' => 'Q10',
            'levels' => ['KG1', 'KG2', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            'mark' => [
                ['PASS', '40', '100', null, false, null],
                ['FAIL', '0', '39', null, true, null],
            ],
            'result' => [
                ['Distinction', '95', '100', null, false, null],
                ['First', '81', '94', null, false, null],
                ['Second', '61', '80', null, false, null],
                ['Third', '40', '60', null, false, null],
                ['Fail', '0', '39', null, true, null],
            ],
        ],
        'G10' => [
            'name' => 'Grade 10',
            'result_basis' => 'point_total',
            'result_max' => '70.00',
            'mark_decimals' => 0,
            'result_decimals' => 0,
            'subject_fail_rule' => 'any_counted',
            'pending_question' => 'Q10,Q19',
            'levels' => ['10'],
            'mark' => [
                ['A', '85', '100', '10', false, null],
                ['B', '76', '84', '9', false, null],
                ['C+', '67', '75', '8', false, null],
                ['C', '58', '66', '7', false, null],
                ['C-', '49', '57', '6', false, null],
                ['D', '40', '48', '5', false, null],
                ['Fail', '0', '39', '0', true, 'Q19'],
            ],
            'result' => [
                ['Distinction', '66', '70', null, false, null],
                ['First', '56', '65', null, false, null],
                ['Second', '46', '55', null, false, null],
                ['Third', '40', '45', null, false, null],
                ['Fail', '0', '39', null, true, 'Q19'],
            ],
        ],
        'G11_12' => [
            'name' => 'Grades 11 and 12',
            'result_basis' => 'gpa',
            'result_max' => '4.00',
            'mark_decimals' => 0,
            'result_decimals' => 2,
            'subject_fail_rule' => 'any_compulsory',
            'pending_question' => 'Q10',
            'levels' => ['11', '12'],
            'mark' => [
                ['A', '90', '100', '4.00', false, null],
                ['A-', '85', '89', '3.67', false, null],
                ['B+', '80', '84', '3.33', false, null],
                ['B', '75', '79', '3.00', false, null],
                ['B-', '70', '74', '2.67', false, null],
                ['C+', '65', '69', '2.33', false, null],
                ['C', '60', '64', '2.00', false, null],
                ['C-', '55', '59', '1.67', false, null],
                ['D+', '50', '54', '1.33', false, null],
                ['D', '40', '49', '1.00', false, null],
                ['F', '0', '39', '0.00', true, null],
            ],
            'result' => [
                ['Distinction', '3.68', '4.00', null, false, null],
                ['First', '3.33', '3.67', null, false, null],
                ['Second', '2.68', '3.32', null, false, null],
                ['Third', '2.00', '2.67', null, false, null],
                ['Fail', '0.00', '1.99', null, true, null],
            ],
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::SCHEMES as $code => $scheme) {
            $schemeId = DB::table('grading_schemes')->insertGetId([
                'code' => $code,
                'name' => $scheme['name'],
                'result_basis' => $scheme['result_basis'],
                'result_max' => $scheme['result_max'],
                'mark_decimals' => $scheme['mark_decimals'],
                'result_decimals' => $scheme['result_decimals'],
                'rounding_mode' => 'half_up',
                'subject_fail_rule' => $scheme['subject_fail_rule'],
                'pending_question' => $scheme['pending_question'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $bands = [];
            foreach (['mark', 'result'] as $kind) {
                foreach ($scheme[$kind] as [$label, $min, $max, $value, $isFail, $pending]) {
                    $bands[] = [
                        'grading_scheme_id' => $schemeId,
                        'kind' => $kind,
                        'label' => $label,
                        'min' => $min,
                        'max' => $max,
                        'value' => $value,
                        'is_fail' => $isFail,
                        'pending_question' => $pending,
                        'archived_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            DB::table('grading_scheme_bands')->insert($bands);

            DB::table('grade_levels')->whereIn('code', $scheme['levels'])
                ->update(['grading_scheme_id' => $schemeId, 'updated_at' => $now]);
        }
    }

    /** Clears the grade levels' assignments, then removes these schemes and all their bands. */
    public function down(): void
    {
        $ids = DB::table('grading_schemes')->whereIn('code', array_keys(self::SCHEMES))->pluck('id');

        DB::table('grade_levels')->whereIn('grading_scheme_id', $ids)->update(['grading_scheme_id' => null]);
        DB::table('grading_scheme_bands')->whereIn('grading_scheme_id', $ids)->delete();
        DB::table('grading_schemes')->whereIn('id', $ids)->delete();
    }
};
