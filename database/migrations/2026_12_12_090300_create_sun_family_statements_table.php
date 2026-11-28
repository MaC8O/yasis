<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What families see of the Sun ledger: one statement per account, financial year and published
 * import, built when the import is published. Families always read the statement of the latest
 * published import for that year, so an import that isn't published yet changes nothing for them,
 * and unpublishing or reverting falls back to the previous one. student_id is whoever the code's
 * link covers for that year (null while a possible reused code is under review). The content
 * holds memos: erasure clears them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sun_family_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sun_account_id')->constrained('sun_accounts')->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->restrictOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->foreignId('batch_id')->constrained('sun_import_batches')->restrictOnDelete();
            $table->json('content');
            $table->decimal('closing_balance', 14, 2);
            $table->timestamps();

            $table->unique(['sun_account_id', 'fiscal_year', 'batch_id'], 'sun_family_statements_key');
            $table->index(['student_id', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sun_family_statements');
    }
};
