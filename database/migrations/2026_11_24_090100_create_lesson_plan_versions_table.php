<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One uploaded file of a lesson plan. Kept for as long as the plan exists. The file is on the
 * private `lesson_plans` disk (outside the web root) under a random name; original_name is only
 * used for the download's file name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_plan_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('stored_path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->constrained('staff_profiles')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['lesson_plan_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_plan_versions');
    }
};
