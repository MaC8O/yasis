<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A student's track for one school year (Grade 10 and Grades 11-12): science or arts, set by the
     * Registrar. `set_by` is null only for a track moved from the student's elective choices.
     */
    public function up(): void
    {
        Schema::create('student_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('track', 20);
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('student_tracks')->whereNotNull('set_by')->exists()) {
            throw new RuntimeException('Not rolled back: the Registrar has set students\' tracks, and rolling back would lose them.');
        }
        Schema::dropIfExists('student_tracks');
    }
};
