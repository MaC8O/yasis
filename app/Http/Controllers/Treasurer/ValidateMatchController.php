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
            // Picker options for typing an ID or name (only needed while rows await a match).
            'pickerStudents' => $unmatched->isEmpty() ? collect() : Student::orderBy('name')->get(['id', 'name', 'student_id_number']),
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

        return $this->backTo("row-{$importedFeeRecord->id}")->with('status', $importedFeeRecord->is_restricted
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

        // Held from a "needs a match" card → return to that list; from the table → to the row.
        $fragment = $request->input('from') === 'match' ? 'needs-match' : "row-{$importedFeeRecord->id}";

        return $this->backTo($fragment)->with('status', $importedFeeRecord->is_held
            ? 'Row held — parked out of publishing until released.'
            : 'Row released from hold.');
    }

    /**
     * §9.4 manual match: map an unmatched row to an ISMS student, chosen by student ID
     * (from a suggestion or the picker) or by exact full name when that name is unique.
     * Success and failure are both reported on the row itself, not just at the top of the page.
     */
    public function resolve(Request $request, ImportedFeeRecord $importedFeeRecord, AuditService $audit)
    {
        $row = $importedFeeRecord;
        $input = trim((string) $request->input('student'));
        $fail = fn (string $message) => $this->backTo("match-{$row->id}")
            ->withErrors(['student' => $message], "resolve_{$row->id}")
            ->withInput(['student' => $input])
            ->with('resolve_row', $row->id);

        if ($row->student_id !== null) {
            return $this->backTo('needs-match')->with('warning', 'That row was already matched — nothing changed.');
        }

        if ($input === '') {
            return $fail('Choose a suggested student, or type a student ID or full name.');
        }

        $student = Student::where('student_id_number', $input)->first();

        if (! $student) {
            $byName = Student::where('name', $input)->get();
            if ($byName->count() > 1) {
                return $fail("{$byName->count()} students are named “{$input}”. Use the student ID instead.");
            }
            $student = $byName->first();
        }

        if (! $student) {
            return $fail("No ISMS student has the ID or name “{$input}”. Check the spelling, or Hold this row until the student is registered.");
        }

        // The source ID is kept: it is the record of what the export said, and it lets the match be undone.
        $row->update(['student_id' => $student->id]);
        $row->setRelation('student', $student);

        $audit->log($request->user(), 'Resolved unmatched fee row', 'ImportedFeeRecord', $row->id, [
            'source_id' => $row->raw_student_key,
            'source_name' => $row->raw_student_name,
            'matched_student' => $student->student_id_number,
        ]);

        $remaining = ImportedFeeRecord::where('import_batch_id', $row->import_batch_id)
            ->whereNull('student_id')->where('is_held', false)->count();

        $message = "Matched {$row->raw_student_key} to {$student->name} ({$student->student_id_number}). "
            .match (true) {
                $remaining === 0 => 'Every row in this batch is now matched or held.',
                $remaining === 1 => '1 row still needs a match.',
                default => "{$remaining} rows still need a match.",
            };

        if ($row->importBatch->is_published) {
            $message .= ' This batch is published, so the line is now visible to the family.';
        }

        $redirect = $this->backTo('needs-match')->with('status', $message)->with('matched_row', $row->id);

        return $row->has_name_conflict
            ? $redirect->with('warning', "Check this match: the export names “{$row->raw_student_name}”, but {$student->student_id_number} is {$student->name}.")
            : $redirect;
    }

    /** Undo a manual match — the row goes back to "needs a match". */
    public function unmatch(Request $request, ImportedFeeRecord $importedFeeRecord, AuditService $audit)
    {
        $row = $importedFeeRecord;

        // Rows matched automatically on import carry no source key: their ID came straight from the
        // export, so the fix belongs in the accounting system and a re-import, not here.
        if ($row->student_id === null || $row->raw_student_key === null) {
            return $this->backTo("row-{$row->id}")->with('warning', 'Only manually matched rows can be unmatched. Correct the student ID in the export and re-import instead.');
        }

        $previous = $row->student;
        $row->update(['student_id' => null]);

        $audit->log($request->user(), 'Undid manual fee row match', 'ImportedFeeRecord', $row->id, [
            'source_id' => $row->raw_student_key,
            'previous_student' => $previous?->student_id_number,
        ]);

        return $this->backTo('needs-match')
            ->with('status', "Match undone — {$row->raw_student_key} needs a match again.");
    }

    /** Redirect to the page we came from, scrolled to $fragment (so feedback appears where the user is). */
    private function backTo(string $fragment)
    {
        return redirect()->to(strtok(url()->previous(), '#').'#'.$fragment);
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
