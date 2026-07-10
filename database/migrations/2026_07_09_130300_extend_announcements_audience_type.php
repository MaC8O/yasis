<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE announcements MODIFY COLUMN audience_type ENUM('School','Department','Section','Staff','Guardians','Students') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE announcements MODIFY COLUMN audience_type ENUM('School','Department','Section') NOT NULL");
    }
};
