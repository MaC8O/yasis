<?php

namespace App\Http\Requests\Behavior;

use Illuminate\Foundation\Http\FormRequest;

/** VP → Deduct on a report, or Restore part of a deduction. */
class PointsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // routes are VP-only; the service re-checks
    }

    public function rules(): array
    {
        return PointsAndReason::rules();
    }

    public function messages(): array
    {
        return PointsAndReason::messages();
    }
}
