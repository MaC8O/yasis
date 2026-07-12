<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AuditService;
use App\Support\SecurityPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    protected array $staffRoles = ['admin', 'principal', 'vp_academic', 'registrar', 'teacher', 'treasurer', 'hr_office'];
    protected array $roleTypeMap = [
        'admin' => 'Admin', 'principal' => 'Principal', 'vp_academic' => 'VP_Academic',
        'registrar' => 'Registrar', 'teacher' => 'Teacher', 'treasurer' => 'Treasurer', 'hr_office' => 'HR_Office',
    ];

    public function index(Request $request)
    {
        $query = User::query()->with(['roles', 'staffProfile']);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if ($role = $request->string('role')->value()) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        return view('admin.users.index', [
            'users' => $query->orderBy('name')->paginate(\App\Support\PerPage::resolve($request))->withQueryString(),
            'roles' => Role::pluck('name'),
            'filters' => $request->only(['search', 'role', 'status']),
        ]);
    }

    public function create()
    {
        return view('admin.users.create', [
            'roles' => Role::pluck('name'),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', 'string', 'in:'.implode(',', Role::pluck('name')->all())],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:Male,Female'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'staff_id_number' => ['nullable', 'required_if:role,'.implode(',', $this->staffRoles), 'string', 'max:30', 'unique:staff_profiles,staff_id_number'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'joined_date' => ['nullable', 'required_if:role,'.implode(',', $this->staffRoles), 'date'],
            'password' => ['nullable', 'confirmed', SecurityPolicy::passwordRule()],
        ]);

        // With an initial password the account is usable immediately (Active);
        // without one it stays Pending until the emailed setup link is completed.
        $hasInitialPassword = ! empty($data['password']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($hasInitialPassword ? $data['password'] : Str::password(12)),
            'status' => $hasInitialPassword ? 'Active' : 'Pending',
            // When the policy requires it, an admin-set initial password must be changed on first login.
            'must_reset_password' => $hasInitialPassword && SecurityPolicy::forceResetNewAccounts(),
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);
        $user->assignRole($data['role']);

        if (in_array($data['role'], $this->staffRoles)) {
            StaffProfile::create([
                'id' => $user->id,
                'staff_id_number' => $data['staff_id_number'],
                'role_type' => $this->roleTypeMap[$data['role']],
                'department_id' => $data['department_id'] ?? null,
                'status' => 'Active',
                'joined_date' => $data['joined_date'],
                'phone' => $data['phone'] ?? null,
            ]);
        }

        $audit->log($request->user(), 'Created user', 'User', $user->id);

        if ($hasInitialPassword) {
            return redirect()->route('admin.users.index')
                ->with('status', "User created and active — {$user->email} can sign in with the password you set.");
        }

        Password::sendResetLink(['email' => $user->email]);

        return redirect()->route('admin.users.index')
            ->with('status', "User created (pending) and a login-setup email was sent to {$user->email}. The account activates when they set their password.");
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', [
            'editUser' => $user->load('roles', 'staffProfile'),
            'roles' => Role::pluck('name'),
        ]);
    }

    public function update(Request $request, User $user, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', "unique:users,email,{$user->id}"],
            'role' => ['required', 'string', 'in:'.implode(',', Role::pluck('name')->all())],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:Male,Female'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);
        $user->syncRoles([$data['role']]);

        $audit->log($request->user(), 'Edited user', 'User', $user->id);

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    public function deactivate(Request $request, User $user, AuditService $audit)
    {
        $user->update(['status' => 'Inactive']);
        $audit->log($request->user(), 'Deactivated user', 'User', $user->id);

        return back()->with('status', "{$user->name} deactivated.");
    }

    public function reactivate(Request $request, User $user, AuditService $audit)
    {
        $user->update(['status' => 'Active']);
        $audit->log($request->user(), 'Reactivated user', 'User', $user->id);

        return back()->with('status', "{$user->name} reactivated.");
    }

    public function resetPassword(Request $request, User $user, AuditService $audit)
    {
        // §3.1/§6.2: force the user through the set-password screen on next login and
        // e-mail a fresh reset link. The password itself is never exposed here.
        $user->forceFill(['must_reset_password' => true])->save();

        Password::sendResetLink(['email' => $user->email]);
        $audit->log($request->user(), 'Reset password / re-sent login', 'User', $user->id);

        return back()->with('status', "Password reset link re-sent to {$user->email}.");
    }

    public function unlock(Request $request, User $user, AuditService $audit)
    {
        $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->save();
        $audit->log($request->user(), 'Unlocked user account', 'User', $user->id);

        return back()->with('status', "{$user->name}'s account unlocked.");
    }

    public function uploadPhoto(Request $request, User $user, AuditService $audit, \App\Services\AvatarService $avatars)
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'photo.max' => 'The photo may not be larger than 10 MB.',
        ]);

        if ($user->photo_path) {
            Storage::disk('public')->delete($user->photo_path);
        }

        // Center-cropped square, EXIF-rotated, resized to 512px — renders cleanly everywhere.
        $path = $avatars->storeSquare($request->file('photo'));
        $user->update(['photo_path' => $path]);

        $audit->log($request->user(), 'Uploaded user photo', 'User', $user->id);

        return back()->with('status', "Photo updated for {$user->name}.");
    }

    public function deletePhoto(Request $request, User $user, AuditService $audit)
    {
        if ($user->photo_path) {
            Storage::disk('public')->delete($user->photo_path);
            $user->update(['photo_path' => null]);
            $audit->log($request->user(), 'Removed user photo', 'User', $user->id);
        }

        return back()->with('status', "Photo removed for {$user->name}.");
    }

    public function setPassword(Request $request, User $user, AuditService $audit)
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', SecurityPolicy::passwordRule()],
        ]);

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'remember_token' => Str::random(60),
        ])->save();

        $audit->log($request->user(), 'Set a new password for user (admin-initiated)', 'User', $user->id);

        return back()->with('status', "New password set for {$user->name}. Share it with them securely — it will not be shown again here.");
    }

    /**
     * Users who have already acted in the system (audit log entries, grades entered, attendance
     * recorded, leave decided, etc.) are referenced by FK from records that must survive for
     * non-repudiation (§3.8). Deleting those users would either violate the DB's referential
     * integrity or silently orphan historical records, so a real delete is only performed when
     * the database confirms the row has no dependents; otherwise we fall back to a GDPR/PDPA-style
     * erasure — scrub PII, deactivate, keep the row so existing references stay valid.
     */
    public function destroy(Request $request, User $user, AuditService $audit)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('status', 'You cannot delete your own account. Ask another Admin, or deactivate your account instead.');
        }

        $userId = $user->id;
        $name = $user->name;

        if ($user->photo_path) {
            Storage::disk('public')->delete($user->photo_path);
            $user->photo_path = null;
        }

        try {
            DB::transaction(fn () => $user->delete());

            $audit->log($request->user(), 'Deleted user', 'User', $userId);

            return redirect()->route('admin.users.index')->with('status', "{$name}'s account permanently deleted.");
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            $user->forceFill([
                'name' => "Erased User #{$userId}",
                'email' => "erased-{$userId}@deleted.yasis.edu",
                'password' => Hash::make(Str::random(32)),
                'status' => 'Inactive',
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'remember_token' => Str::random(60),
            ])->save();

            $audit->log($request->user(), 'Erased user PII — linked records retained for audit trail', 'User', $userId);

            return redirect()->route('admin.users.index')
                ->with('status', "{$name} has linked academic, financial, or audit records, so the account was anonymized and deactivated instead of being removed, to preserve the audit trail.");
        }
    }
}
