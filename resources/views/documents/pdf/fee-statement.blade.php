{{--
    Statement of account for a family, from the published Sun ledger statements (one section per
    financial year). Lines the Treasurer hid by hand appear only as "Adjustments". $statements:
    SunFamilyStatement rows, newest year first.
--}}
@use('App\Support\LedgerFormat')
@php
    $section = $student->enrollments->firstWhere('status', 'Active')?->section;
    $latest = $statements->first();
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
        h3 { margin: 16px 0 4px; font-size: 12px; color: #1F573D; }
        .lines th { background: #1F573D; color: #fff; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; padding: 5px 6px; text-align: left; }
        .lines td { border-bottom: 1px solid #e3e3e3; padding: 4px 6px; }
        .lines .num { text-align: right; white-space: nowrap; }
        .lines tr.sub td { background: #fafaf7; font-weight: bold; }
        .lines tfoot td { border-top: 2px solid #1F573D; border-bottom: 0; font-weight: bold; padding-top: 6px; }
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
                <div class="copy">FAMILY COPY</div>
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
                    Student ID: {{ $student->student_id_number }}<br>
                    {{ $section?->name ?? '—' }} · {{ $student->department?->name ?? '—' }}
                </div>
            </td>
            <td style="width:36%; padding-right:8px; vertical-align:top">
                <div class="box">
                    <h4>Bill to</h4>
                    <strong>{{ $guardian?->user?->name ?? '—' }}</strong><br>
                    {{ $guardian?->relationship ? $guardian->relationship.' · ' : '' }}{{ $guardian?->phone ?? '' }}
                </div>
            </td>
            <td style="vertical-align:top">
                <div class="due">
                    <div class="muted">{{ $latest ? 'Balance, '.$latest->content['year_label'] : 'Balance' }} (Ks)</div>
                    <div class="amt">{{ $latest ? LedgerFormat::money($latest->closing_balance) : '—' }}</div>
                    <div class="muted">{{ $latest ? $latest->status() : 'No records published' }}</div>
                </div>
            </td>
        </tr>
    </table>

    @foreach ($statements as $statement)
        @php $c = $statement->content; @endphp
        <h3>Financial year {{ $c['year_label'] }}</h3>
        <table class="lines">
            <thead><tr><th style="width:18%">Month</th><th>Description</th><th class="num">Charged</th><th class="num">Paid / credited</th></tr></thead>
            <tbody>
                <tr class="sub"><td colspan="3">Balance at the start of the year</td><td class="num">{{ LedgerFormat::money($c['opening']) }}</td></tr>
                @foreach ($c['lines'] as $line)
                    @php $credit = (float) $line['amount'] < 0; @endphp
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($line['month'])->format('M Y') }}</td>
                        <td>{{ $line['memo'] !== '' ? $line['memo'] : ucfirst(str_replace('_', ' ', $line['category'])) }}</td>
                        <td class="num">{{ $credit ? '' : number_format((float) $line['amount'], 2) }}</td>
                        <td class="num">{{ $credit ? number_format(abs((float) $line['amount']), 2) : '' }}</td>
                    </tr>
                @endforeach
                @if ((float) $c['adjustments'] != 0)
                    <tr><td></td><td>Adjustments</td>
                        <td class="num">{{ (float) $c['adjustments'] > 0 ? number_format((float) $c['adjustments'], 2) : '' }}</td>
                        <td class="num">{{ (float) $c['adjustments'] < 0 ? number_format(abs((float) $c['adjustments']), 2) : '' }}</td></tr>
                @endif
            </tbody>
            <tfoot>
                <tr><td colspan="2">Balance at the end of the year · {{ $statement->status() }}</td><td class="num" colspan="2">{{ LedgerFormat::money($c['closing']) }}</td></tr>
            </tfoot>
        </table>
    @endforeach

    <div class="footer">
        Amounts in Myanmar Kyat (Ks); "Cr" means in credit (paid in advance or overpaid). This statement shows the school's accounts as
        last published by the finance office; it is not a receipt or tax invoice. Please settle payments with the Finance Office by the
        15th of the month and quote the student ID above.
    </div>
</body>
</html>
