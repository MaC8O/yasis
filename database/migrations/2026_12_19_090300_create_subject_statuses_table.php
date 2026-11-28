<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A word instead of a mark for a subject in the `completion` grading mode (SUPW, Grades 5-12):
     * Pass, Incomplete or Complete per student, subject and term, entered by the subject's teacher.
     * Complete counts as Pass; there is no Fail.
     */
    public function up(): void
    {
        Schema::create('subject_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['Pass', 'Incomplete', 'Complete']);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'subject_id', 'term_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('subject_statuses')->exists()) {
            throw new RuntimeException('Not rolled back: SUPW words have been entered, and rolling back would lose them.');
        }
        Schema::dropIfExists('subject_statuses');
    }
};
