<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; font-size: 13px; }
        .header { text-align: center; border-bottom: 3px solid #1F573D; padding-bottom: 16px; margin-bottom: 24px; }
        .header h1 { margin: 0; color: #1F573D; font-size: 20px; }
        .title { text-align: center; font-size: 18px; font-weight: bold; margin: 24px 0; text-transform: uppercase; }
        .meta td { padding: 4px 0; font-size: 13px; }
        .meta td.label { color: #666; width: 160px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.lines th, table.lines td { border-bottom: 1px solid #ddd; padding: 6px 8px; text-align: left; font-size: 12px; }
        .footer { margin-top: 40px; font-size: 10px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Yangon Adventist Seminary</h1>
        <p>Finance Office — Fee Statement</p>
    </div>

    <div class="title">Student Fee Statement</div>

    <table class="meta">
        <tr><td class="label">Student ID</td><td>{{ $student->student_id_number }}</td></tr>
        <tr><td class="label">Student Name</td><td>{{ $student->first_name }} {{ $student->last_name }}</td></tr>
        <tr><td class="label">Department</td><td>{{ $student->department->name ?? '—' }}</td></tr>
        <tr><td class="label">Statement Date</td><td>{{ now()->format('F j, Y') }}</td></tr>
    </table>

    <table class="lines">
        <thead>
            <tr><th>Date</th><th>Period</th><th>Amount</th><th>Balance</th><th>Status</th></tr>
        </thead>
        <tbody>
            @foreach ($records as $record)
                <tr>
                    <td>{{ $record->txn_date->format('M j, Y') }}</td>
                    <td>{{ $record->importBatch->period }}</td>
                    <td>{{ number_format($record->amount, 2) }}</td>
                    <td>{{ number_format($record->balance, 2) }}</td>
                    <td>{{ $record->is_restricted ? 'Restricted' : $record->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        This statement reflects fee records imported from the school's accounting system. It is a record-keeping
        summary only and does not constitute a receipt or invoice. Generated {{ now()->format('F j, Y \a\t H:i') }}.
    </div>
</body>
</html>
