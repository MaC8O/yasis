<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The report card's personality record (§7.1): the homeroom teacher rates each of five traits
     * A, B or C for each student each term (Q14). One row per student, term and trait; editable,
     * audited, until the term is locked.
     */
    public function up(): void
    {
        Schema::create('personality_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('term_id')->constrained('terms')->restrictOnDelete();
            $table->string('trait', 30);
            $table->char('rating', 1);
            $table->foreignId('rated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'term_id', 'trait']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personality_ratings');
    }
};
