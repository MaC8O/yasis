<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            // Pending = awaiting Admin approval (Registrar submissions); Published = live for all users.
            $table->string('status', 20)->default('Published')->after('event_type');
            $table->string('created_by_role', 30)->nullable()->after('created_by');
            $table->foreignId('published_by')->nullable()->after('created_by_role')->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->after('published_by');
        });

        // Existing events (seeded/admin-made) are already live.
        \Illuminate\Support\Facades\DB::table('calendar_events')->update([
            'status' => 'Published',
            'published_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by');
            $table->dropColumn(['status', 'created_by_role', 'published_at']);
        });
    }
};
