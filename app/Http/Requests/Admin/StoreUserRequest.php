<?php

namespace App\Http\Requests\Admin;

use App\Services\UserProvisioningService;
use App\Support\SecurityPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin → User Management → Add User. Staff roles also need an HR identity (staff ID, joined date). */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is limited to the Admin role; the service re-checks the role scope
    }

    public function rules(): array
    {
        $provisioning = app(UserProvisioningService::class);
        $staffRoles = implode(',', $provisioning->staffRoles());

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', 'string', Rule::in($provisioning->assignableRoles($this->user()))],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:Male,Female'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'staff_id_number' => ['nullable', "required_if:role,{$staffRoles}", 'string', 'max:30', 'unique:staff_profiles,staff_id_number'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'joined_date' => ['nullable', "required_if:role,{$staffRoles}", 'date'],
            'password' => ['nullable', 'confirmed', SecurityPolicy::passwordRule()],
        ];
    }
}
