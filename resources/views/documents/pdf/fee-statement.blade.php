@use('App\Support\Money')
@use('App\Services\FeeSummaryService')
@php
    $section = $student->enrollments->firstWhere('status', 'Active')?->section;
    $charges = $lines->sum('charge');
    $payments = $lines->sum('payment');
    $open = $lines->sum('open');
    $closing = $lines->last()?->balance ?? 0;
    $statementNo = 'SOA-'.$student->student_id_number.'-'.now()->format('Ymd');
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; font-size: 11px; }
        .brand { color: #1F573D; }
        table { border-collapse: collapse; width: 100%; }
        .head td { vertical-align: top; }
        .school { font-size: 17px; font-weight: bold; }
        .muted { color: #666; }
        .doc-title { font-size: 15px; font-weight: bold; letter-spacing: 1px; text-align: right; }
        .copy { display: inline-block; margin-top: 4px; padding: 2px 8px; border: 1px solid #1F573D; color: #1F573D; font-size: 9px; font-weight: bold; letter-spacing: 1px; }
        .rule { border-bottom: 3px solid #1F573D; margin: 10px 0 14px; }
        .box { border: 1px solid #ccc; padding: 8px 10px; }
        .box h4 { margin: 0 0 4px; font-size: 9px; letter-spacing: 1px; color: #666; text-transform: uppercase; }
        .due { background: #f4f4f0; border: 1px solid #ccc; padding: 8px 10px; text-align: right; }
        .due .amt { font-size: 18px; font-weight: bold; }
        .lines { margin-top: 14px; }
        .lines th { background: #1F573D; color: #fff; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; padding: 6px; text-align: left; }
        .lines td { border-bottom: 1px solid #e3e3e3; padding: 5px 6px; }
        .lines .num { text-align: right; white-space: nowrap; }
        .lines tr.alt td { background: #fafaf7; }
        .lines tfoot td { border-top: 2px solid #1F573D; border-bottom: 0; font-weight: bold; padding-top: 7px; }
        .tag { font-size: 8px; font-weight: bold; color: #4a2d6e; background: #e4daf3; padding: 1px 4px; }
        .aging { margin-top: 16px; }
        .aging th { font-size: 9px; color: #666; text-transform: uppercase; border-bottom: 1px solid #ccc; padding: 4px 6px; text-align: right; }
        .aging td { padding: 6px; text-align: right; font-weight: bold; }
        .footer { margin-top: 24px; font-size: 9px; color: #777; border-top: 1px solid #ddd; padding-top: 8px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="school brand">Yangon Adventist Seminary</div>
                <div class="muted">Finance Office</div>
            </td>
            <td style="text-align:right">
                <div class="doc-title">STATEMENT OF ACCOUNT</div>
                <div class="muted">No. {{ $statementNo }}</div>
                <div class="muted">Date: {{ now()->format('d M Y') }}</div>
                <div class="copy">{{ $familyCopy ? 'FAMILY COPY' : 'OFFICE COPY' }}</div>
            </td>
        </tr>
    </table>
    <div class="rule"></div>

    <table>
        <tr>
            <td style="width:36%; padding-right:8px; vertical-align:top">
                <div class="box">
                    <h4>Student</h4>
                    <strong>{{ $student->name }}</strong><br>
                    Account: {{ $student->student_id_number }}<br>
                    {{ $section?->name ?? '—' }} · {{ $student->department?->name ?? '—' }}
                </div>
            </td>
            <td style="width:36%; padding-right:8px; vertical-align:top">
                <div class="box">
                    <h4>Bill to</h4>
                    <strong>{{ $guardian?->user?->name ?? '—' }}</strong><br>
                    {{ $guardian?->relationship ? $guardian->relationship.' · ' : '' }}{{ $guardian?->phone ?? $guardian?->user?->phone ?? '' }}<br>
                    {{ $guardian?->user?->email ?? '' }}
                </div>
            </td>
            <td style="vertical-align:top">
                <div class="due">
                    <div class="muted">Still owed ({{ Money::CURRENCY }})</div>
                    <div class="amt">{{ Money::format($closing) }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Date</th><th>Period</th><th>Description</th>
                <th class="num">Charged</th><th class="num">Paid</th><th class="num">Still owed</th><th class="num">Running total</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="6"><em>Opening balance</em></td><td class="num">0</td></tr>
            @foreach ($lines as $i => $line)
                <tr class="{{ $i % 2 ? 'alt' : '' }}">
                    <td>{{ $line->record->txn_date->format('d M Y') }}</td>
                    <td>{{ $line->record->importBatch?->period }}</td>
                    <td>
                        {{ $line->record->description ?: 'Fee charge' }}
                        @if (! $familyCopy && $line->record->is_restricted) <span class="tag">SDA</span> @endif
                    </td>
                    <td class="num">{{ Money::format($line->charge, true) }}</td>
                    <td class="num">{{ Money::format($line->payment, true) }}</td>
                    <td class="num">{{ Money::format($line->open, true) }}</td>
                    <td class="num">{{ Money::format($line->balance) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Total</td>
                <td class="num">{{ Money::format($charges) }}</td>
                <td class="num">{{ Money::format($payments) }}</td>
                <td class="num">{{ Money::format($open) }}</td>
                <td class="num">{{ Money::format($closing) }}</td>
            </tr>
        </tfoot>
    </table>

    <table class="aging">
        <thead>
            <tr>
                @foreach (FeeSummaryService::AGING_BUCKETS as $label)<th>{{ $label }}</th>@endforeach
                <th>Still owed</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach (array_keys(FeeSummaryService::AGING_BUCKETS) as $key)<td>{{ Money::format($aging[$key], true) }}</td>@endforeach
                <td>{{ Money::format($closing) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Amounts in Myanmar Kyat ({{ Money::CURRENCY }}). This statement summarises fee records exported from the school's accounting
        system; it is not a receipt or tax invoice. Please settle payments with the Finance Office and quote the account number above.
        @if (! $familyCopy) Office copy — includes restricted (SDA) lines; do not hand to families. @endif
        Generated {{ now()->format('d M Y, H:i') }}.
    </div>
</body>
</html>
