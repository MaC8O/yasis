<?php

namespace App\Http\Requests\Admin;

use App\Services\UserAccountService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin → User Management → Edit. Status is not edited here: it has its own deactivate/reactivate actions. */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is limited to the Admin role; the service re-checks the role scope
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'role' => ['required', 'string', Rule::in(app(UserAccountService::class)->manageableRoles($this->user()))],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:Male,Female'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
