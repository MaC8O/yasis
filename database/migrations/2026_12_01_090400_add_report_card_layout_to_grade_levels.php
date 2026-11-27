<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which of the school's four report cards a grade level prints (§7.1): KG to Grade 4 (blue),
     * Grades 5-9 (green), Grade 10 (yellow), Grades 11-12 (yellow). Set by code; a level the Admin
     * adds later has none until set.
     */
    public function up(): void
    {
        Schema::table('grade_levels', function (Blueprint $table) {
            $table->string('report_card_layout', 20)->nullable()->after('grading_scheme_id');
        });

        $layouts = [
            'kg4' => ['KG1', 'KG2', '1', '2', '3', '4'],
            'g5_9' => ['5', '6', '7', '8', '9'],
            'g10' => ['10'],
            'g11_12' => ['11', '12'],
        ];
        foreach ($layouts as $layout => $codes) {
            DB::table('grade_levels')->whereIn('code', $codes)->update(['report_card_layout' => $layout]);
        }
    }

    public function down(): void
    {
        Schema::table('grade_levels', function (Blueprint $table) {
            $table->dropColumn('report_card_layout');
        });
    }
};
