<?php

namespace App\Services\Results;

use App\Models\ResultFreeze;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * The results of every student in a section for a term or a year. `frozen` when read from a
 * freeze; `lockedWithoutFreeze` when the term is locked but was never frozen (locked before
 * frozen results existed), so it was calculated live.
 */
final class SectionResults
{
    /** @param  Collection<int, StudentResultData>  $students  keyed by student id */
    public function __construct(
        public readonly Collection $students,
        public readonly bool $frozen = false,
        public readonly ?ResultFreeze $freeze = null,
        public readonly bool $lockedWithoutFreeze = false,
    ) {}

    public function for(Student|int $student): ?StudentResultData
    {
        return $this->students->get($student instanceof Student ? $student->id : $student);
    }
}
