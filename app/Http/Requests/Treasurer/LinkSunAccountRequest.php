<?php

namespace App\Http\Requests\Treasurer;

use Illuminate\Foundation\Http\FormRequest;

/** §8: link a ledger account code to a student, by the student's ISMS ID. */
class LinkSunAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route is limited to the Treasurer role
    }

    public function rules(): array
    {
        return ['student_id_number' => ['required', 'string', 'exists:students,student_id_number']];
    }

    public function messages(): array
    {
        return ['student_id_number.exists' => 'No student has that ID.'];
    }
}
