<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\StaffProfile;
use App\Services\AuditService;
use App\Services\UserProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffRecordController extends Controller
{
    public function __construct(private UserProvisioningService $provisioning) {}

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

        $staff = $query->join('users', 'users.id', '=', 'staff_profiles.id')->orderBy('users.name')
            ->select('staff_profiles.*')->get();

        // Master-detail: the panel shows ?selected=<id> (kept in the URL so actions that
        // redirect back() land on the same person), falling back to the first row listed.
        $selected = $request->integer('selected') ? StaffProfile::find($request->integer('selected')) : null;
        $selected ??= $staff->first();

        return view('hr.staff.index', [
            'panel' => $selected ? $this->panelData($selected) : null,
            'staff' => $staff,
            'departments' => Department::orderBy('name')->get(),
            'filters' => $request->only(['search', 'department', 'status']),
        ]);
    }

    /** The staff detail panel alone — fetched by the index page when a row is selected. */
    public function panel(StaffProfile $staffProfile)
    {
        return view('hr.staff.panel', $this->panelData($staffProfile));
    }

    private function panelData(StaffProfile $staffProfile): array
    {
        $staffProfile->load([
            'user',
            'department',
            'homeroomSections' => fn ($q) => $q->whereHas('academicYear', fn ($y) => $y->where('is_active', true)),
            'teachingAssignments' => fn ($q) => $q->whereHas('section.academicYear', fn ($y) => $y->where('is_active', true))
                ->with(['section', 'subject']),
            'leaveBalances' => fn ($q) => $q->where('year', now()->year)->with('leaveType'),
            'leaveRequests' => fn ($q) => $q->with('leaveType')->latest('from_date')->limit(5),
        ]);

        return [
            'staffMember' => $staffProfile,
            'onLeave' => $staffProfile->leaveRequests()->where('status', 'Approved')
                ->whereDate('from_date', '<=', today())->whereDate('to_date', '>=', today())->first(),
            'pendingLeave' => $staffProfile->leaveRequests()->where('status', 'Pending')->count(),
            // This month's attendance, keyed by status (Present/Tardy/Absent/On-Leave).
            'attendance' => $staffProfile->staffAttendances()
                ->whereBetween('attendance_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ];
    }

    public function create()
    {
        return view('hr.staff.create', [
            'departments' => Department::orderBy('name')->get(),
            // HR may onboard any staff role except Admin — see UserProvisioningService::hrAssignableRoles().
            'portalRoles' => $this->provisioning->hrAssignableRoles(),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'staff_id_number' => ['required', 'string', 'max:30', 'unique:staff_profiles,staff_id_number'],
            'job_title' => ['required', 'string', 'max:60'],
            'portal_access' => ['nullable', 'boolean'],
            // Restricting to hrAssignableRoles() is the server-side escalation guard: HR
            // cannot mint an Admin even by tampering with the form.
            'portal_role' => ['nullable', 'required_if:portal_access,1', Rule::in($this->provisioning->hrAssignableRoles())],
            'email' => ['nullable', 'required_if:portal_access,1', 'email', 'unique:users,email'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'joined_date' => ['required', 'date'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $isPortal = $request->boolean('portal_access');

        $user = $this->provisioning->provisionStaff(
            [
                'name' => $data['name'],
                // Non-portal staff still need a unique users row (StaffProfile shares its id),
                // so synthesize an internal, non-routable address they can never sign in with.
                'email' => $isPortal ? $data['email'] : Str::slug($data['name']).'.'.Str::lower($data['staff_id_number']).'@internal.yasis.edu',
            ],
            $isPortal ? $data['portal_role'] : null,
            [
                'staff_id_number' => $data['staff_id_number'],
                'job_title' => $data['job_title'],
                'department_id' => $data['department_id'] ?? null,
                'joined_date' => $data['joined_date'],
                'phone' => $data['phone'] ?? null,
            ],
            invite: $isPortal,
            pendingStatus: $isPortal ? 'Pending' : 'Inactive',
            // HR logs against its own StaffProfile entity below, so skip the service's User audit line.
            auditAction: null,
        );

        $audit->log($request->user(), 'Added staff record', 'StaffProfile', $user->id);

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
