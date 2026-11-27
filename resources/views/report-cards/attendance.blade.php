@php $role = auth()->user()->getRoleNames()->first(); @endphp
<x-app-layout title="Attendance below the minimum" :subtitle="'Students below '.$minimum.'% attendance this term (section 8 of the school\'s rules). Flagged only: the system never blocks a student; a person decides.'" :badge="ucfirst(str_replace('_', ' ', $role))" :role="$role">
    <x-card>
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div>
                <label for="att-term" class="block text-sm font-semibold mb-1">Term</label>
                <select id="att-term" name="term" onchange="this.form.submit()" class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($terms as $t)
                        <option value="{{ $t->id }}" @selected($term && $t->id === $term->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </x-card>

    <x-card :title="count($below).' '.Str::plural('student', count($below)).' below '.$minimum.'%'" subtitle="Attended (Present and Tardy) over the days the student has a record for, with Excused days left out.">
        @if ($below === [])
            <p class="text-sm text-neutral-400">No student is below the minimum this term.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" data-below-list>
                    <thead><tr class="text-left text-neutral-500 border-b border-neutral-200"><th class="py-2 pr-3 font-semibold">Student</th><th class="py-2 pr-3 font-semibold">Class</th><th class="py-2 pr-3 font-semibold">Attendance</th><th class="py-2 font-semibold">Report card</th></tr></thead>
                    <tbody>
                        @foreach ($below as $row)
                            <tr class="border-b border-neutral-100">
                                <td class="py-2 pr-3 font-medium">{{ $row['student'] }}</td>
                                <td class="py-2 pr-3">{{ $row['section'] }}</td>
                                <td class="py-2 pr-3 text-warning font-semibold">{{ $row['pct'] }}% <span class="font-normal text-neutral-500">({{ $row['attended'] }} of {{ $row['counted'] }} days)</span></td>
                                <td class="py-2"><a href="{{ route('report-cards.show', [$row['section_id'], $term->id, $row['student_id']]) }}" class="text-brand font-semibold hover:underline">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-app-layout>
