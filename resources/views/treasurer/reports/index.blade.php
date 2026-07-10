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
            <x-chart.donut
                :segments="[
                    ['label' => 'Paid', 'value' => $distribution['paid'], 'color' => '#1F573D'],
                    ['label' => 'Partial', 'value' => $distribution['partial'], 'color' => '#A8841B'],
                    ['label' => 'Outstanding', 'value' => $distribution['outstanding'], 'color' => '#B0392B'],
                ]"
                :center="$distributionPct['paid'].'%'"
                center-label="paid" />
        </x-card>

        <x-card title="Outstanding balance by department">
            <x-chart.bar-list
                :items="collect($byDepartment)->map(fn ($row) => ['label' => $row->department, 'value' => $row->outstanding])"
                color="#B0392B" label-width="w-32" />
        </x-card>
    </div>

    <x-card title="Collection rate by period" subtitle="Collected share of billed amounts per import period.">
        <x-chart.bar-list
            :items="collect($byPeriod)->map(fn ($row) => ['label' => $row->period, 'value' => $row->rate, 'display' => $row->rate.'%'])"
            :max="100" label-width="w-24" />
    </x-card>

    <p class="text-xs text-neutral-400">
        Visible to: Principal, VP Academic, Registrar, Treasurer (read-only for all but Treasurer). Guardian/student
        view is own-record only, with restricted (SDA) items hidden.
    </p>
</x-app-layout>
