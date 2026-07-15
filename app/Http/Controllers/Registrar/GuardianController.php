<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\UserProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

class GuardianController extends Controller
{
    public function __construct(private UserProvisioningService $provisioning) {}

    public function index(Request $request)
    {
        $query = Guardian::query()->with(['user', 'students']);

        if ($search = $request->string('search')->trim()->value()) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if ($status = $request->string('status')->value()) {
            $query->whereHas('user', fn ($q) => $q->where('status', $status));
        }

        return view('registrar.guardians.index', [
            'guardians' => $query->orderBy('id')->paginate(\App\Support\PerPage::resolve($request))->withQueryString(),
            'filters' => $request->only(['search', 'status']),
            'linkStudent' => $request->filled('student') ? Student::find($request->integer('student')) : null,
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'relationship' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'student_id_number' => ['nullable', 'exists:students,student_id_number'],
        ]);

        // The portal invite is sent by the service; audit is logged against the Guardian below.
        $user = $this->provisioning->provisionAccount(
            ['name' => $data['name'], 'email' => $data['email']],
            'guardian',
            auditAction: null,
        );

        $guardian = Guardian::create([
            'user_id' => $user->id,
            'relationship' => $data['relationship'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        if (! empty($data['student_id_number'])) {
            $student = Student::where('student_id_number', $data['student_id_number'])->first();
            // §4.1: exactly one primary guardian per student — only take primary if the seat is empty.
            $hasPrimary = $student->guardians()->wherePivot('is_primary', true)->exists();
            $guardian->students()->attach($student->id, ['is_primary' => ! $hasPrimary]);
        }

        $audit->log($request->user(), 'Added guardian', 'Guardian', $guardian->id);

        return redirect()->route('registrar.guardians.index')->with('status', 'Guardian added and portal invite sent.');
    }

    public function show(Guardian $guardian)
    {
        return view('registrar.guardians.show', [
            'guardian' => $guardian->load(['user', 'students']),
        ]);
    }

    /** §7.4: edit guardian contact details. */
    public function update(Request $request, Guardian $guardian, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($guardian->user_id)],
            'relationship' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        DB::transaction(function () use ($guardian, $data, $request, $audit) {
            $guardian->user->update(['name' => $data['name'], 'email' => $data['email']]);
            $guardian->update(['relationship' => $data['relationship'] ?? null, 'phone' => $data['phone'] ?? null]);
            $audit->log($request->user(), 'Updated guardian contact', 'Guardian', $guardian->id);
        });

        return back()->with('status', 'Guardian contact updated.');
    }

    public function link(Request $request, Guardian $guardian, AuditService $audit)
    {
        $data = $request->validate([
            'student_id_number' => ['required', 'exists:students,student_id_number'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $student = Student::where('student_id_number', $data['student_id_number'])->firstOrFail();

        DB::transaction(function () use ($guardian, $student, $request, $audit) {
            // §4.1: exactly one primary per student — a first guardian is always primary,
            // and an explicit primary flips the previous one off.
            $makePrimary = $request->boolean('is_primary') || $student->guardians()->doesntExist();

            if ($makePrimary) {
                $student->guardians()->newPivotStatement()
                    ->where('student_id', $student->id)->update(['is_primary' => false]);
            }

            $guardian->students()->syncWithoutDetaching([$student->id => ['is_primary' => $makePrimary]]);
            $audit->log($request->user(), 'Linked guardian to student', 'Guardian', $guardian->id);
        });

        return back()->with('status', "Linked to {$student->name}.");
    }

    /** §7.4: unlink a student — never allowed to leave the student guardian-less. */
    public function unlink(Request $request, Guardian $guardian, Student $student, AuditService $audit)
    {
        abort_unless($guardian->students()->whereKey($student->id)->exists(), 404);

        if ($student->guardians()->count() <= 1) {
            return back()->withErrors([
                'link' => "Cannot unlink: this is the only guardian of {$student->name}. Link another guardian first.",
            ]);
        }

        DB::transaction(function () use ($guardian, $student, $request, $audit) {
            $wasPrimary = $guardian->students()->whereKey($student->id)
                ->wherePivot('is_primary', true)->exists();

            $guardian->students()->detach($student->id);

            // Keep the one-primary invariant: promote the first remaining guardian.
            if ($wasPrimary && ($next = $student->guardians()->first())) {
                $student->guardians()->updateExistingPivot($next->id, ['is_primary' => true]);
            }

            $audit->log($request->user(), 'Unlinked guardian from student', 'Guardian', $guardian->id);
        });

        return back()->with('status', "Unlinked from {$student->name}.");
    }

    /** §7.4: setting a new primary flips the previous primary off atomically. */
    public function setPrimary(Request $request, Guardian $guardian, Student $student, AuditService $audit)
    {
        abort_unless($guardian->students()->whereKey($student->id)->exists(), 404);

        DB::transaction(function () use ($guardian, $student, $request, $audit) {
            $student->guardians()->newPivotStatement()
                ->where('student_id', $student->id)->update(['is_primary' => false]);
            $student->guardians()->updateExistingPivot($guardian->id, ['is_primary' => true]);

            $audit->log($request->user(), 'Set primary guardian', 'Guardian', $guardian->id);
        });

        return back()->with('status', 'Primary guardian updated.');
    }

    public function resendInvite(Request $request, Guardian $guardian, AuditService $audit)
    {
        Password::sendResetLink(['email' => $guardian->user->email]);
        $audit->log($request->user(), 'Re-sent guardian portal invite', 'Guardian', $guardian->id);

        return back()->with('status', 'Portal invite re-sent.');
    }
}
