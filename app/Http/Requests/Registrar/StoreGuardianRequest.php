<?php

namespace App\Http\Requests\Registrar;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Registrar → Guardians → Add guardian. There is deliberately no role field: the account is
 * always a guardian, and any role sent with the request is ignored.
 */
class StoreGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is limited to the Registrar role
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'relationship' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'student_id_number' => ['nullable', 'exists:students,student_id_number'],
        ];
    }
}
