@php
    $role = auth()->user()->getRoleNames()->first();
    $traits = \App\Models\PersonalityRating::TRAITS;
@endphp
<x-app-layout title="Report Cards" :subtitle="$isHomeroom ? 'Your class: personality ratings, attendance and confirming the term\'s reports.' : 'Every class\'s report cards, read-only. Open terms are provisional.'" :badge="(['vp_academic' => 'VP Academic'][$role] ?? ucfirst(str_replace('_', ' ', $role)))" :role="$role">
    <x-card>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
            <div>
                <label for="rc-section" class="block text-sm font-semibold mb-1">Class</label>
                <select id="rc-section" name="section" onchange="this.form.submit()" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($sections as $s)
                        <option value="{{ $s->id }}" @selected($s->id === $section->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="rc-term" class="block text-sm font-semibold mb-1">Term</label>
                <select id="rc-term" name="term" onchange="this.form.submit()" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($terms as $t)
                        <option value="{{ $t->id }}" @selected($t->id === $term->id)>{{ $t->name }}{{ $t->is_locked ? ' (locked)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <p class="text-sm {{ $term->is_locked ? 'text-success' : 'text-warning' }}">{{ $term->is_locked ? 'Locked: the cards show the frozen results.' : 'Open: the cards are provisional.' }}</p>
        </form>
    </x-card>

    @if ($below->isNotEmpty())
        <div class="rounded-xl border border-warning-line bg-warning-soft px-5 py-3 text-sm text-warning" data-below-minimum>
            <p class="font-semibold">Below the {{ $minimum }}% attendance minimum in {{ $term->name }}</p>
            <p class="mt-0.5">These students may be denied permission to sit the examinations. The system does not block anyone: a person decides.</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ($below as $row)
                    <li>{{ $row['student'] }}: {{ $row['pct'] }}% ({{ $row['attended'] }} of {{ $row['counted'] }} days)</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-card title="Students" :subtitle="$roster->count().' in '.$section->name">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 pr-3 font-semibold">Student</th>
                        <th class="py-2 pr-3 font-semibold">Attendance</th>
                        <th class="py-2 pr-3 font-semibold">Days absent</th>
                        <th class="py-2 pr-3 font-semibold">Class teacher</th>
                        <th class="py-2 pr-3 font-semibold">Guardian</th>
                        <th class="py-2 font-semibold">Report card</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roster as $student)
                        @php $att = $attendance[$student->id] ?? null; @endphp
                        <tr class="border-b border-neutral-100" data-student="{{ $student->id }}">
                            <td class="py-2 pr-3 font-medium">{{ $student->name }}</td>
                            <td class="py-2 pr-3 {{ $below->has($student->id) ? 'text-warning font-semibold' : '' }}">{{ $att && $att['pct'] !== null ? round((float) $att['pct'], 1).'%' : '—' }}@if ($below->has($student->id)) · below {{ $minimum }}%@endif</td>
                            <td class="py-2 pr-3">{{ \App\Services\AttendanceScoreService::daysAbsent($att) }}</td>
                            <td class="py-2 pr-3">{{ in_array($student->id, $confirmed) ? 'Confirmed' : '—' }}</td>
                            <td class="py-2 pr-3">{{ in_array($student->id, $guardianAcks) ? 'Acknowledged' : '—' }}</td>
                            <td class="py-2 whitespace-nowrap">
                                <a href="{{ route('report-cards.show', [$section, $term, $student]) }}" class="text-brand font-semibold hover:underline">View</a>
                                · <a href="{{ route('report-cards.pdf', [$section, $term, $student]) }}" target="_blank" class="text-brand font-semibold hover:underline">PDF</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    @if ($isHomeroom)
        <x-card title="Confirm {{ $term->name }} reports" subtitle="Your name and the date are printed where the class teacher signs.">
            @php $allConfirmed = $roster->isNotEmpty() && $roster->every(fn ($s) => in_array($s->id, $confirmed)); @endphp
            <form method="POST" action="{{ route('report-cards.confirm', [$section, $term]) }}">
                @csrf
                @if (! $term->is_locked)
                    <button type="submit" disabled aria-describedby="confirm-reason" class="bg-neutral-300 text-neutral-600 font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] cursor-not-allowed">Confirm reports</button>
                    <p id="confirm-reason" class="mt-2 text-sm text-neutral-500">You can confirm once the Principal has locked {{ $term->name }}, so you confirm the final figures.</p>
                @elseif ($allConfirmed)
                    <p class="text-sm text-success">Every report in {{ $section->name }} is confirmed.</p>
                @else
                    <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Confirm reports</button>
                @endif
            </form>
        </x-card>

        @php $onceAYear = \App\Models\ReportRule::personalityOncePerYear(); $lastTerm = $terms->last(); @endphp
        @if ($onceAYear && $term->id !== $lastTerm?->id)
        <x-card title="Personality record" data-personality-once>
            <p class="text-sm text-neutral-500">Traits are rated once a year, on {{ $lastTerm?->name }}, and printed on the final report. Earlier reports leave the block blank.</p>
            @if ($lastTerm)
                <a href="{{ route('report-cards.index', ['section' => $section->id, 'term' => $lastTerm->id]) }}" class="mt-3 inline-flex items-center min-h-[44px] px-4 rounded-lg border border-neutral-200 text-sm font-semibold hover:bg-neutral-50">Rate on {{ $lastTerm->name }}</a>
            @endif
        </x-card>
        @else
        <x-card :title="'Personality record · '.($onceAYear ? 'the year' : $term->name)" :subtitle="$term->is_locked ? 'Locked: these ratings can no longer change.' : ($onceAYear ? 'Rated once a year. Rate each trait A, B or C; the ratings print on the final report.' : 'Rate each trait A, B or C. Saved ratings print on the card.')">
            <form method="POST" action="{{ route('report-cards.personality', [$section, $term]) }}">
                @csrf @method('PUT')
                <div class="space-y-4">
                    @foreach ($roster as $student)
                        <fieldset class="rounded-xl border border-neutral-200 p-3">
                            <legend class="px-1 text-sm font-semibold">{{ $student->name }}</legend>
                            <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                                @foreach ($traits as $key => $label)
                                    @php $current = $ratings[$student->id][$key] ?? null; @endphp
                                    <div>
                                        <label for="r-{{ $student->id }}-{{ $key }}" class="block text-xs font-semibold mb-1">{{ ucwords(strtolower($label)) }}</label>
                                        <select id="r-{{ $student->id }}-{{ $key }}" name="ratings[{{ $student->id }}][{{ $key }}]" @disabled($term->is_locked)
                                                class="w-full rounded-lg border border-neutral-200 bg-white px-2 py-2 text-sm min-h-[40px]">
                                            <option value="">—</option>
                                            @foreach (\App\Models\PersonalityRating::RATINGS as $r)
                                                <option value="{{ $r }}" @selected($current === $r)>{{ $r }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
                @unless ($term->is_locked)
                    <button type="submit" class="mt-4 w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Save ratings</button>
                @endunless
            </form>
        </x-card>
        @endif
    @endif
</x-app-layout>
