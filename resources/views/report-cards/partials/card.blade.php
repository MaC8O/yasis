{{-- The student's progress report on screen (one responsive column). $card from ReportCardService::build(). --}}
@php
    $accent = ['blue' => 'border-tint-blue-ink text-tint-blue-ink bg-tint-blue', 'green' => 'border-tint-green-ink text-tint-green-ink bg-tint-green', 'yellow' => 'border-tint-yellow-ink text-tint-yellow-ink bg-tint-yellow'][$card['accent']];
    $cols = $card['columns'];
    $labels = [1 => 'FIRST', 2 => 'SECOND', 3 => 'THIRD', 4 => 'FINAL'];
    $roles = ['guardian' => 'Parent(s)/Guardian', 'class_teacher' => 'Class Teacher', 'principal' => 'Principal'];
@endphp
<div class="space-y-4" data-report-card="{{ $card['layout'] }}">
    <div class="rounded-2xl border-2 {{ $accent }} px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide">Student's progress report · {{ $card['layoutTitle'] }}</p>
        <p class="mt-1 text-lg font-bold text-ink">YANGON ADVENTIST SEMINARY INTERNATIONAL SCHOOL</p>
        <p class="text-sm text-neutral-600">34 Natmauk Lane (1), Bahan · Phone: 95-787666619</p>
        <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 text-sm text-ink">
            <div><dt class="inline font-semibold">Student's name:</dt> <dd class="inline">{{ $card['student']->name }}</dd></div>
            <div><dt class="inline font-semibold">Grade:</dt> <dd class="inline">{{ $card['gradeName'] }}</dd></div>
            <div><dt class="inline font-semibold">School year:</dt> <dd class="inline">{{ $card['schoolYear'] }}</dd></div>
            <div><dt class="inline font-semibold">Class teacher:</dt> <dd class="inline">{{ $card['classTeacher'] ?? '—' }}</dd></div>
        </dl>
    </div>

    @if ($card['provisional'])
        <div class="rounded-xl border border-warning-line bg-warning-soft px-4 py-3 text-sm text-warning" data-provisional>
            <span class="font-semibold">Provisional.</span> At least one term on this report is not locked yet, so its figures can still change.
        </div>
    @endif

    <x-card title="Scholarship record">
        <div class="overflow-x-auto">
            <table class="w-full text-sm" data-scholarship>
                <thead>
                    <tr class="text-left border-b border-neutral-200 text-neutral-500">
                        <th class="py-2 pr-3 font-semibold">MAX. MARKS (100)</th>
                        @if ($card['hasHours']) <th class="py-2 px-2 font-semibold text-center">HRS</th> @endif
                        @foreach ($cols as $seq => $c)
                            <th class="py-2 px-2 font-semibold text-center">{{ $labels[$seq] ?? $c['term']->name }}@if ($c['shown'] && ! $c['frozen'])<span class="block text-[10px] font-normal text-warning">provisional</span>@endif</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($card['subjects'] as $row)
                        <tr class="border-b border-neutral-100">
                            <td class="py-1.5 pr-3 font-medium">{{ $row['label'] }}</td>
                            @if ($card['hasHours']) <td class="py-1.5 px-2 text-center">{{ $row['hours'] }}</td> @endif
                            @foreach ($row['cells'] as $cell) <td class="py-1.5 px-2 text-center">{{ $cell }}</td> @endforeach
                        </tr>
                    @endforeach
                    @foreach ([[$card['gpaLabel'], 'gpaRow'], ['POSITION', 'positionRow'], ['TOTAL NUMBER IN CLASS', 'totalRow'], ['DAYS ABSENT*', 'daysAbsentRow']] as [$label, $key])
                        <tr class="border-b border-neutral-100 bg-neutral-50 font-semibold" data-row="{{ $key }}">
                            <td class="py-1.5 pr-3">{{ $label }}</td>
                            @if ($card['hasHours']) <td></td> @endif
                            @foreach ($card[$key] as $cell) <td class="py-1.5 px-2 text-center">{{ $cell }}</td> @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-neutral-600">* Any student who has more than ten per cent absence in a subject in a term may be denied permission to write the final examination. (Or) A minimum of 80% attendance is required to sit for any examination.</p>
        @foreach ($card['notes'] as $note)
            <p class="mt-1 text-xs text-neutral-500">{{ $note }}</p>
        @endforeach
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <x-card title="Student's personality record" :subtitle="$card['personality']['term'] ? 'Rated in '.$card['personality']['term']->name.'.' : 'Not rated yet.'">
            <table class="w-full text-sm" data-personality>
                <thead><tr class="border-b border-neutral-200 text-neutral-500"><th class="py-1.5 text-left font-semibold">DESCRIPTION</th>@foreach (\App\Models\PersonalityRating::RATINGS as $r)<th class="py-1.5 w-12 text-center font-semibold">{{ $r }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach (\App\Models\PersonalityRating::TRAITS as $key => $trait)
                        <tr class="border-b border-neutral-100">
                            <td class="py-1.5">{{ $trait }}</td>
                            @foreach (\App\Models\PersonalityRating::RATINGS as $r)
                                <td class="py-1.5 text-center">@if (($card['personality']['ratings'][$key] ?? null) === $r)<span aria-label="Rated {{ $r }}">✓</span>@endif</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>

        <x-card title="Acknowledgement signature">
            <table class="w-full text-sm" data-acknowledgements>
                <thead><tr class="border-b border-neutral-200 text-neutral-500 text-left"><th class="py-1.5 font-semibold">Reports</th><th class="py-1.5 font-semibold">Description</th><th class="py-1.5 font-semibold">Signature</th></tr></thead>
                <tbody>
                    @foreach ($cols as $seq => $c)
                        @foreach ($roles as $role => $roleLabel)
                            @php $sig = $card['acknowledgements'][$seq][$role] ?? null; @endphp
                            <tr class="{{ $loop->last ? 'border-b border-neutral-200' : '' }}">
                                <td class="py-1 pr-2 font-medium">{{ $loop->first ? ucfirst(strtolower($labels[$seq] ?? $c['term']->name)).' Report' : '' }}</td>
                                <td class="py-1 pr-2 text-neutral-600">{{ $roleLabel }}</td>
                                <td class="py-1 text-neutral-800">{{ $sig ? $sig['name'].', '.$sig['date'] : '' }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </x-card>
    </div>

    @if ($card['term']->sequence === max(array_keys($cols)))
        <x-card title="Class placement next year">
            <p class="text-sm" data-placement>
                Assigned to Grade <span class="font-semibold">{{ $card['placement']['grade'] ?? '_______' }}</span>
                for School Year <span class="font-semibold">{{ $card['placement']['year'] ?? '__________' }}</span>.
                Beginning <span class="font-semibold">{{ $card['placement']['beginning'] ?? '_________' }}</span>.
            </p>
        </x-card>
    @endif

    <p class="text-xs text-neutral-500">This progress report is sent to you once in every nine week of the school year. This is to report to you and get your acknowledgement of your child's performance in the class. Please check the record carefully, and if you feel that your child needs help, or if you do not fully understand this report, contact the teacher in order to plan ways of promoting your child's progress.</p>
</div>
