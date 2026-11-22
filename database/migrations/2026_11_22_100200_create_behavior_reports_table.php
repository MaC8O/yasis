<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavior_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('reported_by')->constrained('staff_profiles');
            $table->enum('source', ['Teacher', 'VP']);
            $table->date('incident_date');
            $table->foreignId('behavior_category_id')->constrained('behavior_categories');
            $table->text('description');
            $table->boolean('is_office_referral')->default(false);
            $table->enum('status', ['Submitted', 'Dismissed', 'Deducted', 'Voided'])->default('Submitted');
            $table->foreignId('reviewed_by')->nullable()->constrained('staff_profiles');
            $table->dateTime('reviewed_at')->nullable();
            $table->text('dismiss_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('staff_profiles');
            $table->dateTime('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->dateTime('edited_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'academic_year_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_reports');
    }
};
