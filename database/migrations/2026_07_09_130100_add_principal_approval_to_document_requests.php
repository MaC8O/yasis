<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->foreignId('principal_approved_by')->nullable()->after('approved_at')->constrained('staff_profiles')->nullOnDelete();
            $table->dateTime('principal_approved_at')->nullable()->after('principal_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropForeign(['principal_approved_by']);
            $table->dropColumn(['principal_approved_by', 'principal_approved_at']);
        });
    }
};
