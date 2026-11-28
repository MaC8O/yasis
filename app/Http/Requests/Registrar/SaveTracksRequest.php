<?php

namespace App\Http\Requests\Registrar;

use App\Services\TrackService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The Registrar's tracks for one section: tracks[studentId] = track. Every student must be actively
 * enrolled in the section, every track must be one the section's grade level offers, and a track
 * already set can be changed but not cleared.
 */
class SaveTracksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is already limited to the Registrar role
    }

    public function rules(): array
    {
        return [
            'tracks' => ['required', 'array'],
            'tracks.*' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $section = $this->route('section');
            $service = app(TrackService::class);
            $offered = $service->tracksFor($section)->keys()->all();
            $roster = $service->roster($section)->keyBy('id');
            $current = $service->current($section, $roster->keys());

            foreach ($this->input('tracks', []) as $studentId => $track) {
                $student = $roster->get((int) $studentId);
                if (! $student) {
                    $validator->errors()->add('tracks', 'One of the students is not actively enrolled in this section. Reload the page and try again.');

                    return;
                }
                if ($track === null || $track === '') {
                    if ($current->has($student->id)) {
                        $validator->errors()->add("tracks.{$student->id}", "{$student->name}: a track can be changed but not cleared.");
                    }

                    continue;
                }
                if (! in_array($track, $offered, true)) {
                    $validator->errors()->add("tracks.{$student->id}", "{$student->name}: choose one of this grade's tracks.");
                }
            }
        }];
    }
}
