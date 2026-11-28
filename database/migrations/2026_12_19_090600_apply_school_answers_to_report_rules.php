<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The school's answers on the report card rules:
     * - Personality traits are rated once a year and printed on the final report only (Q14).
     * - The examination centre "Yangon Adventist Seminary International School" is confirmed (Q15).
     * - New: whether SUPW Incomplete stops the promotion recommendation. Default no, flagged FQ3
     *   (follow-up question 3); it is shown beside the recommendation either way.
     */
    public function up(): void
    {
        $now = now();
        DB::table('report_rules')->where('key', 'personality_shown')->update(['value' => 'final_report', 'pending_question' => null, 'updated_at' => $now]);
        DB::table('report_rules')->where('key', 'examination_centre')->update(['pending_question' => null, 'updated_at' => $now]);
        DB::table('report_rules')->insert([
            'key' => 'supw_incomplete_blocks_promotion', 'value' => 'no', 'pending_question' => 'FQ3', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $now = now();
        DB::table('report_rules')->where('key', 'supw_incomplete_blocks_promotion')->delete();
        DB::table('report_rules')->where('key', 'examination_centre')->update(['pending_question' => 'Q15', 'updated_at' => $now]);
        DB::table('report_rules')->where('key', 'personality_shown')->update(['value' => 'latest_term', 'pending_question' => 'Q14', 'updated_at' => $now]);
    }
};
