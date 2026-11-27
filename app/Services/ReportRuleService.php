<?php

namespace App\Services;

use App\Exceptions\ActionRefused;
use App\Models\ReportRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** The Admin edits the report card and attendance rules; every change is audited with before and after. */
class ReportRuleService
{
    public function __construct(private AuditService $audit) {}

    /** @param  array<string, string>  $values  key => value; $confirmed lists keys the school has confirmed */
    public function update(array $values, array $confirmed, User $actor): void
    {
        DB::transaction(function () use ($values, $confirmed, $actor) {
            $changes = [];
            foreach (ReportRule::lockForUpdate()->get() as $rule) {
                if (array_key_exists($rule->key, $values)) {
                    $rule->value = $values[$rule->key];
                }
                if (in_array($rule->key, $confirmed, true)) {
                    $rule->pending_question = null;
                }
                if ($rule->isDirty()) {
                    $changes[$rule->key] = collect($rule->getDirty())->map(fn ($to, $f) => ['from' => $rule->getOriginal($f), 'to' => $to])->all();
                    $rule->save();
                }
            }
            if ($changes === []) {
                throw new ActionRefused('Nothing was changed.');
            }
            $this->audit->log($actor, 'Updated report card rules', 'ReportRule', null, ['changes' => $changes]);
        });
    }
}
