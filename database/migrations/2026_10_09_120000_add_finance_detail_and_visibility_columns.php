<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imported_fee_records', function (Blueprint $table) {
            // The student's name as written in the source export — shown beside the ID so the
            // finance office can recognise rows, and compared to the ISMS name to flag conflicts.
            $table->string('raw_student_name', 150)->nullable()->after('raw_student_key');
            // §4 data model: the line's narrative from the ledger (e.g. "Term 1 tuition").
            $table->string('description', 150)->nullable()->after('txn_date');
        });

        Schema::table('import_batches', function (Blueprint $table) {
            // §9.8: the Treasurer (re)confirms each import's restricted (SDA) classification.
            // Cleared whenever a row's restriction changes, and required before publishing.
            $table->dateTime('restricted_confirmed_at')->nullable()->after('published_at');
            $table->foreignId('restricted_confirmed_by')->nullable()->after('restricted_confirmed_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('restricted_confirmed_by');
            $table->dropColumn('restricted_confirmed_at');
        });

        Schema::table('imported_fee_records', function (Blueprint $table) {
            $table->dropColumn(['raw_student_name', 'description']);
        });
    }
};
