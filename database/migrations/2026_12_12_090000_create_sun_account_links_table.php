<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T3 (the Treasurer, 2026-11-27): an account code can be given to a different student after one
 * leaves. A code's link to a student now covers a range of financial years: a line belongs to the
 * student whose link covers its year. At most one open link per code and per student (generated
 * columns with unique indexes); a voided link (a mistake) covers nothing and is kept.
 *
 * Existing links (sun_accounts.student_id) move to open links from the code's first year, and
 * the column goes. Rolling back puts each open link back into the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sun_account_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sun_account_id')->constrained('sun_accounts')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->unsignedSmallInteger('from_fiscal_year');
            $table->unsignedSmallInteger('to_fiscal_year')->nullable();
            $table->string('accepted_name', 80); // the printed name confirmed for this student
            $table->foreignId('linked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('linked_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->timestamps();
            // One open link per code and per student: NULL unless the link is open and not voided.
            $table->unsignedBigInteger('open_account_id')->nullable()->storedAs('IF(to_fiscal_year IS NULL AND voided_at IS NULL, sun_account_id, NULL)');
            $table->unsignedBigInteger('open_student_id')->nullable()->storedAs('IF(to_fiscal_year IS NULL AND voided_at IS NULL, student_id, NULL)');
            $table->unique('open_account_id');
            $table->unique('open_student_id');
            $table->index(['sun_account_id', 'from_fiscal_year']);
        });

        $now = now();
        foreach (DB::table('sun_accounts')->whereNotNull('student_id')->get() as $account) {
            DB::table('sun_account_links')->insert([
                'sun_account_id' => $account->id, 'student_id' => $account->student_id, 'from_fiscal_year' => $account->first_seen_year,
                'accepted_name' => $account->name_as_printed, 'linked_by' => $account->linked_by, 'linked_at' => $account->linked_at ?? $now,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        Schema::table('sun_accounts', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['linked_by']);
            $table->dropUnique(['student_id']);
            $table->dropColumn(['student_id', 'linked_at', 'linked_by']);
        });
    }

    public function down(): void
    {
        Schema::table('sun_accounts', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->unique()->after('code')->constrained('students')->restrictOnDelete();
            $table->dateTime('linked_at')->nullable();
            $table->foreignId('linked_by')->nullable()->constrained('users')->restrictOnDelete();
        });

        foreach (DB::table('sun_account_links')->whereNull('to_fiscal_year')->whereNull('voided_at')->get() as $link) {
            DB::table('sun_accounts')->where('id', $link->sun_account_id)
                ->update(['student_id' => $link->student_id, 'linked_at' => $link->linked_at, 'linked_by' => $link->linked_by]);
        }

        Schema::dropIfExists('sun_account_links');
    }
};
