<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Services\AuditService;
use Illuminate\Http\Request;

class AcademicYearController extends Controller
{
    public function index()
    {
        return view('admin.academic-year.index', [
            'academicYears' => AcademicYear::with(['terms', 'sections'])->orderByDesc('year_label')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'year_label' => ['required', 'string', 'max:20', 'unique:academic_years,year_label'],
            'start_date' => ['required', 'date'],
        ]);

        $year = AcademicYear::create([
            'year_label' => $data['year_label'],
            'is_active' => false,
        ]);

        $start = \Carbon\Carbon::parse($data['start_date']);
        for ($i = 1; $i <= 4; $i++) {
            $termStart = $start->copy()->addWeeks(($i - 1) * 9);
            $termEnd = $termStart->copy()->addWeeks(9)->subDay();

            Term::create([
                'academic_year_id' => $year->id,
                'name' => "Term {$i}",
                'sequence' => $i,
                'start_date' => $termStart,
                'end_date' => $termEnd,
            ]);
        }

        $audit->log($request->user(), 'Created academic year', 'AcademicYear', $year->id);

        return redirect()->route('admin.academic-year.index')->with('status', "Academic year {$year->year_label} created with 4 terms.");
    }

    public function activate(Request $request, AcademicYear $academicYear, AuditService $audit)
    {
        AcademicYear::where('is_active', true)->update(['is_active' => false]);
        $academicYear->update(['is_active' => true]);

        $audit->log($request->user(), 'Activated academic year', 'AcademicYear', $academicYear->id);

        return back()->with('status', "{$academicYear->year_label} is now the active academic year.");
    }

    public function update(Request $request, AcademicYear $academicYear, AuditService $audit)
    {
        $data = $request->validate([
            'year_label' => ['required', 'string', 'max:20', "unique:academic_years,year_label,{$academicYear->id}"],
            'terms' => ['required', 'array'],
            'terms.*.id' => ['required', 'exists:terms,id'],
            'terms.*.start_date' => ['required', 'date'],
            'terms.*.end_date' => ['required', 'date', 'after:terms.*.start_date'],
        ]);

        $academicYear->update(['year_label' => $data['year_label']]);

        foreach ($data['terms'] as $termData) {
            $term = $academicYear->terms()->findOrFail($termData['id']);
            $term->update(['start_date' => $termData['start_date'], 'end_date' => $termData['end_date']]);
        }

        $audit->log($request->user(), 'Edited academic year', 'AcademicYear', $academicYear->id);

        return redirect()->route('admin.academic-year.index')->with('status', "{$academicYear->year_label} updated.");
    }

    public function destroy(Request $request, AcademicYear $academicYear, AuditService $audit)
    {
        if ($academicYear->is_active) {
            return back()->withErrors(['year' => "{$academicYear->year_label} is the active year and cannot be deleted. Activate another year first."]);
        }

        // A year with sections has (or can have) enrollments, attendance, and grades
        // hanging off it — deleting it would orphan academic history.
        if ($academicYear->sections()->exists()) {
            return back()->withErrors(['year' => "{$academicYear->year_label} has sections (and possibly enrollment/academic records) and cannot be deleted."]);
        }

        $label = $academicYear->year_label;
        $id = $academicYear->id;

        $academicYear->terms()->delete();
        $academicYear->delete();

        $audit->log($request->user(), 'Deleted academic year', 'AcademicYear', $id);

        return back()->with('status', "Academic year {$label} deleted.");
    }
}
