<?php

namespace App\Services;

use App\Exceptions\ActionRefused;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\FeeScheduleItem;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * §10: the fee schedule, reference data only (the ISMS never records payments). The Treasurer
 * edits amounts per school year, every change audited (with a reason once families can see it),
 * and publishes it; families see the published schedule for their child's grade. Amounts are
 * whole kyats; the last instalment takes any remainder.
 */
class FeeScheduleService
{
    public function __construct(private AuditService $audit) {}

    public function forYear(AcademicYear $year): ?FeeSchedule
    {
        return FeeSchedule::with(['items.gradeLevel', 'publishedBy'])->where('academic_year_id', $year->id)->first();
    }

    /**
     * One grade level's fees for a year, by type: amount, instalments and each instalment's
     * amount. Null when there is no schedule (or, with $publishedOnly, no published one) for the
     * year, or the level has no fees in it. Used by the family view and the import check (8b).
     *
     * @return array<string, array{label: string, amount: string, instalments: int, per_instalment: list<string>, new_students_only: bool, pending: ?string}>|null
     */
    public function forLevel(?GradeLevel $level, AcademicYear $year, bool $publishedOnly = false): ?array
    {
        if (! $level) {
            return null;
        }
        $schedule = FeeSchedule::where('academic_year_id', $year->id)->when($publishedOnly, fn ($q) => $q->whereNotNull('published_at'))->first();
        $items = $schedule?->items()->where('grade_level_id', $level->id)->get()->keyBy('fee_type');
        if (! $items || $items->isEmpty()) {
            return null;
        }

        return collect(FeeScheduleItem::TYPES)->filter(fn ($label, $type) => $items->has($type))
            ->map(fn ($label, $type) => $this->line($items[$type]))->all();
    }

    /**
     * The family's card: this school year's published fees for the child's grade, or null (no
     * enrollment this year, a level without fees, or no published schedule). No question flags.
     *
     * @return array{year: string, grade: string, lines: array}|null
     */
    public function familyCard(Student $student): ?array
    {
        $year = AcademicYear::where('is_active', true)->first();
        $level = $year ? Enrollment::with('section.gradeLevel')->where('student_id', $student->id)->where('status', 'Active')
            ->whereHas('section', fn ($q) => $q->where('academic_year_id', $year->id))->latest('id')->first()?->section?->gradeLevel : null;
        $lines = $year ? $this->forLevel($level, $year, publishedOnly: true) : null;

        return $lines ? ['year' => $year->year_label, 'grade' => $level->name, 'lines' => $lines] : null;
    }

    /** A schedule row as shown: the amount in whole kyats and its instalments. */
    public function line(FeeScheduleItem $item): array
    {
        $amount = $this->kyats((string) $item->amount);

        return [
            'label' => FeeScheduleItem::TYPES[$item->fee_type] ?? $item->fee_type,
            'amount' => $amount,
            'instalments' => (int) $item->instalments,
            'per_instalment' => $this->instalments($amount, (int) $item->instalments),
            'new_students_only' => (bool) $item->new_students_only,
            'pending' => $item->pending_question,
        ];
    }

    /**
     * Split an amount into whole-kyat instalments: each is the amount divided by the count,
     * rounded down, and the last takes the remainder (2,550,001 in 4 is 637,500 × 3 and 637,501).
     *
     * @return list<string>
     */
    public function instalments(string $amount, int $count): array
    {
        $total = BigDecimal::of($amount)->toScale(0, RoundingMode::DOWN);
        $count = max(1, $count);
        $each = $total->dividedBy($count, 0, RoundingMode::DOWN);
        $last = $total->minus($each->multipliedBy($count - 1));

        return [...array_fill(0, $count - 1, (string) $each), (string) $last];
    }

    /**
     * The schedule as on the school's sheet: for each fee type, runs of consecutive grade levels
     * with the same amount, e.g. ["KG1 to Grade 6", "250000"].
     *
     * @return array<string, list<array{levels: string, amount: string, instalments: int}>>
     */
    public function summary(FeeSchedule $schedule): array
    {
        $items = $schedule->items->sortBy(fn ($i) => $i->gradeLevel->sort_order);
        $out = [];
        foreach (array_keys(FeeScheduleItem::TYPES) as $type) {
            $runs = [];
            foreach ($items->where('fee_type', $type) as $item) {
                $amount = $this->kyats((string) $item->amount);
                $last = array_key_last($runs);
                if ($last !== null && $runs[$last]['amount'] === $amount && $runs[$last]['instalments'] === (int) $item->instalments) {
                    $runs[$last]['to'] = $item->gradeLevel->name;
                } else {
                    $runs[] = ['from' => $item->gradeLevel->name, 'to' => null, 'amount' => $amount, 'instalments' => (int) $item->instalments];
                }
            }
            $out[$type] = array_map(fn ($r) => [
                'levels' => $r['to'] ? "{$r['from']} to {$r['to']}" : $r['from'],
                'amount' => $r['amount'], 'instalments' => $r['instalments'],
            ], $runs);
        }

        return $out;
    }

    /**
     * Save the Treasurer's edits: $values item id => [amount, instalments], $confirmed the question
     * numbers the school has confirmed (e.g. ['Q18']), which are cleared from every row. Once the schedule is published a reason is
     * required; it goes into the audit entry with each change's before and after values.
     */
    public function save(FeeSchedule $schedule, array $values, array $confirmed, ?string $reason, User $actor): int
    {
        return DB::transaction(function () use ($schedule, $values, $confirmed, $reason, $actor) {
            $schedule = FeeSchedule::with('academicYear')->lockForUpdate()->findOrFail($schedule->id);
            $reason = trim((string) $reason);
            if ($schedule->isPublished() && $reason === '') {
                throw new ActionRefused('Families can see this schedule, so give a short reason for the change.');
            }

            $changes = [];
            foreach ($schedule->items()->with('gradeLevel')->lockForUpdate()->get() as $item) {
                $input = $values[$item->id] ?? null;
                if ($input !== null) {
                    if (array_key_exists('amount', $input) && BigDecimal::of((string) $input['amount'])->compareTo((string) $item->amount) !== 0) {
                        $item->amount = (string) $input['amount'];
                    }
                    if ($item->fee_type === 'tuition' && isset($input['instalments'])) {
                        $item->instalments = (int) $input['instalments'];
                    }
                }
                if ($item->pending_question && $confirmed !== []) {
                    $left = array_diff(array_map('trim', explode(',', $item->pending_question)), $confirmed);
                    $item->pending_question = $left === [] ? null : implode(', ', $left);
                }
                if ($item->isDirty()) {
                    $changes[] = [
                        'grade' => $item->gradeLevel->name, 'fee' => FeeScheduleItem::TYPES[$item->fee_type],
                        'changes' => collect($item->getDirty())->map(fn ($to, $field) => [
                            'from' => $field === 'amount' ? $this->kyats((string) $item->getOriginal($field)) : $item->getOriginal($field),
                            'to' => $field === 'amount' ? $this->kyats((string) $to) : $to,
                        ])->all(),
                    ];
                    $item->save();
                }
            }
            if ($changes === []) {
                throw new ActionRefused('Nothing was changed.');
            }

            $this->audit->log($actor, 'Updated fee schedule', 'FeeSchedule', $schedule->id, array_filter([
                'school_year' => $schedule->academicYear->year_label,
                'published' => $schedule->isPublished(),
                'reason' => $reason !== '' ? $reason : null,
                'changes' => $changes,
            ], fn ($v) => $v !== null));

            return count($changes);
        });
    }

    /**
     * Start a year's schedule from the latest earlier one: amounts and instalments copied,
     * unpublished, and no question flags (the school's questions were about that year only). With
     * no earlier schedule, every grade level starts at 0.
     */
    public function start(AcademicYear $year, User $actor): FeeSchedule
    {
        return DB::transaction(function () use ($year, $actor) {
            if (FeeSchedule::where('academic_year_id', $year->id)->lockForUpdate()->exists()) {
                throw new ActionRefused("{$year->year_label} already has a fee schedule.");
            }
            $source = FeeSchedule::with(['items', 'academicYear'])
                ->whereHas('academicYear', fn ($q) => $q->where('year_label', '<', $year->year_label))
                ->get()->sortByDesc(fn ($s) => $s->academicYear->year_label)->first();

            $schedule = FeeSchedule::create(['academic_year_id' => $year->id, 'currency' => $source?->currency ?? 'MMK']);
            $rows = $source
                ? $source->items->map(fn ($i) => [
                    'grade_level_id' => $i->grade_level_id, 'fee_type' => $i->fee_type, 'amount' => $i->amount,
                    'instalments' => $i->instalments, 'new_students_only' => $i->new_students_only,
                ])
                : GradeLevel::active()->ordered()->get()->flatMap(fn ($level) => collect(FeeScheduleItem::TYPES)->keys()->map(fn ($type) => [
                    'grade_level_id' => $level->id, 'fee_type' => $type, 'amount' => 0,
                    'instalments' => $type === 'tuition' ? 4 : 1, 'new_students_only' => $type === 'deposit',
                ]));
            foreach ($rows as $row) {
                $schedule->items()->create($row + ['pending_question' => null]);
            }

            $this->audit->log($actor, 'Started fee schedule', 'FeeSchedule', $schedule->id, [
                'school_year' => $year->year_label, 'copied_from' => $source?->academicYear->year_label,
            ]);

            return $schedule;
        });
    }

    public function publish(FeeSchedule $schedule, bool $publish, User $actor): void
    {
        DB::transaction(function () use ($schedule, $publish, $actor) {
            $schedule = FeeSchedule::with('academicYear')->lockForUpdate()->findOrFail($schedule->id);
            if ($schedule->isPublished() === $publish) {
                throw new ActionRefused($publish ? 'This schedule is already published.' : 'This schedule is not published.');
            }
            $schedule->update($publish ? ['published_at' => now(), 'published_by' => $actor->id] : ['published_at' => null, 'published_by' => null]);
            $this->audit->log($actor, $publish ? 'Published fee schedule' : 'Unpublished fee schedule', 'FeeSchedule', $schedule->id,
                ['school_year' => $schedule->academicYear->year_label]);
        });
    }

    /** "2550000.00" as "2550000": schedule amounts are whole kyats. */
    private function kyats(string $amount): string
    {
        return (string) BigDecimal::of($amount)->toScale(0, RoundingMode::DOWN);
    }

    /** The question numbers still flagged anywhere in a schedule, e.g. ['Q17', 'Q18']. */
    public function pendingQuestions(FeeSchedule $schedule): array
    {
        return $schedule->items->pluck('pending_question')->filter()
            ->flatMap(fn ($q) => array_map('trim', explode(',', $q)))->unique()->sort(SORT_NATURAL)->values()->all();
    }

    /** Grade levels a schedule covers, in order, for the grid. */
    public function levels(FeeSchedule $schedule): Collection
    {
        return $schedule->items->pluck('gradeLevel')->unique('id')->sortBy('sort_order')->values();
    }
}
