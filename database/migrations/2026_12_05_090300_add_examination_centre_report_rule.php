<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * §7.2, Q15: the examination centre printed on the Statement of Marks is a report rule the Admin
 * edits. The rule values grow to 120 characters to hold the name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_rules', function (Blueprint $table) {
            $table->string('value', 120)->change();
        });

        $now = now();
        DB::table('report_rules')->insert([
            'key' => 'examination_centre', 'value' => 'YANGON ADVENTIST SEMINARY INTERNATIONAL SCHOOL',
            'pending_question' => 'Q15', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('statements_of_marks') && DB::table('statements_of_marks')->exists()) {
            throw new RuntimeException('Statements of Marks exist; they would be lost. Roll back is refused.');
        }
        DB::table('report_rules')->where('key', 'examination_centre')->delete();

        Schema::table('report_rules', function (Blueprint $table) {
            $table->string('value', 40)->change();
        });
    }
};
