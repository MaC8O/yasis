<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * A request that is within the actor's authority but breaks a business rule (a report that was
 * already reviewed, restoring more points than were deducted, deciding out of turn). Nothing is
 * changed; the person sees why as a warning on the page they came from. Out-of-scope requests are
 * authorization failures (403) instead.
 *
 * Not reported to the error log: it is an expected answer, not a fault, and logging it would
 * bury real errors.
 */
class ActionRefused extends RuntimeException implements ShouldntReport
{
    public function render(): RedirectResponse
    {
        return back()->withInput()->with('warning', $this->getMessage());
    }
}
