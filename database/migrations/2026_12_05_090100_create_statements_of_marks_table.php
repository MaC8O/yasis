<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * §7.2: one row per Statement of Marks document request. The Registrar enters the two dates when
 * preparing it; at the first print the statement's content is saved with the year freeze it came
 * from, so reprints are the issued statement word for word.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statements_of_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->unique()->constrained('document_requests')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->restrictOnDelete();
            $table->string('layout', 10);
            $table->string('examination_date', 60);
            $table->date('issue_date');
            // Filled in at the first print (the issue).
            $table->string('examination_centre', 120)->nullable();
            $table->foreignId('result_freeze_id')->nullable()->constrained('result_freezes')->restrictOnDelete();
            $table->json('content')->nullable();
            $table->dateTime('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('statements_of_marks') && DB::table('statements_of_marks')->exists()) {
            throw new RuntimeException('Statements of Marks exist; they would be lost. Roll back is refused.');
        }
        Schema::dropIfExists('statements_of_marks');
    }
};
