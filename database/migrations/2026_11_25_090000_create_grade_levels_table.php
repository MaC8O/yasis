<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grade levels as data (sdp_doc/YASIS_Academic_and_Fee_Rules.md §1). Grading schemes, the
     * curriculum, report card templates and fee bands are looked up by grade level, not by
     * department. The 14 school levels are inserted here so every environment has them
     * without running a seeder. `code` is the fixed key later phases use; `name` and
     * `sort_order` are the Admin's to edit. Retired levels keep their row (`archived_at`).
     */
    public function up(): void
    {
        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->smallInteger('sort_order')->unique();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        $levels = [['KG1', 'Kindergarten 1'], ['KG2', 'Kindergarten 2']];
        for ($grade = 1; $grade <= 12; $grade++) {
            $levels[] = [(string) $grade, "Grade {$grade}"];
        }

        $now = now();
        DB::table('grade_levels')->insert(array_map(fn ($level, $i) => [
            'code' => $level[0],
            'name' => $level[1],
            'sort_order' => $i + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $levels, array_keys($levels)));
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_levels');
    }
};
