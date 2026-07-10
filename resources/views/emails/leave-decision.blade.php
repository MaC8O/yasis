<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: sans-serif; color: #1a1a1a;">
    <p>Dear {{ $leaveRequest->staff->user->name }},</p>
    <p>
        Your <strong>{{ $leaveRequest->leaveType->name }}</strong> leave request for
        {{ $leaveRequest->from_date->format('F j, Y') }}
        @if (!$leaveRequest->from_date->equalTo($leaveRequest->to_date))
            – {{ $leaveRequest->to_date->format('F j, Y') }}
        @endif
        ({{ $leaveRequest->days }} day(s)) has been <strong>{{ strtolower($leaveRequest->status) }}</strong>
        by {{ $leaveRequest->decidedBy->user->name ?? 'HR Office' }}.
    </p>
    <p>— Yangon Adventist Seminary, Integrated School Management System</p>
</body>
</html>
