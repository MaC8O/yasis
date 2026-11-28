<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registrar\SaveTracksRequest;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Services\TrackService;

/**
 * Grade 10 and Grades 11-12 tracks (science or arts), set by the Registrar per section. Sections are
 * listed when their grade level's curriculum has tracks, so this follows the data, not fixed grades.
 */
class TrackController extends Controller
{
    public function __construct(private TrackService $tracks) {}

    public function index()
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        $rows = Section::active()->where('academic_year_id', $activeYear?->id)
            ->whereNotNull('grade_level_id')
            ->whereHas('gradeLevel.offerings', fn ($q) => $q->active()->whereNotNull('track'))
            ->with('gradeLevel')->orderByGradeLevel()->get()
            ->map(function (Section $section) {
                $students = $this->tracks->roster($section);
                $set = $this->tracks->current($section, $students->pluck('id'));

                return ['section' => $section, 'tracks' => $this->tracks->tracksFor($section)->keys(), 'students' => $students->count(),
                    'missing' => $students->count() - $set->count(), 'counts' => $set->countBy()];
            });

        return view('registrar.tracks.index', ['activeYear' => $activeYear, 'rows' => $rows]);
    }

    public function show(Section $section)
    {
        $tracks = $this->tracks->tracksFor($section);
        abort_if($tracks->isEmpty(), 404);
        $students = $this->tracks->roster($section);

        return view('registrar.tracks.show', [
            'section' => $section->load(['gradeLevel', 'academicYear']),
            'tracks' => $tracks,
            'students' => $students,
            'current' => $this->tracks->current($section, $students->pluck('id')),
        ]);
    }

    public function update(SaveTracksRequest $request, Section $section)
    {
        abort_if($this->tracks->tracksFor($section)->isEmpty(), 404);

        $changed = $this->tracks->save($section, $request->validated('tracks'), $request->user());

        return back()->with('status', $changed === 0 ? 'No tracks were changed.' : "{$changed} ".($changed === 1 ? 'track' : 'tracks').' saved.');
    }
}
