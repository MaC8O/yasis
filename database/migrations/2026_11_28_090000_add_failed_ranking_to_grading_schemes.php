<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Q20: how failed students are ranked for their position in class. `together` (the default,
     * today's behaviour) ranks everyone by their result; `failed_last` puts every student who
     * failed after all who passed. Every existing scheme gets `together` and the Q20 flag, which the
     * Admin clears with "Confirmed by the school". `pending_question` is widened from 10 to 20
     * characters to hold "Q10,Q19,Q20".
     */
    public function up(): void
    {
        Schema::table('grading_schemes', function (Blueprint $table) {
            $table->string('failed_ranking', 20)->default('together')->after('subject_fail_rule');
            $table->string('pending_question', 20)->nullable()->change();
        });

        foreach (DB::table('grading_schemes')->get(['id', 'pending_question']) as $scheme) {
            $flags = array_filter(array_map('trim', explode(',', (string) $scheme->pending_question)));
            if (! in_array('Q20', $flags, true)) {
                $flags[] = 'Q20';
            }
            DB::table('grading_schemes')->where('id', $scheme->id)->update(['pending_question' => implode(',', $flags)]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('grading_schemes')->get(['id', 'pending_question']) as $scheme) {
            $flags = array_values(array_filter(array_map('trim', explode(',', (string) $scheme->pending_question)), fn ($q) => $q !== '' && $q !== 'Q20'));
            DB::table('grading_schemes')->where('id', $scheme->id)->update(['pending_question' => $flags ? implode(',', $flags) : null]);
        }

        Schema::table('grading_schemes', function (Blueprint $table) {
            $table->dropColumn('failed_ranking');
            $table->string('pending_question', 10)->nullable()->change();
        });
    }
};
