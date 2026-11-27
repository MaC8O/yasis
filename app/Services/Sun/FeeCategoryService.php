<?php

namespace App\Services\Sun;

use App\Exceptions\ActionRefused;
use App\Models\FeeCategory;
use App\Models\FeeCategoryRule;
use App\Models\SunLedgerLine;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * §5: the Treasurer edits the category keyword rules and, per category, whether its lines are
 * hidden from families by default (T4). Rules are switched off, never deleted. Re-applying the
 * rules recategorises every line the Treasurer hasn't overridden. All audited.
 */
class FeeCategoryService
{
    public function __construct(private AuditService $audit) {}

    /**
     * @param  array<int, bool>  $restricted  category id => hidden by default
     * @param  array<int, array{keyword: string, active: bool}>  $rules  rule id => values
     * @param  array{category_id?: int, keyword?: string}  $new
     */
    public function save(array $restricted, array $rules, array $new, User $actor): int
    {
        return DB::transaction(function () use ($restricted, $rules, $new, $actor) {
            $changes = [];
            foreach (FeeCategory::lockForUpdate()->get() as $category) {
                if (array_key_exists($category->id, $restricted) && (bool) $restricted[$category->id] !== $category->restricted_by_default) {
                    $changes[] = ['category' => $category->key, 'hidden_by_default' => ['from' => $category->restricted_by_default, 'to' => (bool) $restricted[$category->id]]];
                    $category->update(['restricted_by_default' => (bool) $restricted[$category->id], 'pending_question' => null]);
                }
            }
            foreach (FeeCategoryRule::with('category')->whereIn('id', array_keys($rules))->lockForUpdate()->get() as $rule) {
                $input = $rules[$rule->id];
                $keyword = $rule->cr_credit ? null : mb_strtolower(trim((string) ($input['keyword'] ?? $rule->keyword)));
                $rule->fill(['keyword' => $keyword, 'active' => (bool) ($input['active'] ?? false)]);
                if ($rule->isDirty()) {
                    $changes[] = ['category' => $rule->category->key, 'rule' => $rule->id,
                        'changes' => collect($rule->getDirty())->map(fn ($to, $f) => ['from' => $rule->getOriginal($f), 'to' => $to])->all()];
                    $rule->save();
                }
            }
            if (! empty($new['category_id']) && trim((string) ($new['keyword'] ?? '')) !== '') {
                $rule = FeeCategoryRule::create(['fee_category_id' => $new['category_id'], 'keyword' => mb_strtolower(trim($new['keyword'])), 'active' => true]);
                $changes[] = ['category' => $rule->category->key, 'added_keyword' => $rule->keyword];
            }
            if ($changes === []) {
                throw new ActionRefused('Nothing was changed.');
            }
            $this->audit->log($actor, 'Updated fee category rules', 'FeeCategory', null, ['changes' => $changes]);

            return count($changes);
        });
    }

    /** Recategorise every current line without a category override; visibility follows unless overridden. */
    public function reapply(User $actor): int
    {
        $categoriser = FeeCategoriser::fromDatabase();
        $changed = 0;
        DB::transaction(function () use ($categoriser, &$changed, $actor) {
            SunLedgerLine::current()->where('category_overridden', false)->orderBy('id')
                ->chunkById(1000, function ($lines) use ($categoriser, &$changed) {
                    foreach ($lines as $line) {
                        $category = $categoriser->classify($line->memo, $line->journal_type, (string) $line->amount);
                        $restricted = $line->restricted_overridden ? $line->is_restricted : $categoriser->restrictedByDefault($category);
                        if ($category !== $line->category || $restricted !== (bool) $line->is_restricted) {
                            DB::table('sun_ledger_lines')->where('id', $line->id)->update(['category' => $category, 'is_restricted' => $restricted, 'updated_at' => now()]);
                            $changed++;
                        }
                    }
                });
            $this->audit->log($actor, 'Re-applied fee category rules', 'FeeCategory', null, ['lines_changed' => $changed]);
        });

        return $changed;
    }
}
