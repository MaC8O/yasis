<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: sans-serif; color: #1a1a1a;">
    <p>Dear Guardian,</p>
    <p>
        This is to notify you that <strong>{{ $record->student->first_name }} {{ $record->student->last_name }}</strong>
        was marked <strong>{{ $record->status }}</strong> on {{ $record->attendance_date->format('F j, Y') }}
        for {{ $record->section->name }}.
    </p>
    @if ($record->remark)
        <p>Remark: {{ $record->remark }}</p>
    @endif
    <p>If you believe this was recorded in error, please contact the school office.</p>
    <p>— Yangon Adventist Seminary, Integrated School Management System</p>
</body>
</html>
