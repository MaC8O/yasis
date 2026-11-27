<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sun ledger uploads waiting between their summary and Import expire after an hour (they may hold
// real names and balances). Also run on every upload, since staging may have no scheduler.
Artisan::command('sun:purge-pending', function (App\Services\Sun\SunLedgerImporter $importer) {
    $this->info($importer->purgeExpired().' expired upload(s) deleted.');
})->purpose('Delete Sun ledger uploads left pending for more than an hour');
Schedule::command('sun:purge-pending')->hourly();
