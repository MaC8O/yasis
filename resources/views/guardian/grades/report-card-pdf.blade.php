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
        .comment { margin-top: 24px; padding: 12px; background: #f5f5f0; border-radius: 8px; }
        .footer { margin-top: 40px; font-size: 10px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Yangon Adventist Seminary</h1>
        <p>Report Card — {{ $term->name }}</p>
    </div>
    <div class="title">{{ $child->first_name }} {{ $child->last_name }} — {{ $child->student_id_number }}</div>
    <table>
        <thead><tr><th>Subject</th><th>Score</th><th>Letter</th></tr></thead>
        <tbody>
            @foreach ($subjects as $row)
                <tr>
                    <td>{{ $row->subject }}</td>
                    <td>{{ $row->result['pct'] !== null ? $row->result['pct'].'%' : '—' }}</td>
                    <td>{{ $row->result['letter'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if ($comment)
        <div class="comment"><strong>Homeroom comment:</strong> {{ $comment->comment }}</div>
    @endif
    <div class="footer">Generated {{ now()->format('F j, Y \a\t H:i') }}. Physical seal and signature required for legal validity.</div>
</body>
</html>
