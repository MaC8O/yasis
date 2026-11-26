<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The bands of a grading scheme, in two sets: `mark` (a subject's mark gives a letter and,
     * for Grade 10 and 11-12, points or a grade value) and `result` (the average, point total or
     * GPA gives the class of pass). Ranges are inclusive, as the school prints them.
     *
     * A set is saved as a whole: the old rows get `archived_at` and the new rows are inserted with
     * `created_at` at that same moment, so the set in force at any time T is the rows with
     * created_at <= T and (archived_at is null or archived_at > T). Nothing is deleted.
     */
    public function up(): void
    {
        Schema::create('grading_scheme_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_scheme_id')->constrained('grading_schemes')->restrictOnDelete();
            $table->string('kind', 10);
            $table->string('label', 20);
            $table->decimal('min', 5, 2);
            $table->decimal('max', 5, 2);
            $table->decimal('value', 4, 2)->nullable();
            $table->boolean('is_fail')->default(false);
            $table->string('pending_question', 10)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['grading_scheme_id', 'kind', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_scheme_bands');
    }
};
