<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The grading rules a freeze was calculated with: a copy of each scheme's settings and both
     * band sets, with numbers kept as exact decimal strings. Later edits to the scheme never reach
     * a freeze.
     */
    public function up(): void
    {
        Schema::create('result_freeze_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_freeze_id')->constrained('result_freezes')->restrictOnDelete();
            $table->foreignId('grading_scheme_id')->constrained('grading_schemes')->restrictOnDelete();
            $table->json('settings');
            $table->json('mark_bands');
            $table->json('result_bands');
            $table->timestamps();

            $table->unique(['result_freeze_id', 'grading_scheme_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_freeze_schemes');
    }
};
