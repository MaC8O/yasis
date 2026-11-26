<x-app-layout title="Prepare Promotion Batch" :subtitle="$section->name.' — '.$section->department->name" badge="Registrar" role="registrar">
    @if ($blocker)
        <div id="promotion-blocker" role="note" class="rounded-xl border border-warning-line bg-warning-soft px-4 py-3 text-sm text-warning">
            <p class="font-semibold">Provisional: this batch can't be submitted yet</p>
            <p class="mt-1">{{ $blocker }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('registrar.promotions.store', $section) }}">
        @csrf
        <x-card title="Roster actions" subtitle="Choose Promote / Retain / Graduate for each student. Each decision starts at the calculated recommendation; a different decision needs a reason, which the VP and Principal see.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-neutral-500 border-b border-neutral-200">
                            <th class="py-2 pr-3 font-semibold">Student</th>
                            <th class="py-2 pr-3 font-semibold">Recommendation</th>
                            <th class="py-2 pr-3 font-semibold">Action</th>
                            <th class="py-2 font-semibold">Target section</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($section->enrollments as $i => $enrollment)
                            @php
                                $rec = $recommendations[$enrollment->student_id] ?? ['action' => null, 'basis' => 'No recommendation.'];
                                $chosen = old("actions.{$i}.action", $rec['action'] ?? 'Promote');
                            @endphp
                            <tr class="border-b border-neutral-100 last:border-0 align-top" x-data="{ action: @js($chosen), rec: @js($rec['action']) }">
                                <td class="py-2.5 pr-3">
                                    {{ $enrollment->student->name }}
                                    <input type="hidden" name="actions[{{ $i }}][student_id]" value="{{ $enrollment->student_id }}">
                                </td>
                                <td class="py-2.5 pr-3">
                                    @if ($rec['action'])
                                        <span class="font-semibold">{{ $rec['action'] }}</span>
                                        <span class="block text-xs text-neutral-500">{{ $rec['basis'] }}</span>
                                    @else
                                        <span class="text-xs text-neutral-500">{{ $rec['basis'] }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-3">
                                    <label for="action-{{ $i }}" class="sr-only">Action for {{ $enrollment->student->name }}</label>
                                    <select id="action-{{ $i }}" name="actions[{{ $i }}][action]" x-model="action" class="rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 text-xs min-h-[36px]">
                                        @foreach (['Promote', 'Retain', 'Graduate'] as $option)
                                            <option value="{{ $option }}" @selected($chosen === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                    <div x-show="rec && action !== rec" x-cloak class="mt-2">
                                        <label for="reason-{{ $i }}" class="block text-xs font-semibold mb-1">Why not {{ $rec['action'] }}? (required)</label>
                                        <textarea id="reason-{{ $i }}" name="actions[{{ $i }}][override_reason]" rows="2" maxlength="1000"
                                                  class="w-full min-w-[14rem] rounded-lg border border-neutral-200 bg-white px-2 py-1.5 text-xs">{{ old("actions.{$i}.override_reason") }}</textarea>
                                    </div>
                                </td>
                                <td class="py-2.5">
                                    <label for="target-{{ $i }}" class="sr-only">Target section for {{ $enrollment->student->name }}</label>
                                    <select id="target-{{ $i }}" name="actions[{{ $i }}][to_section_id]" class="rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 text-xs min-h-[36px]">
                                        <option value="">—</option>
                                        @foreach ($targetSections as $target)
                                            <option value="{{ $target->id }}" @selected(old("actions.{$i}.to_section_id") == $target->id)>{{ $target->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-4 text-neutral-400">No students enrolled in this section.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-neutral-400">Recommendations come from the year's results (section 9 of the school's rules). “Provisional” means the year is not frozen yet: some terms are still unlocked, and the batch can't be submitted until they are.</p>
        </x-card>

        <div class="flex gap-3 mt-6">
            @if ($blocker)
                <button type="submit" disabled aria-describedby="promotion-blocker" title="{{ $blocker }}"
                        class="bg-neutral-300 text-neutral-600 font-semibold rounded-lg px-6 py-3 text-sm cursor-not-allowed">Prepare batch</button>
                <span class="text-xs text-neutral-500 self-center">Disabled until every term is locked (see above).</span>
            @else
                <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-6 py-3 text-sm hover:bg-brand-dark transition-colors">Prepare batch</button>
            @endif
            <a href="{{ route('registrar.promotions.index') }}" class="text-sm font-semibold text-neutral-500 self-center">Cancel</a>
        </div>
    </form>
</x-app-layout>
