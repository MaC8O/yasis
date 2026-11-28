<x-app-layout title="Visibility Rules" subtitle="Who can see the Sun ledger: generated from the rules every fee page applies." badge="Enforced" role="treasurer">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4" data-rules>
        @foreach ($rules as $audience => $rule)
            <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden flex flex-col">
                <div class="px-5 py-4 border-b border-neutral-200">
                    <p class="font-bold text-ink">{{ $rule['label'] }}</p>
                    <p class="text-xs text-neutral-500">{{ $rule['roles'] }}</p>
                </div>
                <div class="px-5 py-4 space-y-3 text-sm flex-1">
                    <ul class="space-y-1.5">
                        @foreach ($rule['sees'] as $item)
                            <li class="flex gap-2"><x-icon name="check" class="w-4 h-4 mt-0.5 text-success" /><span>{{ $item }}</span></li>
                        @endforeach
                    </ul>
                    @if ($rule['hidden'])
                        <ul class="space-y-1.5 pt-3 border-t border-neutral-100">
                            @foreach ($rule['hidden'] as $item)
                                <li class="flex gap-2 text-neutral-500"><x-icon name="x" class="w-4 h-4 mt-0.5 text-danger" /><span>{{ $item }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <x-card title="Right now">
        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div><dt class="text-neutral-500">Ledger lines</dt><dd class="text-xl font-bold tabular-nums">{{ number_format($counts['lines']) }}</dd></div>
            <div><dt class="text-neutral-500">Hidden from families by hand</dt><dd class="text-xl font-bold tabular-nums">{{ number_format($counts['hidden']) }}</dd></div>
            <div><dt class="text-neutral-500">Published family statements</dt><dd class="text-xl font-bold tabular-nums">{{ number_format($counts['statements']) }}</dd></div>
        </dl>
    </x-card>

    <x-card title="Lines hidden by hand, and leadership" subtitle="Families see every line, allowances included (the Treasurer's answer to T4). You can still hide an exceptional line; families then see it only inside one &quot;Adjustments&quot; amount, so their totals still match the ledger. You decide whether read-only leadership sees those lines.">
        <form method="POST" action="{{ route('treasurer.info.visibility-policy') }}" class="flex flex-wrap items-center justify-between gap-4">
            @csrf @method('PUT')
            <div class="text-sm">
                <p class="font-semibold text-ink">
                    Currently:
                    @if ($leadershipSeesRestricted)
                        <span class="text-warning">leadership sees lines hidden from families, marked</span>
                    @else
                        <span class="text-success">leadership sees them as "Adjustments", like families</span>
                    @endif
                </p>
                <p class="text-neutral-500 mt-0.5">Default: as families see them (§2.2).</p>
            </div>
            <input type="hidden" name="leadership_sees_restricted" value="{{ $leadershipSeesRestricted ? 0 : 1 }}">
            <button type="submit" class="{{ $leadershipSeesRestricted ? 'bg-brand text-white hover:bg-brand-dark' : 'border border-warning-line bg-warning-soft text-warning hover:bg-tint-yellow' }} font-semibold rounded-lg px-4 py-2.5 text-sm min-h-[44px]">
                {{ $leadershipSeesRestricted ? 'Show them to leadership as Adjustments' : 'Show them to leadership, marked' }}
            </button>
        </form>
    </x-card>
</x-app-layout>
