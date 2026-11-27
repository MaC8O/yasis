<?php

namespace App\Http\Requests\Treasurer;

use Illuminate\Foundation\Http\FormRequest;

/** A Sun Accounts Account Listing printed to a file (.PRN, plain text), up to 10 MB. */
class UploadSunListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route is limited to the Treasurer role
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:10240']];
    }

    public function messages(): array
    {
        return ['file.max' => 'The file is larger than 10 MB.', 'file.required' => 'Choose the Account Listing file printed from Sun.'];
    }
}
