<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A freeze: the moment a term's results (or, with no term, the year's) were calculated and
     * stored, because the Principal locked the term. Unlocking sets `lifted_at`; the row and its
     * results stay as history. `rules` says where the grading rules came from: `previous_freeze` (the
     * default when a term is locked again: the rules of its previous freeze) or `current`, which on
     * a relock needs a `reason`.
     */
    public function up(): void
    {
        Schema::create('result_freezes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->restrictOnDelete();
            $table->string('rules', 20)->default('current');
            $table->text('reason')->nullable();
            $table->timestamp('frozen_at');
            $table->foreignId('frozen_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('lifted_at')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['term_id', 'lifted_at']);
            $table->index(['academic_year_id', 'term_id', 'lifted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_freezes');
    }
};
