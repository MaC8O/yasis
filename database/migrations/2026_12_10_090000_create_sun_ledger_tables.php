<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sun Accounts ledger import (sdp_doc/YASIS_Sun_Finance_Import.md, gitignored): new tables beside
 * the CSV import's import_batches / imported_fee_records, which stay untouched (owner, 2026-11-27).
 *
 * - sun_import_batches: one per imported Account Listing file.
 * - sun_accounts: every ledger account code (§8): linked student, control account, "not an ISMS student".
 * - sun_ledger_lines: one row per ledger line; signed DECIMAL(14,2), debit positive.
 * - sun_account_balances: opening and closing balance per account and financial year.
 * - sun_ledger_versions: what each batch created or changed, so a revert restores exactly that.
 *
 * Nothing is hard-deleted: a reverted batch's lines are marked removed_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sun_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('source_file', 255);
            $table->char('file_sha256', 64)->index();
            $table->string('stored_path', 255)->nullable();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('period_from', 7);
            $table->string('period_to', 7);
            $table->string('account_code_from', 20);
            $table->string('account_code_to', 20);
            $table->string('printed_at', 16)->nullable();
            $table->unsignedInteger('accounts_count')->default(0);
            $table->unsignedInteger('lines_count')->default(0);
            $table->unsignedInteger('new_lines')->default(0);
            $table->unsignedInteger('updated_lines')->default(0);
            $table->unsignedInteger('missing_lines')->default(0);
            $table->unsignedInteger('matched_accounts')->default(0);
            $table->unsignedInteger('unmatched_accounts')->default(0);
            $table->unsignedInteger('new_accounts')->default(0);
            $table->json('warnings')->nullable();
            $table->decimal('control_balance', 14, 2)->default(0);
            $table->dateTime('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('reverted_at')->nullable();
            $table->foreignId('reverted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('sun_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('student_id')->nullable()->unique()->constrained('students')->restrictOnDelete();
            $table->boolean('is_control')->default(false);
            $table->boolean('not_isms_student')->default(false);
            $table->string('name_as_printed', 80);
            $table->unsignedSmallInteger('first_seen_year');
            $table->dateTime('linked_at')->nullable();
            $table->foreignId('linked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('sun_ledger_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sun_account_id')->constrained('sun_accounts')->restrictOnDelete();
            $table->string('sun_account_code', 20);
            $table->foreignId('created_batch_id')->constrained('sun_import_batches')->restrictOnDelete();
            $table->foreignId('last_batch_id')->constrained('sun_import_batches')->restrictOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedTinyInteger('period');
            $table->date('period_month');
            $table->date('txn_date')->nullable();
            $table->boolean('date_suspect')->default(false);
            $table->string('journal_type', 4);
            $table->string('reference', 20);
            $table->string('journal_no', 12);
            $table->unsignedInteger('journal_line');
            $table->boolean('allocated')->default(true);
            $table->decimal('amount', 14, 2);
            $table->decimal('running_balance', 14, 2);
            $table->string('memo', 255)->default('');
            $table->string('category', 20);
            $table->boolean('category_overridden')->default(false);
            $table->date('billed_month')->nullable();
            $table->boolean('is_restricted')->default(false);
            $table->boolean('restricted_overridden')->default(false);
            $table->boolean('missing_from_latest')->default(false);
            $table->unsignedInteger('file_line')->nullable();
            $table->dateTime('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['fiscal_year', 'journal_type', 'journal_no', 'journal_line', 'sun_account_code'], 'sun_ledger_lines_key');
            $table->index(['sun_account_id', 'fiscal_year']);
        });

        Schema::create('sun_account_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sun_account_id')->constrained('sun_accounts')->restrictOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('opening_balance', 14, 2);
            $table->decimal('closing_balance', 14, 2);
            $table->string('account_name_as_printed', 80);
            $table->boolean('is_control')->default(false);
            $table->foreignId('created_batch_id')->constrained('sun_import_batches')->restrictOnDelete();
            $table->foreignId('last_batch_id')->constrained('sun_import_batches')->restrictOnDelete();
            $table->dateTime('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['sun_account_id', 'fiscal_year']);
        });

        Schema::create('sun_ledger_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('sun_import_batches')->restrictOnDelete();
            $table->string('record_type', 10); // line | balance
            $table->unsignedBigInteger('record_id');
            $table->json('previous')->nullable(); // null: the batch created the record
            $table->dateTime('reverted_at')->nullable();
            $table->timestamps();

            $table->index(['record_type', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sun_ledger_versions');
        Schema::dropIfExists('sun_account_balances');
        Schema::dropIfExists('sun_ledger_lines');
        Schema::dropIfExists('sun_accounts');
        Schema::dropIfExists('sun_import_batches');
    }
};
