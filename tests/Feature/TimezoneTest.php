<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimezoneTest extends TestCase
{
    public function test_app_runs_on_yangon_time(): void
    {
        $this->assertSame('Asia/Yangon', config('app.timezone'));
        $this->assertSame('Asia/Yangon', date_default_timezone_get());
        $this->assertSame('+06:30', now()->format('P'));
    }

    public function test_database_clock_matches_app_clock(): void
    {
        // CURRENT_TIMESTAMP column defaults must land in the same zone Laravel writes in.
        $dbNow = DB::selectOne('select now() as n')->n;

        $this->assertLessThan(5, abs(now()->diffInSeconds($dbNow)));
    }
}
