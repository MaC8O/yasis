<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Frozen results keep the rules they were frozen with: a freeze now stores the curriculum it
     * used (grade level id => its rows), so a re-freeze after a grade change, or a relock with the
     * previous rules, doesn't pick up later curriculum changes. Existing freezes get theirs from
     * their own frozen subject rows, which hold a copy of each curriculum row.
     */
    public function up(): void
    {
        Schema::table('result_freezes', function (Blueprint $table) {
            $table->json('curriculum')->nullable()->after('reason');
        });

        foreach (DB::table('result_freezes')->pluck('id') as $freezeId) {
            $rows = DB::table('student_subject_results as s')->join('student_results as r', 'r.id', '=', 's.student_result_id')
                ->where('r.result_freeze_id', $freezeId)->where('s.in_curriculum', true)->whereNotNull('s.subject_offering_id')
                ->whereNotNull('r.grade_level_id')->orderBy('s.sort_order')
                ->get(['r.grade_level_id', 's.subject_offering_id', 's.subject_id', 's.report_label', 's.sort_order', 's.is_compulsory',
                    's.elective_slot', 's.credit_hours', 's.grading_mode', 's.counts_in_result', 's.pending_question']);
            $curriculum = [];
            foreach ($rows->groupBy('grade_level_id') as $levelId => $levelRows) {
                $curriculum[$levelId] = $levelRows->unique('subject_offering_id')->map(fn ($s) => [
                    'id' => (int) $s->subject_offering_id, 'subject_id' => (int) $s->subject_id, 'report_label' => $s->report_label,
                    'sort_order' => (int) $s->sort_order, 'is_compulsory' => (bool) $s->is_compulsory, 'elective_slot' => $s->elective_slot,
                    'track' => null, 'credit_hours' => $s->credit_hours === null ? null : (string) $s->credit_hours,
                    'grading_mode' => $s->grading_mode ?? 'score', 'counts_in_result' => (bool) $s->counts_in_result,
                    'pending_question' => $s->pending_question,
                ])->values()->all();
            }
            if ($curriculum !== []) {
                DB::table('result_freezes')->where('id', $freezeId)->update(['curriculum' => json_encode($curriculum)]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('result_freezes', function (Blueprint $table) {
            $table->dropColumn('curriculum');
        });
    }
};
