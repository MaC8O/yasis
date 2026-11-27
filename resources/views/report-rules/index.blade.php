<x-app-layout title="Report Card Rules" :subtitle="$readOnly
        ? 'How report cards and the attendance check work. Set by the Admin; shown here read-only.'
        : 'How report cards and the attendance check work. Each default awaiting the school is flagged.'"
    :badge="$readOnly ? 'Principal' : 'Admin'" :role="$portal">

    <x-card title="Rules">
        @if ($readOnly)
            <dl class="divide-y divide-neutral-100 text-sm">
                @foreach ($rules as $rule)
                    @php [$label, $options] = \App\Models\ReportRule::RULES[$rule->key] ?? [$rule->key, null]; @endphp
                    <div class="py-3">
                        <dt class="text-neutral-500">{{ $label }}</dt>
                        <dd class="font-semibold">{{ is_array($options) ? ($options[$rule->value] ?? $rule->value) : ($options === 'text' ? $rule->value : $rule->value.'%') }}
                            @if ($rule->pending_question)<span class="ml-1 rounded-full bg-tint-yellow text-tint-yellow-ink px-2.5 py-0.5 text-xs font-semibold">Needs confirmation from the school ({{ $rule->pending_question }})</span>@endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        @else
            <form method="POST" action="{{ route('admin.report-rules.update') }}" class="space-y-5">
                @csrf @method('PUT')
                @foreach ($rules as $rule)
                    @php [$label, $options] = \App\Models\ReportRule::RULES[$rule->key] ?? [$rule->key, null]; @endphp
                    <div class="rounded-xl border border-neutral-200 p-4">
                        <label for="rule-{{ $rule->key }}" class="block text-sm font-semibold mb-1">{{ $label }}</label>
                        @if ($options === 'text')
                            <input id="rule-{{ $rule->key }}" name="values[{{ $rule->key }}]" type="text" maxlength="120" required value="{{ old('values.'.$rule->key, $rule->value) }}" class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-sm">
                        @elseif ($options)
                            <select id="rule-{{ $rule->key }}" name="values[{{ $rule->key }}]" class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-sm">
                                @foreach ($options as $value => $text)
                                    <option value="{{ $value }}" @selected(old('values.'.$rule->key, $rule->value) === $value)>{{ $text }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="rule-{{ $rule->key }}" name="values[{{ $rule->key }}]" type="number" min="1" max="100" value="{{ old('values.'.$rule->key, $rule->value) }}" class="w-32 rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-sm">
                        @endif
                        @if ($rule->pending_question)
                            <p class="mt-2 text-xs"><span class="rounded-full bg-tint-yellow text-tint-yellow-ink px-2.5 py-0.5 font-semibold">Needs confirmation from the school ({{ $rule->pending_question }})</span></p>
                            <label class="mt-2 flex items-center gap-2 text-sm min-h-[44px]">
                                <input type="checkbox" name="confirmed[]" value="{{ $rule->key }}" class="rounded border-neutral-300 text-brand">
                                Confirmed by the school (clears the {{ $rule->pending_question }} flag)
                            </label>
                        @endif
                    </div>
                @endforeach
                <button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Save rules</button>
            </form>
        @endif
    </x-card>

    <x-card title="Fixed by the school's card">
        <ul class="list-disc pl-5 text-sm text-neutral-600 space-y-1">
            <li>The "Final" column is the fourth term's own result. The year result is on the Statement of Marks (Q3).</li>
            <li>The "G.P.A" row holds the average (KG1 to Grade 9), the point total (Grade 10) or the GPA (Grades 11-12), labelled on the card (Q12).</li>
            <li>Tied students share a position and the next is skipped (Q13); where failed students are placed is set per grading scheme (Q20).</li>
        </ul>
    </x-card>
</x-app-layout>
