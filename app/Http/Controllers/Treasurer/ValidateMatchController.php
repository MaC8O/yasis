<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\ImportedFeeRecord;
use App\Models\Student;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ValidateMatchController extends Controller
{
    public function index(Request $request)
    {
        $batches = ImportBatch::orderByDesc('uploaded_at')->get();
        $batch = $batches->firstWhere('id', $request->integer('batch')) ?? $batches->first();

        $records = $batch
            ? ImportedFeeRecord::where('import_batch_id', $batch->id)->with('student')->orderBy('txn_date')->orderBy('id')->get()
            : collect();

        $unmatched = $records->whereNull('student_id')->where('is_held', false);

        return view('treasurer.validate.index', [
            'batches' => $batches,
            'batch' => $batch,
            'records' => $records,
            'uploaded' => $records->count(),
            'matched' => $records->whereNotNull('student_id')->count(),
            'unmatchedRecords' => $unmatched,
            'suggestions' => $this->suggestions($unmatched),
            'nameConflicts' => $records->filter->has_name_conflict->count(),
            'restrictedCount' => $records->where('is_restricted', true)->count(),
            'heldCount' => $records->where('is_held', true)->count(),
            'blockingCount' => $unmatched->count(),
        ]);
    }

    /**
     * §9.4 manual-resolution drawer: up to three likely ISMS students per unmatched row,
     * ranked by how close the source name and ID are to each candidate.
     *
     * @return array<int, Collection<int, Student>> keyed by imported row id
     */
    private function suggestions(Collection $unmatched): array
    {
        if ($unmatched->isEmpty()) {
            return [];
        }

        $students = Student::where('enrollment_status', 'Enrolled')->get(['id', 'name', 'student_id_number']);
        $normalize = fn (?string $v) => preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $v));

        return $unmatched->mapWithKeys(function ($row) use ($students, $normalize) {
            $name = $normalize($row->raw_student_name);
            $key = $normalize($row->raw_student_key);

            $ranked = $students->map(function ($student) use ($name, $key, $normalize) {
                $score = 0;
                if ($name !== '') {
                    similar_text($name, $normalize($student->name), $pct);
                    $score = max($score, $pct);
                }
                if ($key !== '') {
                    similar_text($key, $normalize($student->student_id_number), $pct);
                    $score = max($score, $pct);
                }

                return ['student' => $student, 'score' => $score];
            })->filter(fn ($c) => $c['score'] >= 60)->sortByDesc('score')->take(3)->pluck('student')->values();

            return [$row->id => $ranked];
        })->all();
    }

    /** §9.4: restrict marks a row SDA-sensitive — hidden from guardians/students everywhere. */
    public function toggleRestrict(Request $request, ImportedFeeRecord $importedFeeRecord, AuditService $audit)
    {
        $importedFeeRecord->update(['is_restricted' => ! $importedFeeRecord->is_restricted]);

        // §9.8: the batch's restricted classification changed, so it needs re-confirming.
        $importedFeeRecord->importBatch()->update(['restricted_confirmed_at' => null, 'restricted_confirmed_by' => null]);

        $audit->log(
            $request->user(),
            $importedFeeRecord->is_restricted ? 'Restricted fee row' : 'Unrestricted fee row',
            'ImportedFeeRecord',
            $importedFeeRecord->id
        );

        return back()->with('status', $importedFeeRecord->is_restricted
            ? 'Row restricted — hidden from guardians and students.'
            : 'Restriction removed — row follows normal visibility.');
    }

    /** §9.4: hold parks a row — it stops blocking publish and never reaches family views. */
    public function toggleHold(Request $request, ImportedFeeRecord $importedFeeRecord, AuditService $audit)
    {
        $importedFeeRecord->update(['is_held' => ! $importedFeeRecord->is_held]);

        $audit->log(
            $request->user(),
            $importedFeeRecord->is_held ? 'Held fee row' : 'Released held fee row',
            'ImportedFeeRecord',
            $importedFeeRecord->id
        );

        return back()->with('status', $importedFeeRecord->is_held
            ? 'Row held — parked out of publishing until released.'
            : 'Row released from hold.');
    }

    public function resolve(Request $request, ImportedFeeRecord $importedFeeRecord, AuditService $audit)
    {
        $data = $request->validate([
            'student_id_number' => ['required', 'exists:students,student_id_number'],
        ]);

        $student = Student::where('student_id_number', $data['student_id_number'])->firstOrFail();
        $importedFeeRecord->update(['student_id' => $student->id, 'raw_student_key' => null]);

        $audit->log($request->user(), 'Resolved unmatched fee row', 'ImportedFeeRecord', $importedFeeRecord->id);

        return back()->with('status', "Row mapped to {$student->name}.");
    }

    public function publish(Request $request, ImportBatch $importBatch, AuditService $audit)
    {
        // §9.4: cannot publish while unresolved unmatched rows remain — hold a row to park it.
        $blocking = $importBatch->importedFeeRecords()
            ->whereNull('student_id')->where('is_held', false)->count();

        if ($blocking > 0) {
            return back()->withErrors([
                'publish' => "Cannot publish: {$blocking} unmatched row(s) remain. Match them to a student or put them on hold first.",
            ]);
        }

        // §9.8: families must never see an SDA line by mistake, so the Treasurer signs off
        // the batch's restricted classification as part of publishing.
        $request->validate(['confirm_restricted' => ['accepted']], [
            'confirm_restricted.accepted' => 'Confirm the restricted (SDA) rows are correctly marked before publishing.',
        ]);

        $held = $importBatch->importedFeeRecords()->where('is_held', true)->count();

        $importBatch->update([
            'published_at' => now(),
            'restricted_confirmed_at' => now(),
            'restricted_confirmed_by' => $request->user()->id,
        ]);
        $audit->log($request->user(), 'Published fee import batch', 'ImportBatch', $importBatch->id);

        $note = $held > 0 ? " Published with {$held} held row(s) excluded." : '';

        return back()->with('status', "Batch {$importBatch->period} published. Matched records are now visible to leadership and guardians.{$note}");
    }
}
