<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Models\ImportedFeeRecord;
use App\Models\Student;
use App\Services\FeeSummaryService;
use Illuminate\Http\Request;

class ImportedFeeRecordController extends Controller
{
    public function index(Request $request, FeeSummaryService $service)
    {
        $summaries = $service->studentSummaries();

        if ($search = $request->string('search')->trim()->value()) {
            $summaries = $summaries->filter(fn ($s) => str_contains(strtolower($s->student->name), strtolower($search))
                || str_contains(strtolower($s->student->student_id_number), strtolower($search)));
        }

        if ($status = $request->string('status')->value()) {
            $summaries = $summaries->where('status', $status);
        }

        return view('treasurer.records.index', [
            'summaries' => $summaries->sortBy(fn ($s) => $s->student->name)->values(),
            'filters' => $request->only(['search', 'status']),
            'stats' => [
                'studentSummaries' => $summaries->count(),
                'transactionLines' => ImportedFeeRecord::whereNotNull('student_id')->count(),
                'partialOutstanding' => $summaries->whereIn('status', ['Partial', 'Owed', 'Outstanding'])->count(),
                'hiddenRows' => ImportedFeeRecord::where('is_restricted', true)->count(),
            ],
        ]);
    }

    public function show(Student $student)
    {
        return view('treasurer.records.show', [
            'student' => $student,
            'records' => ImportedFeeRecord::where('student_id', $student->id)->with('importBatch')->orderByDesc('txn_date')->get(),
        ]);
    }
}
