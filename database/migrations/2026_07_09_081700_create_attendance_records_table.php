<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms');
            $table->date('attendance_date');
            $table->enum('status', ['Present', 'Absent', 'Tardy', 'Excused']);
            $table->string('remark', 150)->nullable();
            $table->foreignId('absence_notice_id')->nullable()->constrained('absence_notices')->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('staff_profiles');
            $table->timestamps();
            $table->unique(['student_id', 'section_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
