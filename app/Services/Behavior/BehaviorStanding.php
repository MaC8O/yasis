<?php

namespace App\Services\Behavior;

use App\Models\AcademicYear;
use App\Models\BehaviorCase;
use App\Models\BehaviorFlag;
use App\Models\BehaviorLedgerEntry;
use App\Models\BehaviorReport;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Read side: a student's score, stage and office visits for a school year. Everything is counted
 * per school year, so activating a new year starts every student at 100 with no reset job.
 * The stage shown is taken from open flags (the record), not recalculated from the thresholds,
 * so editing the thresholds never changes what people see until the student's next entry.
 */
class BehaviorStanding
{
    public const START_SCORE = 100;

    public function activeYear(): ?AcademicYear
    {
        return AcademicYear::where('is_active', true)->first();
    }

    public function activeYearId(): ?int
    {
        return $this->activeYear()?->id;
    }

    public function score(int $studentId, int $yearId): int
    {
        $sums = BehaviorLedgerEntry::where('student_id', $studentId)->where('academic_year_id', $yearId)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'Deduction' THEN points_applied ELSE 0 END), 0) AS d")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'Restoration' THEN points_applied ELSE 0 END), 0) AS r")
            ->first();

        return self::START_SCORE - (int) $sums->d + (int) $sums->r;
    }

    /** @return Collection<int, int> student_id => score, only for students with entries that year */
    public function scoresForYear(int $yearId, ?array $studentIds = null): Collection
    {
        return BehaviorLedgerEntry::where('academic_year_id', $yearId)
            ->when($studentIds !== null, fn ($q) => $q->whereIn('student_id', $studentIds))
            ->groupBy('student_id')
            ->selectRaw('student_id')
            ->selectRaw("SUM(CASE WHEN type = 'Deduction' THEN points_applied ELSE 0 END) AS d")
            ->selectRaw("SUM(CASE WHEN type = 'Restoration' THEN points_applied ELSE 0 END) AS r")
            ->get()
            ->mapWithKeys(fn ($row) => [$row->student_id => self::START_SCORE - (int) $row->d + (int) $row->r]);
    }

    /** Office referrals this year that count: Submitted, Dismissed or Deducted — never Voided. */
    public function officeVisits(int $studentId, int $yearId): int
    {
        return BehaviorReport::where('student_id', $studentId)->where('academic_year_id', $yearId)
            ->where('is_office_referral', true)->whereIn('status', BehaviorReport::COUNTED_STATUSES)->count();
    }

    public function openFlags(int $studentId, int $yearId): Collection
    {
        return BehaviorFlag::open()->where('student_id', $studentId)->where('academic_year_id', $yearId)->get();
    }

    public static function highestStage(iterable $stages): ?string
    {
        return collect($stages)->sortByDesc(fn ($s) => BehaviorFlag::rank($s))->first();
    }

    /**
     * Everything a summary card shows.
     *
     * @return array{year: ?AcademicYear, score: ?int, stage: ?string, stage_label: ?string, office_visits: int, open_case: ?BehaviorCase}
     */
    public function summary(Student $student): array
    {
        $year = $this->activeYear();
        if (! $year) {
            return ['year' => null, 'score' => null, 'stage' => null, 'stage_label' => null, 'office_visits' => 0, 'open_case' => null];
        }

        $stage = self::highestStage($this->openFlags($student->id, $year->id)->pluck('stage'));

        return [
            'year' => $year,
            'score' => $this->score($student->id, $year->id),
            'stage' => $stage,
            'stage_label' => BehaviorFlag::label($stage),
            'office_visits' => $this->officeVisits($student->id, $year->id),
            'open_case' => BehaviorCase::where('student_id', $student->id)->where('status', 'Open')->latest('id')->first(),
        ];
    }

    /**
     * Batched figures for a list of students.
     *
     * @return array<int, array{score: int, stage: ?string, stage_label: ?string, office_visits: int}>
     */
    public function summaries(Collection $studentIds): array
    {
        $yearId = $this->activeYearId();
        $ids = $studentIds->values()->all();
        if (! $yearId || $ids === []) {
            return [];
        }

        $scores = $this->scoresForYear($yearId, $ids);
        $stages = BehaviorFlag::open()->where('academic_year_id', $yearId)->whereIn('student_id', $ids)
            ->get(['student_id', 'stage'])->groupBy('student_id')
            ->map(fn ($flags) => self::highestStage($flags->pluck('stage')));
        $visits = BehaviorReport::where('academic_year_id', $yearId)->whereIn('student_id', $ids)
            ->where('is_office_referral', true)->whereIn('status', BehaviorReport::COUNTED_STATUSES)
            ->groupBy('student_id')->selectRaw('student_id, COUNT(*) AS c')->pluck('c', 'student_id');

        $out = [];
        foreach ($ids as $id) {
            $stage = $stages[$id] ?? null;
            $out[$id] = [
                'score' => $scores[$id] ?? self::START_SCORE,
                'stage' => $stage,
                'stage_label' => BehaviorFlag::label($stage),
                'office_visits' => (int) ($visits[$id] ?? 0),
            ];
        }

        return $out;
    }
}
