<?php

namespace App\Http\Requests\Registrar;

use Illuminate\Foundation\Http\FormRequest;

/** §7.2: a draft Statement of Marks' two dates. */
class UpdateStatementOfMarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route is limited to the Registrar role
    }

    public function rules(): array
    {
        return [
            'examination_date' => ['required', 'string', 'max:60'],
            'issue_date' => ['required', 'date'],
        ];
    }
}
