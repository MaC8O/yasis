<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->string('job_title', 60)->nullable()->after('role_type');
        });

        // 'Staff' is a generic catch-all RBAC category for non-portal/auxiliary employees
        // (receptionist, maintenance, canteen, transportation, IT & Media assistant, etc.)
        // who are not ISMS users — job_title carries their real job label.
        DB::statement("ALTER TABLE staff_profiles MODIFY COLUMN role_type ENUM('Admin','Principal','VP_Academic','Registrar','Teacher','Treasurer','HR_Office','Staff') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE staff_profiles MODIFY COLUMN role_type ENUM('Admin','Principal','VP_Academic','Registrar','Teacher','Treasurer','HR_Office') NOT NULL");

        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropColumn('job_title');
        });
    }
};
