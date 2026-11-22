<?php

namespace App\Http\Requests\Registrar;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Registrar → Transfer/Drop. When the student has a pending exit request, the form shows it and
 * the Registrar ticks it only if this exit is that request; otherwise the two stay unlinked.
 */
class TransferStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is limited to the Registrar role
    }

    public function rules(): array
    {
        return ['exit_request_id' => ['nullable', 'integer', 'exists:student_exit_requests,id']];
    }
}
