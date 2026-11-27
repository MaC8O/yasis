<?php

namespace App\Http\Requests\Teacher;

use App\Models\PersonalityRating;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The homeroom teacher's personality ratings for a term: ratings[student id][trait] = A|B|C or blank. */
class SavePersonalityRatingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the controller checks the homeroom teacher
    }

    public function rules(): array
    {
        return [
            'ratings' => ['array'],
            'ratings.*' => ['array'],
            'ratings.*.*' => ['nullable', Rule::in(PersonalityRating::RATINGS)],
        ];
    }

    public function messages(): array
    {
        return ['ratings.*.*.in' => 'Ratings are A, B or C.'];
    }
}
