{{-- Section 9: decisions in a promotion batch that differ from the calculated recommendation, with the Registrar's reason. --}}
@php
    $overrides = $batch->items->filter(fn ($item) => $item->recommended_action !== null && $item->action !== $item->recommended_action);
@endphp
@if ($overrides->isNotEmpty())
    <div class="mt-2 rounded-lg border border-warning-line bg-warning-soft px-3 py-2 text-xs text-warning">
        <p class="font-semibold">{{ $overrides->count() }} {{ $overrides->count() === 1 ? 'decision differs' : 'decisions differ' }} from the recommendation</p>
        <ul class="mt-1 space-y-0.5">
            @foreach ($overrides as $item)
                <li>{{ $item->student->name }}: {{ $item->action }} (recommended {{ $item->recommended_action }}). {{ $item->override_reason }}</li>
            @endforeach
        </ul>
    </div>
@endif
