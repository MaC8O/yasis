<?php

namespace App\Http\Requests\Registrar;

use Illuminate\Foundation\Http\FormRequest;

/** §7.2: the Registrar prepares a Statement of Marks: the student, the school year and the two dates. */
class PrepareStatementOfMarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route is limited to the Registrar role
    }

    public function rules(): array
    {
        return [
            'student_id_number' => ['required', 'string', 'exists:students,student_id_number'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'examination_date' => ['required', 'string', 'max:60'],
            'issue_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id_number.exists' => 'No student has that ID.',
            'examination_date.required' => 'Enter the date of examination as it should print, for example "MARCH 2-5, 2027".',
        ];
    }
}
