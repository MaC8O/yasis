<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The school's answers on position in class (Q13) and Grade 10's G.P.A row (Q12):
     * - `tie_ranking`: `dense` (1, 2, 2, 3, the school's answer) or `standard` (1, 2, 2, 4, how
     *   freezes made before this ranked). Copied into each freeze with the scheme's other settings.
     * - `rank_basis`: rank by the result (`result`) or by the average of the counted subjects
     *   (`average`, Grade 10, whose point total still decides its class of pass).
     * - `student_results.average_value`: that average, frozen (Grade 10's G.P.A row).
     * - `student_results.class_pending`: the question a class of pass waits on (Grade 10, Q19)
     *   when the total is shown but the class can't be decided yet.
     */
    public function up(): void
    {
        Schema::table('grading_schemes', function (Blueprint $table) {
            $table->string('tie_ranking', 10)->default('dense')->after('failed_ranking');
            $table->string('rank_basis', 10)->default('result')->after('tie_ranking');
        });
        DB::table('grading_schemes')->where('code', 'G10')->update(['rank_basis' => 'average']);

        Schema::table('student_results', function (Blueprint $table) {
            $table->decimal('average_value', 7, 2)->nullable()->after('result_unrounded');
            $table->string('class_pending', 20)->nullable()->after('class_label');
        });
    }

    public function down(): void
    {
        Schema::table('student_results', function (Blueprint $table) {
            $table->dropColumn(['average_value', 'class_pending']);
        });
        Schema::table('grading_schemes', function (Blueprint $table) {
            $table->dropColumn(['tie_ranking', 'rank_basis']);
        });
    }
};
