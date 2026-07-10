<x-app-layout title="Fee Reports" subtitle="Downloadable and printable reports built from imported fee records." badge="Sun account, not Sun Plus" role="treasurer">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-stat-tile label="Outstanding total" color="yellow">{{ number_format($outstandingTotal) }}</x-stat-tile>
        <x-stat-tile label="Paid total" color="green">{{ number_format($paidTotal) }}</x-stat-tile>
        <x-stat-tile label="Students with balance" color="blue">{{ $studentsWithBalance }}</x-stat-tile>
    </div>

    <x-card title="Reports">
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('treasurer.reports.outstanding') }}" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">
                Download outstanding balance list (CSV)
            </a>
            <a href="{{ route('treasurer.records.index') }}" class="bg-neutral-900 text-white font-semibold rounded-lg px-5 py-2.5 text-sm">
                Open a student fee statement (PDF)
            </a>
        </div>
        <p class="text-xs text-neutral-400 mt-3">Reports are downloadable and printable; they are not transaction-entry screens.</p>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Fee status distribution" subtitle="Share of total billed amount, by status.">
            <div class="flex items-center gap-8">
                <div class="relative w-40 h-40 shrink-0 rounded-full"
                    style="background: conic-gradient(#0ca30c 0% {{ $distributionPct['paid'] }}%, #fab219 {{ $distributionPct['paid'] }}% {{ $distributionPct['paid'] + $distributionPct['partial'] }}%, #d03b3b {{ $distributionPct['paid'] + $distributionPct['partial'] }}% 100%);">
                    <div class="absolute inset-3 rounded-full bg-white flex items-center justify-center flex-col">
                        <span class="text-2xl font-bold">{{ $distributionPct['paid'] }}%</span>
                        <span class="text-xs text-neutral-500">paid</span>
                    </div>
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full inline-block" style="background:#0ca30c"></span> Paid — {{ number_format($distribution['paid']) }} ({{ $distributionPct['paid'] }}%)</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full inline-block" style="background:#fab219"></span> Partial — {{ number_format($distribution['partial']) }} ({{ $distributionPct['partial'] }}%)</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full inline-block" style="background:#d03b3b"></span> Outstanding — {{ number_format($distribution['outstanding']) }} ({{ $distributionPct['outstanding'] }}%)</div>
                </div>
            </div>
        </x-card>

        <x-card title="Outstanding balance by department">
            @php $maxDept = $byDepartment->max('outstanding') ?: 1; @endphp
            <div class="space-y-3">
                @forelse ($byDepartment as $row)
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>{{ $row->department }}</span>
                            <span class="font-semibold">{{ number_format($row->outstanding) }}</span>
                        </div>
                        <div class="h-3 rounded-full bg-neutral-100 overflow-hidden">
                            <div class="h-full rounded-full" style="width: {{ $row->outstanding / $maxDept * 100 }}%; background:#2a78d6"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-neutral-400">No outstanding balances.</p>
                @endforelse
            </div>
        </x-card>
    </div>

    <x-card title="Collection rate by period">
        <div class="space-y-3">
            @forelse ($byPeriod as $row)
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span>{{ $row->period }}</span>
                        <span class="font-semibold">{{ $row->rate }}%</span>
                    </div>
                    <div class="h-3 rounded-full bg-neutral-100 overflow-hidden">
                        <div class="h-full rounded-full" style="width: {{ $row->rate }}%; background:#2a78d6"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-neutral-400">No published periods yet.</p>
            @endforelse
        </div>
    </x-card>

    <p class="text-xs text-neutral-400">
        Visible to: Principal, VP Academic, Registrar, Treasurer (read-only for all but Treasurer). Guardian/student
        view is own-record only, with restricted (SDA) items hidden.
    </p>
</x-app-layout>
