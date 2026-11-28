<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T3: a linked code printed under a different name may have been given to another student. The
 * import opens a "possible reused code" review for that year; while it is pending, the account's
 * lines for the year match nobody. The Treasurer resolves it as the same student (a rename: the
 * new name becomes the accepted one) or a different student (the old link ends, the new student
 * is linked from that year). previous_name and printed_name are real names: erasure clears them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sun_account_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sun_account_id')->constrained('sun_accounts')->restrictOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('previous_name', 80);
            $table->string('printed_name', 80);
            $table->string('status', 10)->default('pending'); // pending | same | relinked
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['sun_account_id', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sun_account_reviews');
    }
};
