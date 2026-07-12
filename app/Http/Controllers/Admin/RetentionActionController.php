<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\RetentionService;
use Illuminate\Http\Request;

class RetentionActionController extends Controller
{
    /**
     * §6.2: action an erasure/retention request against a named student or
     * guardian, with a required reason. Audited; never a hard delete.
     */
    public function store(Request $request, RetentionService $retention)
    {
        $data = $request->validate([
            'subject_type' => ['required', 'in:student,guardian'],
            'identifier' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:200'],
        ]);

        if ($data['subject_type'] === 'student') {
            $student = Student::where('student_id_number', $data['identifier'])->first();

            if (! $student) {
                return back()->withErrors(['identifier' => 'No student found with that student ID.'])->withInput();
            }

            $retention->eraseStudent($student, $data['reason'], $request->user());

            return back()->with('status', 'Student record erased and anonymized; academic history retained without PII.');
        }

        $user = User::where('email', $data['identifier'])->first();
        $guardian = $user ? Guardian::where('user_id', $user->id)->first() : null;

        if (! $guardian) {
            return back()->withErrors(['identifier' => 'No guardian found with that email address.'])->withInput();
        }

        $retention->eraseGuardian($guardian, $data['reason'], $request->user());

        return back()->with('status', 'Guardian record erased and anonymized; portal access revoked.');
    }
}
