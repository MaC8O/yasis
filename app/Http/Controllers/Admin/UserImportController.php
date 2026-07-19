<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\UsersImport;
use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AuditService;
use App\Services\UserProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class UserImportController extends Controller
{
    public function __construct(private UserProvisioningService $provisioning) {}

    public function index()
    {
        return view('admin.users.import');
    }

    public function template(): Response
    {
        $csv = "name,email,role,staff_id_number,department,joined_date,date_of_birth,gender,phone,address\n"
            ."Daw Mya Mya,mya.mya@yasis.edu,teacher,USR-0101,High School,2026-06-01,1990-04-12,Female,+95 900-123-456,\"Bahan Township, Yangon\"\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="user_import_template.csv"',
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $import = new UsersImport;
        Excel::import($import, $request->file('file'));
        $rows = $import->rows;

        $staffRoles = $this->provisioning->staffRoles();
        $validRoles = array_merge($staffRoles, ['guardian', 'student']);

        $created = [];
        $skipped = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;

            $name = trim((string) ($row['name'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $role = Str::of((string) ($row['role'] ?? ''))->trim()->lower()->replace(' ', '_')->value();

            if ($name === '' || $email === '' || $role === '') {
                $errors[] = "Row {$rowNum}: missing required field (name, email, role).";

                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$rowNum}: \"{$email}\" is not a valid email address.";

                continue;
            }

            if (! in_array($role, $validRoles, true)) {
                $errors[] = "Row {$rowNum}: role \"{$role}\" is not one of the 9 system roles.";

                continue;
            }

            if (User::where('email', $email)->exists()) {
                $skipped[] = "Row {$rowNum}: {$email} already has an account — skipped.";

                continue;
            }

            $isStaff = in_array($role, $staffRoles, true);
            $staffIdNumber = trim((string) ($row['staff_id_number'] ?? ''));

            if ($isStaff && $staffIdNumber === '') {
                $errors[] = "Row {$rowNum}: staff role \"{$role}\" requires a staff_id_number.";

                continue;
            }

            if ($isStaff && StaffProfile::where('staff_id_number', $staffIdNumber)->exists()) {
                $skipped[] = "Row {$rowNum}: staff ID {$staffIdNumber} already exists — skipped.";

                continue;
            }

            $departmentName = trim((string) ($row['department'] ?? ''));
            $department = $departmentName !== ''
                ? Department::whereRaw('LOWER(name) = ?', [strtolower($departmentName)])->first()
                : null;
            if ($departmentName !== '' && ! $department) {
                $errors[] = "Row {$rowNum}: department \"{$departmentName}\" not recognized.";

                continue;
            }

            $attributes = [
                'name' => $name,
                'email' => $email,
                'date_of_birth' => trim((string) ($row['date_of_birth'] ?? '')) ?: null,
                'gender' => trim((string) ($row['gender'] ?? '')) ?: null,
                'phone' => trim((string) ($row['phone'] ?? '')) ?: null,
                'address' => trim((string) ($row['address'] ?? '')) ?: null,
            ];

            // The invite e-mail is sent per-row below so a single send failure can be
            // recorded without aborting the batch; the audit is a single line at the end.
            if ($isStaff) {
                $user = $this->provisioning->provisionStaff($attributes, $role, [
                    'staff_id_number' => $staffIdNumber,
                    'department_id' => $department?->id,
                    'joined_date' => trim((string) ($row['joined_date'] ?? '')) ?: now()->toDateString(),
                    'phone' => trim((string) ($row['phone'] ?? '')) ?: null,
                ], invite: false, auditAction: null);
            } else {
                $user = $this->provisioning->provisionAccount($attributes, $role, invite: false, auditAction: null);
            }

            // A failed login-setup email must not abort the rest of the import —
            // the Admin can re-send from the user list at any time.
            try {
                Password::sendResetLink(['email' => $user->email]);
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNum}: account created but the login-setup email failed to send — use Reset / Re-send from the user list.";
            }

            $created[] = "Row {$rowNum}: {$email} ({$role})";
        }

        $audit->log($request->user(), 'Bulk-imported user accounts ('.count($created).' created)', 'User');

        return redirect()->route('admin.users.import')
            ->with('status', count($created).' account(s) created, '.count($skipped).' skipped, '.count($errors).' issue(s).')
            ->with('importResults', ['created' => $created, 'skipped' => $skipped, 'errors' => $errors]);
    }
}
