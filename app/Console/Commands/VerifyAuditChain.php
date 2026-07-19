<?php

namespace App\Console\Commands;

use App\Services\AuditIntegrityService;
use Illuminate\Console\Command;

class VerifyAuditChain extends Command
{
    protected $signature = 'audit:verify';

    protected $description = 'Verify the tamper-evidence hash chain of the audit log';

    public function handle(AuditIntegrityService $integrity): int
    {
        $result = $integrity->verify();

        if ($result['ok']) {
            $this->info("Audit chain intact — {$result['checked']} hash-protected record(s) verified.");

            return self::SUCCESS;
        }

        $this->error("Audit chain BROKEN: {$result['reason']}");
        $this->line("Verified {$result['checked']} record(s) before the break (at row #{$result['brokenAtId']}).");

        return self::FAILURE;
    }
}
