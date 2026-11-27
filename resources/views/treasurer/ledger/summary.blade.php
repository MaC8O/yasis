@use('App\Support\LedgerFormat')
<x-app-layout title="Ledger Import" subtitle="Check the summary. Nothing is saved until you press Import." badge="Treasurer" role="treasurer">
    @if (! $token)
        <div class="rounded-xl border border-danger/30 bg-danger/5 px-5 py-4 text-sm" data-parse-errors>
            <p class="font-semibold text-danger">This file can't be imported: {{ $parsed['error_count'] }} {{ Str::plural('check', $parsed['error_count']) }} failed. Nothing was saved, and the upload was deleted.</p>
            <p class="mt-1 text-neutral-600">A failure means the file is cut short, was edited, or isn't an Account Listing. Print the report from Sun again and upload that file.</p>
            <ul class="mt-2 list-disc pl-5 text-neutral-700">
                @foreach ($parsed['errors'] as $error)
                    <li>{{ $error['line'] ? 'Line '.$error['line'].': ' : '' }}{{ $error['message'] }}</li>
                @endforeach
                @if ($parsed['error_count'] > count($parsed['errors']))
                    <li>…and {{ $parsed['error_count'] - count($parsed['errors']) }} more.</li>
                @endif
            </ul>
        </div>
        <a href="{{ route('treasurer.ledger.index') }}" class="inline-flex items-center min-h-[44px] text-sm font-semibold text-brand hover:underline">← Back to Ledger Import</a>
    @else
        @php $w = $summary['warnings']; @endphp
        <x-card :title="$fileName" :subtitle="'Financial year '.$summary['year_label'].' · periods '.$summary['period_from'].' to '.$summary['period_to'].' · printed '.($summary['printed_at'] ?? '—')">
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm" data-summary>
                <div><dt class="text-neutral-500">Accounts</dt><dd class="text-lg font-bold tabular-nums">{{ number_format($summary['accounts']) }}</dd><dd class="text-xs text-neutral-500">{{ $summary['account_from'] }} to {{ $summary['account_to'] }}</dd></div>
                <div><dt class="text-neutral-500">Lines</dt><dd class="text-lg font-bold tabular-nums">{{ number_format($summary['lines']) }}</dd><dd class="text-xs text-neutral-500">{{ number_format($summary['new_lines']) }} new</dd></div>
                <div><dt class="text-neutral-500">Matched to students</dt><dd class="text-lg font-bold tabular-nums">{{ number_format($summary['matched']) }}</dd><dd class="text-xs text-neutral-500">{{ number_format($summary['unmatched']) }} to match · {{ number_format($summary['new_accounts']) }} new codes</dd></div>
                <div><dt class="text-neutral-500">Unallocated in Student Control</dt><dd class="text-lg font-bold tabular-nums">{{ LedgerFormat::money($summary['control_balance']) }}</dd><dd class="text-xs text-neutral-500">Money received but not yet on a student's account</dd></div>
            </dl>
            <p class="mt-4 text-sm text-success">Every check passed: each running balance follows from the one before, each account ends on its closing balance, and the report sums to 0.00.</p>
        </x-card>

        @php
            // "1 line has" / "2 lines have": $say(count, singular phrase, plural phrase).
            $say = fn (int $n, string $one, string $many) => $n.' '.($n === 1 ? $one : $many);
            $notes = array_filter([
                $w['duplicate_file'] ? 'This exact file was imported before. Importing it again changes nothing unless lines were changed since.' : null,
                $w['opening_differs'] ? $say($w['opening_differs'], 'account starts', 'accounts start').' the year on a different balance from last year\'s closing balance in the ISMS.' : null,
                $w['suspect_dates'] ? $say($w['suspect_dates'], 'line has', 'lines have').' a typed date more than a month away from its accounting period. The period is used; the typed date is kept and marked.' : null,
                $w['renamed'] ? $say($w['renamed'], 'account has', 'accounts have').' a different name from last time.' : null,
                $w['unallocated'] ? $say($w['unallocated'], 'line is', 'lines are').' not allocated in Sun (no *).' : null,
                $w['changed_amounts'] ? $say($w['changed_amounts'], 'line already in the ISMS has', 'lines already in the ISMS have').' a different amount in this file.' : null,
                $w['missing_lines'] ? $say($w['missing_lines'], 'line in the ISMS for this year is not in this file. It is', 'lines in the ISMS for this year are not in this file. They are').' kept and marked, not deleted.' : null,
            ]);
        @endphp
        @if ($notes)
            <div class="rounded-xl border border-warning-line bg-warning-soft px-5 py-4 text-sm text-warning" data-warnings>
                <p class="font-semibold">{{ count($notes) }} {{ Str::plural('thing', count($notes)) }} to know before importing</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($notes as $note)<li>{{ $note }}</li>@endforeach
                </ul>
                <p class="mt-1 text-xs">They are listed on Ledger Review after the import.</p>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row gap-3">
            <form method="POST" action="{{ route('treasurer.ledger.import') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="file_name" value="{{ $fileName }}">
                <button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Import</button>
            </form>
            <form method="POST" action="{{ route('treasurer.ledger.cancel') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <button type="submit" class="w-full sm:w-auto border border-neutral-300 text-neutral-700 font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-neutral-50">Cancel and delete the upload</button>
            </form>
        </div>
    @endif
</x-app-layout>
