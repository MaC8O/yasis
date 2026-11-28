<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The Sun ledger import sets a 120-second limit for web requests; in a test run that limit
        // would carry over to the rest of the PHPUnit process, so each test starts without one.
        set_time_limit(0);
    }
}
