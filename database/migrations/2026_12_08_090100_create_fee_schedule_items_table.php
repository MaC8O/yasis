<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §10: a schedule's amounts, one row per grade level and fee type: the yearly fee (one-time),
 * tuition (the year's amount, in instalments) and the refundable deposit (one-time, new students
 * only). Rows are never deleted; a fee that isn't charged is 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_schedule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_schedule_id')->constrained('fee_schedules')->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->restrictOnDelete();
            $table->string('fee_type', 10);
            $table->decimal('amount', 12, 2);
            $table->unsignedTinyInteger('instalments')->default(1);
            $table->boolean('new_students_only')->default(false);
            $table->string('pending_question', 20)->nullable();
            $table->timestamps();

            $table->unique(['fee_schedule_id', 'grade_level_id', 'fee_type'], 'fee_schedule_items_one_per_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_schedule_items');
    }
};
