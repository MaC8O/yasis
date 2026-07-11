@props(['default' => 15])
@php
    $options = \App\Support\PerPage::OPTIONS;
    $current = request('per_page', (string) $default);
@endphp
{{-- "Show N per page" control. Submits as a GET form, carrying the page's other
     filters as hidden fields so changing the size never drops an active filter. --}}
<form method="GET" {{ $attributes->merge(['class' => 'flex items-center gap-2 text-sm']) }}>
    @foreach (request()->except(['per_page', 'page']) as $key => $value)
        @if (is_array($value))
            @foreach ($value as $v)
                <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
            @endforeach
        @else
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
    <span class="text-neutral-500">Show</span>
    <select name="per_page" onchange="this.form.submit()"
            class="rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 text-sm">
        @foreach ($options as $opt)
            <option value="{{ $opt }}" @selected((string) $current === (string) $opt)>{{ $opt }}</option>
        @endforeach
        <option value="all" @selected($current === 'all')>All</option>
    </select>
    <span class="text-neutral-500">per page</span>
</form>
