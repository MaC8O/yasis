<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T4, T5 (the Treasurer, 2026-11-27): families see every transaction, allowances included. The
 * allowance category is no longer hidden by default, allowance lines not hidden by hand become
 * visible, and the netting setting (T5) goes. Lines the Treasurer hid by hand keep their setting.
 * Rolling back restores all three.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('fee_categories')->where('key', 'allowance')->update(['restricted_by_default' => false, 'pending_question' => null, 'updated_at' => now()]);
        DB::table('sun_ledger_lines')->where('category', 'allowance')->where('restricted_overridden', false)->update(['is_restricted' => false]);
        DB::table('system_settings')->where('key', 'fees.family_nets_allowances')->delete();
    }

    public function down(): void
    {
        DB::table('fee_categories')->where('key', 'allowance')->update(['restricted_by_default' => true, 'pending_question' => 'T4', 'updated_at' => now()]);
        DB::table('sun_ledger_lines')->where('category', 'allowance')->where('restricted_overridden', false)->update(['is_restricted' => true]);
        if (! DB::table('system_settings')->where('key', 'fees.family_nets_allowances')->exists()) {
            DB::table('system_settings')->insert(['key' => 'fees.family_nets_allowances', 'value' => '1', 'created_at' => now(), 'updated_at' => now()]);
        }
    }
};
