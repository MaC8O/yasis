@php $role = auth()->user()->getRoleNames()->first(); @endphp
<x-app-layout title="Attendance check" :subtitle="'Students below '.$minimum.'% attendance, and students more than '.$subjectLimit.'% absent in a term (section 8 of the school\'s rules). Flagged only: the system never blocks a student; a person decides.'" :badge="(['vp_academic' => 'VP Academic'][$role] ?? ucfirst(str_replace('_', ' ', $role)))" :role="$role">
    <x-card>
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div>
                <label for="att-term" class="block text-sm font-semibold mb-1">Term</label>
                <select id="att-term" name="term" onchange="this.form.submit()" class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm min-h-[44px]">
                    @foreach ($terms as $t)
                        <option value="{{ $t->id }}" @selected($term && $t->id === $term->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
        <p class="mt-3 text-xs text-neutral-500" data-attendance-rules>
            Attended means Present or Late.
            {{ $rules['excused'] === 'count_as_absent' ? 'An excused absence counts as an absence.' : 'Excused days are left out.' }}
            @if ($rules['lates_per_absence'] > 0) Every {{ $rules['lates_per_absence'] }} late arrivals count as one absence. @endif
            Only the days a student has a record for count.
        </p>
    </x-card>

    <x-card :title="count($below).' '.Str::plural('student', count($below)).' below '.$minimum.'%'.($checksYear && $term ? ' in the school year up to '.$term->name : ($term ? ' in '.$term->name : ''))"
            :subtitle="$checksYear ? 'Checked over the school year so far, as the school decided; late arrivals are converted on the year\'s total.' : 'Checked over the term.'">
        @if ($below === [])
            <p class="text-sm text-neutral-400">No student is below the minimum.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" data-below-list>
                    <thead><tr class="text-left text-neutral-500 border-b border-neutral-200"><th class="py-2 pr-3 font-semibold">Student</th><th class="py-2 pr-3 font-semibold">Class</th><th class="py-2 pr-3 font-semibold">Attendance</th><th class="py-2 font-semibold">Report card</th></tr></thead>
                    <tbody>
                        @foreach ($below as $row)
                            <tr class="border-b border-neutral-100">
                                <td class="py-2 pr-3 font-medium">{{ $row['student'] }}</td>
                                <td class="py-2 pr-3">{{ $row['section'] }}</td>
                                <td class="py-2 pr-3 text-warning font-semibold">{{ $row['pct'] }}% <span class="font-normal text-neutral-500">({{ $row['attended'] }} of {{ $row['counted'] }} days{{ $row['late_absences'] ? ', '.$row['late_absences'].' from late arrivals' : '' }})</span></td>
                                <td class="py-2"><a href="{{ route('report-cards.show', [$row['section_id'], $term->id, $row['student_id']]) }}" class="text-brand font-semibold hover:underline">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-card :title="count($aboveSubject).' '.Str::plural('student', count($aboveSubject)).' more than '.$subjectLimit.'% absent'.($term ? ' in '.$term->name : '')"
            subtitle="The rule is per subject, per term. ISMS has no attendance per subject, so it uses the daily attendance and is the same for every subject.">
        @if ($aboveSubject === [])
            <p class="text-sm text-neutral-400">No student is above the limit this term.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" data-subject-absence-list>
                    <thead><tr class="text-left text-neutral-500 border-b border-neutral-200"><th class="py-2 pr-3 font-semibold">Student</th><th class="py-2 pr-3 font-semibold">Class</th><th class="py-2 pr-3 font-semibold">Absent</th><th class="py-2 font-semibold">Report card</th></tr></thead>
                    <tbody>
                        @foreach ($aboveSubject as $row)
                            <tr class="border-b border-neutral-100">
                                <td class="py-2 pr-3 font-medium">{{ $row['student'] }}</td>
                                <td class="py-2 pr-3">{{ $row['section'] }}</td>
                                <td class="py-2 pr-3 text-warning font-semibold">{{ $row['absent_pct'] }}% <span class="font-normal text-neutral-500">({{ $row['counted'] - $row['attended'] }} of {{ $row['counted'] }} days)</span></td>
                                <td class="py-2"><a href="{{ route('report-cards.show', [$row['section_id'], $term->id, $row['student_id']]) }}" class="text-brand font-semibold hover:underline">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-app-layout>
