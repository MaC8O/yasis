<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Tamper-evidence: each row's HMAC over its own content chained to the previous
            // row's hash. Nullable so rows written before this migration (which predate the
            // chain) remain valid — verification treats the chain as starting at the first
            // hashed row. No positional after() so this is independent of the enrichment
            // migration's columns (merge order doesn't matter).
            $table->char('prev_hash', 64)->nullable();
            $table->char('hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['prev_hash', 'hash']);
        });
    }
};
