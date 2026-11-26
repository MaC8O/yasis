<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ResultFreezeService;
use Illuminate\Console\Command;

/**
 * Freezes the results of terms that were locked before frozen results existed (for example a
 * staging term locked by the seeder). Run once after deploying phase 4a; safe to run again.
 * Audited as the given Principal or Admin.
 */
class FreezeLockedTerms extends Command
{
    protected $signature = 'results:freeze-locked-terms
        {--actor= : Email of the Principal or Admin the freeze is recorded under}';

    protected $description = 'Store the results of every locked term that has no frozen results yet';

    public function handle(ResultFreezeService $freezes): int
    {
        $actor = User::where('email', (string) $this->option('actor'))->first();
        if (! $actor || ! $actor->hasAnyRole(['principal', 'admin'])) {
            $this->error('Give --actor the email of a Principal or Admin account; the freeze is audited under it.');

            return self::FAILURE;
        }

        $count = $freezes->freezeLockedTerms($actor);
        $this->info($count === 0 ? 'Nothing to freeze: every locked term already has frozen results.' : "Froze {$count} ".($count === 1 ? 'term' : 'terms').'.');

        return self::SUCCESS;
    }
}
