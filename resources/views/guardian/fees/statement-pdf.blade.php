<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; font-size: 13px; }
        .header { text-align: center; border-bottom: 3px solid #1F573D; padding-bottom: 16px; margin-bottom: 24px; }
        .header h1 { margin: 0; color: #1F573D; font-size: 20px; }
        .title { text-align: center; font-size: 18px; font-weight: bold; margin: 24px 0; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #ddd; padding: 8px; text-align: left; font-size: 13px; }
        .footer { margin-top: 40px; font-size: 10px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Yangon Adventist Seminary</h1>
        <p>Guardian Fee Statement</p>
    </div>
    <div class="title">{{ $child->name }} — {{ $child->student_id_number }}</div>
    <table>
        <thead><tr><th>Date</th><th>Amount</th><th>Balance</th><th>Status</th></tr></thead>
        <tbody>
            @foreach ($records as $record)
                <tr>
                    <td>{{ $record->txn_date->format('Y-m-d') }}</td>
                    <td>{{ number_format($record->amount, 2) }}</td>
                    <td>{{ number_format($record->balance, 2) }}</td>
                    <td>{{ $record->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="footer">
        SDA discount/allowance rows are excluded from this statement. This is a record-keeping summary only,
        not a receipt or invoice. Generated {{ now()->format('F j, Y \a\t H:i') }}.
    </div>
</body>
</html>
