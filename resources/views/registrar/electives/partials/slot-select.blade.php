@php
    $chosen = old("choices.{$student->id}.{$slot}", $choices[$student->id.'|'.$slot] ?? null);
    $hasSaved = isset($choices[$student->id.'|'.$slot]);
    $error = $errors->first("choices.{$student->id}.{$slot}");
@endphp
<select id="{{ $view }}-{{ $student->id }}-{{ $slot }}" name="choices[{{ $student->id }}][{{ $slot }}]" data-elective="{{ $view }}"
        aria-label="Slot {{ $slot }} for {{ $student->name }}"
        class="w-full rounded-lg border {{ $error ? 'border-danger' : 'border-neutral-200' }} bg-neutral-50 px-3 py-2.5 text-sm">
    @unless ($hasSaved)
        <option value="">Not chosen</option>
    @endunless
    @foreach ($options as $option)
        <option value="{{ $option->id }}" @selected((string) $chosen === (string) $option->id)>{{ $option->subject->name }}</option>
    @endforeach
</select>
@if ($error)
    <p class="mt-1 text-xs text-danger">{{ $error }}</p>
@endif
