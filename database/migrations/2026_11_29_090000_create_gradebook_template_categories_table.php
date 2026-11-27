<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The school's gradebook template (sdp_doc/YASIS_Academic_and_Fee_Rules.md §4.1): the five
     * categories every gradebook uses in the grade levels whose grading scheme uses the template
     * (KG1 to Grade 10). `key` and `source` are fixed; the Admin renames and reweights (the weights
     * total 100). The Attendance category has no items: its score comes from attendance records,
     * a default awaiting the school (Q8).
     */
    public function up(): void
    {
        Schema::create('gradebook_template_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 20)->unique();
            $table->string('name', 80);
            $table->decimal('weight_pct', 5, 2);
            $table->unsignedTinyInteger('sort_order');
            $table->string('source', 20)->default('items');
            $table->string('pending_question', 20)->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('gradebook_template_categories')->insert(array_map(fn ($row) => $row + ['created_at' => $now, 'updated_at' => $now], [
            ['key' => 'quizzes', 'name' => 'Class tests / quizzes', 'weight_pct' => '10.00', 'sort_order' => 1, 'source' => 'items', 'pending_question' => null],
            ['key' => 'attendance', 'name' => 'Attendance', 'weight_pct' => '10.00', 'sort_order' => 2, 'source' => 'attendance', 'pending_question' => 'Q8'],
            ['key' => 'assignments', 'name' => 'Assignments', 'weight_pct' => '10.00', 'sort_order' => 3, 'source' => 'items', 'pending_question' => null],
            ['key' => 'participation', 'name' => 'Participation / obedience / Code of Conduct', 'weight_pct' => '10.00', 'sort_order' => 4, 'source' => 'items', 'pending_question' => null],
            ['key' => 'examinations', 'name' => 'Examinations', 'weight_pct' => '60.00', 'sort_order' => 5, 'source' => 'items', 'pending_question' => null],
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('gradebook_template_categories');
    }
};
