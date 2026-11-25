<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A student's chosen subject in each elective slot, per academic year (Grades 11-12, §3). Set
     * by the Registrar. One choice per slot (Q7 default: each slot is chosen separately); a choice
     * can be changed but not cleared.
     */
    public function up(): void
    {
        Schema::create('student_electives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('elective_slot', 10);
            $table->foreignId('subject_offering_id')->constrained('subject_offerings')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id', 'elective_slot'], 'student_electives_one_per_slot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_electives');
    }
};
