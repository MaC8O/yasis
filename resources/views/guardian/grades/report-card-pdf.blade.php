<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; font-size: 13px; }
        .header { text-align: center; border-bottom: 3px solid #1F573D; padding-bottom: 16px; margin-bottom: 24px; }
        .header h1 { margin: 0; color: #1F573D; font-size: 20px; }
        .title { text-align: center; font-size: 18px; font-weight: bold; margin: 24px 0 8px; text-transform: uppercase; }
        .meta { text-align: center; font-size: 12px; color: #555; }
        .status { margin-top: 16px; padding: 8px 12px; border-radius: 6px; font-size: 12px; }
        .provisional { background: #fbf3dc; color: #7a5b0b; border: 1px solid #e8d39a; }
        .final { background: #e6f3ec; color: #1F573D; border: 1px solid #b9dcc8; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #ddd; padding: 8px; text-align: left; font-size: 13px; }
        tr.summary td { font-weight: bold; border-top: 2px solid #1F573D; }
        .muted { color: #888; font-size: 11px; }
        .comment { margin-top: 24px; padding: 12px; background: #f5f5f0; border-radius: 8px; }
        .footer { margin-top: 40px; font-size: 10px; color: #999; text-align: center; }
    </style>
</head>
<body>
    @php
        $result = $report->result;
        $label = $report->resultLabel();
    @endphp
    <div class="header">
        <h1>Yangon Adventist Seminary</h1>
        <p>Report Card — {{ $term->name }}</p>
    </div>
    <div class="title">{{ $child->name }} — {{ $child->student_id_number }}</div>
    @if ($report->section)
        <div class="meta">{{ $report->section->name }}{{ $report->section->gradeLevel ? ' · '.$report->section->gradeLevel->name : '' }}</div>
    @endif

    <div class="status {{ $report->frozen ? 'final' : 'provisional' }}">
        {{ $report->statusNote() }}
    </div>

    <table>
        <thead><tr><th>Subject</th><th>Mark</th><th>Letter</th></tr></thead>
        <tbody>
            @forelse ($report->subjects() as $subject)
                <tr>
                    <td>
                        {{ $subject->label }}
                        @unless ($subject->inCurriculum) <span class="muted">(not in this grade's curriculum)</span> @endunless
                        @if ($subject->inCurriculum && ! $subject->counted) <span class="muted">(not counted)</span> @endif
                    </td>
                    <td>{{ $subject->roundedMark ?? '—' }}</td>
                    <td>{{ $report->bandText($subject) ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">No subjects on record for this term.</td></tr>
            @endforelse
            <tr class="summary">
                <td>{{ $label === 'GPA' ? 'G.P.A' : $label }}</td>
                <td>{{ $report->resultDisplay() ?? '—' }}</td>
                <td>{{ $result?->isComplete() ? 'Class: '.$result->classLabel : '' }}</td>
            </tr>
        </tbody>
    </table>
    @foreach ($report->reasons() as $reason)
        <p class="muted">{{ $reason }}</p>
    @endforeach

    @if ($comment)
        <div class="comment"><strong>Homeroom comment:</strong> {{ $comment->comment }}</div>
    @endif
    <div class="footer">Generated {{ now()->format('F j, Y \a\t H:i') }}. Physical seal and signature required for legal validity.</div>
</body>
</html>
