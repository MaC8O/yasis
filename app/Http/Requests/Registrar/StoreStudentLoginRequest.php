<?php

namespace App\Http\Requests\Registrar;

use Illuminate\Foundation\Http\FormRequest;

/** Registrar → Student record → Create portal login. The account is always a student account. */
class StoreStudentLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is limited to the Registrar role
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ];
    }
}
