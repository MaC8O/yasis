<x-app-layout title="Fee Categories" subtitle="How a ledger line's category is worked out from its memo: categories are checked from the top, and the first keyword found wins." badge="Treasurer" role="treasurer">
    <form method="POST" action="{{ route('treasurer.ledger.categories.update') }}" class="space-y-6">
        @csrf @method('PUT')
        <x-card title="Categories and keywords" subtitle="Keywords match anywhere in the memo, upper or lower case. Switch a keyword off rather than deleting it.">
            <div class="divide-y divide-neutral-100">
                @foreach ($categories as $category)
                    <div class="py-4 grid gap-3 md:grid-cols-[14rem_1fr]" data-category="{{ $category->key }}">
                        <div>
                            <p class="font-semibold">{{ $category->label }} <span class="text-xs font-normal text-neutral-500">· {{ number_format($counts[$category->key] ?? 0) }} {{ Str::plural('line', $counts[$category->key] ?? 0) }}</span></p>
                            @if ($category->key !== 'unclassified')
                                <input type="hidden" name="restricted[{{ $category->id }}]" value="0">
                                <label class="mt-1 flex items-center gap-2 text-sm min-h-[40px]">
                                    <input type="checkbox" name="restricted[{{ $category->id }}]" value="1" @checked($category->restricted_by_default) class="rounded border-neutral-300 text-brand">
                                    Hidden from families by default
                                </label>
                                @if ($category->pending_question)
                                    <span class="rounded-full bg-tint-yellow text-tint-yellow-ink px-2 py-0.5 text-[11px] font-semibold">Default awaiting the Treasurer's answer ({{ $category->pending_question }})</span>
                                @endif
                            @else
                                <p class="text-xs text-neutral-500">Lines no rule matches. They are listed on Ledger Review.</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($category->rules as $rule)
                                @if ($rule->cr_credit)
                                    <span class="inline-flex items-center gap-2 rounded-lg border border-neutral-200 px-2 py-1 text-xs">
                                        <input type="hidden" name="rules[{{ $rule->id }}][active]" value="0">
                                        <label class="flex items-center gap-1"><input type="checkbox" name="rules[{{ $rule->id }}][active]" value="1" @checked($rule->active)> any credit in a CR (cash receipt) journal{{ $rule->position ? ', checked right after Brought forward' : '' }}</label>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-lg border border-neutral-200 px-2 py-1 {{ $rule->active ? '' : 'opacity-60' }}">
                                        <label class="sr-only" for="kw-{{ $rule->id }}">Keyword</label>
                                        <input id="kw-{{ $rule->id }}" type="text" name="rules[{{ $rule->id }}][keyword]" value="{{ $rule->keyword }}" maxlength="60" class="w-32 border-0 p-0 text-xs focus:ring-0">
                                        <input type="hidden" name="rules[{{ $rule->id }}][active]" value="0">
                                        <label class="flex items-center gap-1 text-[11px] text-neutral-500"><input type="checkbox" name="rules[{{ $rule->id }}][active]" value="1" @checked($rule->active)> on</label>
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        <x-card title="Add a keyword">
            <div class="flex flex-col sm:flex-row gap-3 sm:items-end">
                <div>
                    <label for="new-cat" class="block text-sm font-semibold mb-1">Category</label>
                    <select id="new-cat" name="new[category_id]" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm min-h-[44px]">
                        <option value="">—</option>
                        @foreach ($categories->where('key', '!=', 'unclassified') as $category)<option value="{{ $category->id }}">{{ $category->label }}</option>@endforeach
                    </select>
                </div>
                <div class="flex-1">
                    <label for="new-kw" class="block text-sm font-semibold mb-1">Keyword</label>
                    <input id="new-kw" type="text" name="new[keyword]" maxlength="60" placeholder="e.g. tution" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm min-h-[44px]">
                </div>
                <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Save rules</button>
            </div>
        </x-card>
    </form>

    <x-card title="Re-apply the rules" subtitle="Works out the category again for every imported line you haven't set by hand, and whether families see it.">
        <form method="POST" action="{{ route('treasurer.ledger.categories.reapply') }}">
            @csrf
            <button type="submit" class="w-full sm:w-auto border border-brand text-brand font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-soft">Re-apply to imported lines</button>
        </form>
    </x-card>
</x-app-layout>
