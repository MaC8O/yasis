<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffRecordController extends Controller
{
    protected array $portalRoleMap = [
        'admin' => 'Admin', 'principal' => 'Principal', 'vp_academic' => 'VP_Academic',
        'registrar' => 'Registrar', 'teacher' => 'Teacher', 'treasurer' => 'Treasurer', 'hr_office' => 'HR_Office',
    ];

    public function index(Request $request)
    {
        $query = StaffProfile::with(['user', 'department']);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($q) => $q->where('staff_id_number', 'like', "%{$search}%")
                ->orWhere('job_title', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")));
        }

        if ($departmentId = $request->string('department')->value()) {
            $query->where('department_id', $departmentId);
        }

        if ($status = $request->string('status')->value()) {
            $query->where('staff_profiles.status', $status);
        }

        return view('hr.staff.index', [
            'staff' => $query->join('users', 'users.id', '=', 'staff_profiles.id')->orderBy('users.name')
                ->select('staff_profiles.*')->get(),
            'departments' => Department::orderBy('name')->get(),
            'filters' => $request->only(['search', 'department', 'status']),
        ]);
    }

    public function create()
    {
        return view('hr.staff.create', [
            'departments' => Department::orderBy('name')->get(),
            'portalRoles' => array_keys($this->portalRoleMap),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'staff_id_number' => ['required', 'string', 'max:30', 'unique:staff_profiles,staff_id_number'],
            'job_title' => ['required', 'string', 'max:60'],
            'portal_access' => ['nullable', 'boolean'],
            'portal_role' => ['nullable', 'required_if:portal_access,1', Rule::in(array_keys($this->portalRoleMap))],
            'email' => ['nullable', 'required_if:portal_access,1', 'email', 'unique:users,email'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'joined_date' => ['required', 'date'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $isPortal = $request->boolean('portal_access');

        $user = User::create([
            'name' => $data['name'],
            'email' => $isPortal ? $data['email'] : Str::slug($data['name']).'.'.Str::lower($data['staff_id_number']).'@internal.yasis.edu',
            'password' => Hash::make(Str::password(16)),
            'status' => $isPortal ? 'Pending' : 'Inactive',
        ]);

        $roleType = $isPortal ? $this->portalRoleMap[$data['portal_role']] : 'Staff';
        if ($isPortal) {
            $user->assignRole($data['portal_role']);
        }

        $staff = StaffProfile::create([
            'id' => $user->id,
            'staff_id_number' => $data['staff_id_number'],
            'role_type' => $roleType,
            'job_title' => $data['job_title'],
            'department_id' => $data['department_id'] ?? null,
            'status' => 'Active',
            'joined_date' => $data['joined_date'],
            'phone' => $data['phone'] ?? null,
        ]);

        $audit->log($request->user(), 'Added staff record', 'StaffProfile', $staff->id);

        if ($isPortal) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return redirect()->route('hr_office.staff.index')->with('status', "{$data['name']} added to staff records.");
    }

    public function show(StaffProfile $staffProfile)
    {
        return view('hr.staff.show', [
            'staffMember' => $staffProfile->load(['user', 'department', 'leaveBalances.leaveType', 'leaveRequests.leaveType']),
        ]);
    }

    public function updateStatus(Request $request, StaffProfile $staffProfile, AuditService $audit)
    {
        $data = $request->validate([
            'status' => ['required', 'in:Active,On Leave,Probation,Inactive'],
        ]);

        $staffProfile->update($data);
        $audit->log($request->user(), 'Updated staff employment status', 'StaffProfile', $staffProfile->id);

        return back()->with('status', "Status updated to {$data['status']}.");
    }
}
