<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * §7.2: which Statement of Marks layout each grade level uses: `kg9` (KG1 to Grade 9) or `g11_12`.
 * Grade 10 has none until the school answers Q11; levels without a layout get no statement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_levels', function (Blueprint $table) {
            $table->string('statement_layout', 10)->nullable()->after('report_card_layout');
        });

        DB::table('grade_levels')->whereIn('code', ['KG1', 'KG2', '1', '2', '3', '4', '5', '6', '7', '8', '9'])->update(['statement_layout' => 'kg9']);
        DB::table('grade_levels')->whereIn('code', ['11', '12'])->update(['statement_layout' => 'g11_12']);
    }

    public function down(): void
    {
        if (Schema::hasTable('statements_of_marks') && DB::table('statements_of_marks')->exists()) {
            throw new RuntimeException('Statements of Marks exist; they would be lost. Roll back is refused.');
        }
        Schema::table('grade_levels', function (Blueprint $table) {
            $table->dropColumn('statement_layout');
        });
    }
};
