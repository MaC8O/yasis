<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class GuardianController extends Controller
{
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
            'guardians' => $query->orderBy('id')->paginate(15)->withQueryString(),
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

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make(Str::password(12)),
            'status' => 'Pending',
        ]);
        $user->assignRole('guardian');

        $guardian = Guardian::create([
            'user_id' => $user->id,
            'relationship' => $data['relationship'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        if (! empty($data['student_id_number'])) {
            $student = Student::where('student_id_number', $data['student_id_number'])->first();
            $guardian->students()->attach($student->id, ['is_primary' => true]);
        }

        $audit->log($request->user(), 'Added guardian', 'Guardian', $guardian->id);
        Password::sendResetLink(['email' => $user->email]);

        return redirect()->route('registrar.guardians.index')->with('status', 'Guardian added and portal invite sent.');
    }

    public function show(Guardian $guardian)
    {
        return view('registrar.guardians.show', [
            'guardian' => $guardian->load(['user', 'students']),
        ]);
    }

    public function link(Request $request, Guardian $guardian, AuditService $audit)
    {
        $data = $request->validate([
            'student_id_number' => ['required', 'exists:students,student_id_number'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $student = Student::where('student_id_number', $data['student_id_number'])->firstOrFail();
        $guardian->students()->syncWithoutDetaching([$student->id => ['is_primary' => $request->boolean('is_primary')]]);

        $audit->log($request->user(), 'Linked guardian to student', 'Guardian', $guardian->id);

        return back()->with('status', "Linked to {$student->first_name} {$student->last_name}.");
    }

    public function resendInvite(Request $request, Guardian $guardian, AuditService $audit)
    {
        Password::sendResetLink(['email' => $guardian->user->email]);
        $audit->log($request->user(), 'Re-sent guardian portal invite', 'Guardian', $guardian->id);

        return back()->with('status', 'Portal invite re-sent.');
    }
}
