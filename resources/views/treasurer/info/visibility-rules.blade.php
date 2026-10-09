<x-app-layout title="Visibility Rules" subtitle="Who can see imported fee records — enforced on every fee page, report and statement." badge="Enforced" role="treasurer">
    {{-- Live matrix: generated from App\Support\FeeVisibility, the same rules the portals apply --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        @foreach ($rules as $audience => $rule)
            <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden flex flex-col">
                <div class="px-5 py-4 border-b border-neutral-200">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-bold text-ink">{{ $rule['label'] }}</p>
                            <p class="text-xs text-neutral-500">{{ $rule['roles'] }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-bold text-ink tabular-nums leading-none">{{ number_format($exposure[$audience]) }}</p>
                            <p class="text-[11px] text-neutral-500">rows visible now</p>
                        </div>
                    </div>
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

    {{-- Policy switch --}}
    <x-card title="Restricted (SDA) rows and leadership" subtitle="SDA student discounts and allowances are always hidden from guardians. You decide whether read-only leadership may see them.">
        <form method="POST" action="{{ route('treasurer.info.visibility-policy') }}" class="flex flex-wrap items-center justify-between gap-4">
            @csrf @method('PUT')
            <div class="text-sm">
                <p class="font-semibold text-ink">
                    Currently:
                    @if ($leadershipSeesRestricted)
                        <span class="text-warning">leadership can see restricted rows (flagged)</span>
                    @else
                        <span class="text-success">restricted rows are hidden from leadership</span>
                    @endif
                </p>
                <p class="text-neutral-500 mt-0.5">Default: hidden — the permission table (§2.2) says read-only viewers never see restricted rows.</p>
            </div>
            <input type="hidden" name="leadership_sees_restricted" value="{{ $leadershipSeesRestricted ? 0 : 1 }}">
            <button type="submit" class="{{ $leadershipSeesRestricted ? 'bg-brand text-white hover:bg-brand-dark' : 'border border-warning-line bg-warning-soft text-warning hover:bg-tint-yellow' }} font-semibold rounded-lg px-4 py-2.5 text-sm transition-colors"
                    onclick="return confirm('{{ $leadershipSeesRestricted ? 'Hide restricted rows from leadership?' : 'Let Principal, VP and Registrar see restricted (SDA) rows? Guardians will still never see them.' }}');">
                {{ $leadershipSeesRestricted ? 'Hide from leadership' : 'Show to leadership' }}
            </button>
        </form>
    </x-card>

    {{-- Per-import confirmation --}}
    <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-neutral-200">
            <h2 class="text-lg font-bold text-ink">Restricted classification per import</h2>
            <p class="text-sm text-neutral-500 mt-1">Confirm each batch's SDA rows are correctly marked. Publishing requires it, and changing any row's restriction asks for it again.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider text-neutral-500 border-b border-neutral-200 bg-neutral-50">
                        <th class="py-2 pl-5 pr-2 text-left font-semibold">Batch</th>
                        <th class="py-2 px-2 text-right font-semibold">Rows</th>
                        <th class="py-2 px-2 text-right font-semibold">Restricted</th>
                        <th class="py-2 px-2 text-right font-semibold">Held</th>
                        <th class="py-2 px-2 text-left font-semibold">Published</th>
                        <th class="py-2 px-2 text-left font-semibold">Classification</th>
                        <th class="py-2 pl-2 pr-5 text-right font-semibold"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($batches as $batch)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5 pl-5 pr-2">
                                <p class="font-medium text-ink">{{ $batch->period }}</p>
                                <p class="text-xs text-neutral-400">#{{ $batch->id }} · {{ $batch->source_file }}</p>
                            </td>
                            <td class="py-2.5 px-2 text-right tabular-nums">{{ $batch->imported_fee_records_count }}</td>
                            <td class="py-2.5 px-2 text-right tabular-nums {{ $batch->restricted_count ? 'font-semibold text-tint-purple-ink' : 'text-neutral-400' }}">{{ $batch->restricted_count }}</td>
                            <td class="py-2.5 px-2 text-right tabular-nums text-neutral-500">{{ $batch->held_count }}</td>
                            <td class="py-2.5 px-2 text-neutral-600 whitespace-nowrap">{{ $batch->published_at?->format('d M Y') ?? 'Draft' }}</td>
                            <td class="py-2.5 px-2">
                                @if ($batch->is_restricted_confirmed)
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-success"><x-icon name="check" class="w-3.5 h-3.5" />Confirmed</span>
                                    <p class="text-[11px] text-neutral-400">{{ $batch->restrictedConfirmedBy?->name }} · {{ $batch->restricted_confirmed_at->format('d M Y') }}</p>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-warning"><x-icon name="alert" class="w-3.5 h-3.5" />Needs confirmation</span>
                                @endif
                            </td>
                            <td class="py-2.5 pl-2 pr-5 text-right whitespace-nowrap">
                                <a href="{{ route('treasurer.validate.index', ['batch' => $batch->id]) }}" class="text-xs font-semibold text-neutral-600 hover:underline mr-3">Review rows</a>
                                <form method="POST" action="{{ route('treasurer.info.confirm-restricted', $batch) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-brand hover:underline">{{ $batch->is_restricted_confirmed ? 'Re-confirm' : 'Confirm' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 px-5 text-neutral-400">No import batches yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-card title="Boundary with the school's accounting system">
        <p class="text-sm text-neutral-600">
            The ISMS does not record payments, calculate fees, generate invoices, or transfer data back to the
            accounting system. It only displays records the finance office has already finalized and exported.
        </p>
    </x-card>
</x-app-layout>
