<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Sun ledger import's settings, defaults awaiting the Treasurer (sdp_doc/YASIS_Sun_Finance_Import.md §13):
 * - T2: the calendar month of accounting period 01 (April).
 * - T7: the Student Control account code.
 * - T5: hidden allowances are netted into the charge they belong to in the family view (used in 8b-2).
 * An existing value is never overwritten; rolling back removes only rows still at their default.
 */
return new class extends Migration
{
    private const SETTINGS = [
        'fees.sun_period_one_month' => '4',
        'fees.sun_control_account' => '1000285005',
        'fees.family_nets_allowances' => '1',
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::SETTINGS as $key => $value) {
            if (! DB::table('system_settings')->where('key', $key)->exists()) {
                DB::table('system_settings')->insert(['key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::SETTINGS as $key => $value) {
            DB::table('system_settings')->where('key', $key)->where('value', $value)->delete();
        }
    }
};
