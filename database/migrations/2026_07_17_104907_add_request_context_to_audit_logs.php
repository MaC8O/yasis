<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Request context for non-repudiation: who, from where, on what device.
            $table->string('ip_address', 45)->nullable()->after('role');   // 45 = max IPv6 length
            $table->string('user_agent', 500)->nullable()->after('ip_address');
            // Structured before/after payload (e.g. per-student grade from→to). Null for
            // actions with no captured detail.
            $table->json('details')->nullable()->after('entity_id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent', 'details']);
        });
    }
};
