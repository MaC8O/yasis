<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The VP's review history: one row per decision, tied to the exact version that was reviewed.
 * The plan FK is restrict, so the database refuses to delete a reviewed plan as well as the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_plan_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('lesson_plan_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('staff_profiles')->restrictOnDelete();
            $table->enum('decision', ['Approved', 'NeedsChanges']);
            $table->string('comment', 1000)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('lesson_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_plan_reviews');
    }
};
