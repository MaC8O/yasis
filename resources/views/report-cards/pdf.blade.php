<!DOCTYPE html>
{{--
    The school's report card as a booklet (§7.1): A4 landscape, two columns per page, two pages, to
    print double-sided and fold. Page 1: personality record + class placement (back) | cover (front).
    Page 2: acknowledgement signatures | scholarship record. $card from ReportCardService::build().
--}}
@php
    $palette = [
        'blue' => ['#1F4E8C', '#DEEAF6'],
        'green' => ['#2E6B45', '#E2F0E6'],
        'yellow' => ['#7A5B0B', '#FFF2C4'],
    ][$card['accent']];
    [$ink, $fill] = $palette;
    $cols = $card['columns'];
    $labels = [1 => 'FIRST', 2 => 'SECOND', 3 => 'THIRD', 4 => 'FINAL'];
    $roles = ['guardian' => 'Parent(s)/Guardian', 'class_teacher' => 'Class Teacher', 'principal' => 'Principal'];
    $isFinal = $card['term']->sequence === max(array_keys($cols));
    $blank = fn ($v, $len = 18) => $v !== null && $v !== '' ? e($v) : str_repeat('_', $len);
@endphp
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; font-size: 10px; margin: 0; }
        .spread { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .spread > tbody > tr > td { width: 50%; vertical-align: top; padding: 0 10mm; }
        .spread > tbody > tr > td:first-child { border-right: 1px dashed #bbb; }
        .page-break { page-break-after: always; }
        h2 { font-size: 12px; color: {{ $ink }}; margin: 0 0 6px; letter-spacing: .5px; }
        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.grid th, table.grid td { border: 1px solid #888; padding: 3px 4px; }
        table.grid th { background: {{ $fill }}; font-weight: bold; }
        .c { text-align: center; }
        .sum td { font-weight: bold; background: #f6f6f6; }
        .cover { text-align: center; padding-top: 8mm; }
        .cover .school { font-size: 14px; font-weight: bold; color: {{ $ink }}; margin-top: 4px; }
        .cover .title { font-size: 16px; font-weight: bold; margin-top: 10px; letter-spacing: 1px; }
        .fields { margin: 14px auto 0; text-align: left; width: 85%; font-size: 11px; line-height: 2; }
        .note { margin-top: 14px; font-size: 9.5px; text-align: justify; }
        .band { height: 6px; background: {{ $ink }}; margin-bottom: 8px; }
        .foot { font-size: 8.5px; margin-top: 4px; }
        .prov { color: #9a6a00; font-weight: bold; font-size: 10px; margin-bottom: 6px; }
        .sig { font-size: 9px; }
    </style>
</head>
<body>
    {{-- Page 1: back (personality, placement) | front (cover) --}}
    <table class="spread page-break"><tbody><tr>
        <td>
            <div class="band"></div>
            <h2>STUDENT'S PERSONALITY RECORD</h2>
            <table class="grid">
                <thead><tr><th style="text-align:left">DESCRIPTION</th>@foreach (\App\Models\PersonalityRating::RATINGS as $r)<th class="c" style="width:12%">{{ $r }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach (\App\Models\PersonalityRating::TRAITS as $key => $trait)
                        <tr><td>{{ $trait }}</td>@foreach (\App\Models\PersonalityRating::RATINGS as $r)<td class="c">{{ ($card['personality']['ratings'][$key] ?? null) === $r ? '✓' : '' }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
            @if ($card['personality']['term'])<p class="foot">Rated in {{ $card['personality']['term']->name }}.</p>@endif

            <h2 style="margin-top:18px">CLASS PLACEMENT NEXT YEAR</h2>
            <p style="line-height:2">
                Assigned to Grade {!! $isFinal ? $blank($card['placement']['grade'] ?? null, 8) : str_repeat('_', 8) !!}
                for School Year {!! $isFinal ? $blank($card['placement']['year'] ?? null, 14) : str_repeat('_', 14) !!}.<br>
                Beginning {!! $isFinal ? $blank($card['placement']['beginning'] ?? null, 12) : str_repeat('_', 12) !!}<br>
                Teacher's Signature ________________<br>
                Principal's Signature _______________
            </p>
        </td>
        <td>
            <div class="band"></div>
            <div class="cover">
                <div class="title">STUDENT'S PROGRESS REPORT</div>
                <div class="school">YANGON ADVENTIST SEMINARY INTERNATIONAL SCHOOL</div>
                <div>34 Natmauk Lane (1), Bahan</div>
                <div>Phone: 95-787666619</div>
                <div class="fields">
                    STUDENT'S NAME: <strong>{{ $card['student']->name }}</strong><br>
                    GRADE: <strong>{{ $card['gradeName'] }}</strong><br>
                    SCHOOL YEAR: <strong>{{ $card['schoolYear'] }}</strong><br>
                    CLASS TEACHER: <strong>{{ $card['classTeacher'] ?? '' }}</strong>
                </div>
                <p class="note">This progress report is sent to you once in every nine week of the school year. This is to report to you and get your acknowledgement of your child's performance in the class. Please check the record carefully, and if you feel that your child needs help, or if you do not fully understand this report, contact the teacher in order to plan ways of promoting your child's progress.</p>
                @if ($card['provisional'])<p class="prov">PROVISIONAL: a term on this report is not locked yet.</p>@endif
            </div>
        </td>
    </tr></tbody></table>

    {{-- Page 2: acknowledgement | scholarship record --}}
    <table class="spread"><tbody><tr>
        <td>
            <div class="band"></div>
            <h2>ACKNOWLEDGEMENT SIGNATURE</h2>
            <table class="grid">
                <thead><tr><th style="text-align:left;width:24%">Reports</th><th style="text-align:left;width:30%">Description</th><th style="text-align:left">Signature</th></tr></thead>
                <tbody>
                    @foreach ($cols as $seq => $c)
                        @foreach ($roles as $role => $roleLabel)
                            @php $sig = $card['acknowledgements'][$seq][$role] ?? null; @endphp
                            <tr>
                                @if ($loop->first)<td rowspan="3">{{ ucfirst(strtolower($labels[$seq] ?? $c['term']->name)) }} Report</td>@endif
                                <td>{{ $roleLabel }}</td>
                                <td class="sig">{{ $sig ? $sig['name'].' · '.$sig['date'] : '' }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </td>
        <td>
            <div class="band"></div>
            <h2>SCHOLARSHIP RECORD</h2>
            @if ($card['provisional'])<p class="prov">PROVISIONAL</p>@endif
            <table class="grid">
                <thead>
                    <tr>
                        <th style="text-align:left">MAX. MARKS (100)</th>
                        @if ($card['hasHours'])<th class="c" style="width:8%">HRS</th>@endif
                        @foreach ($cols as $seq => $c)<th class="c" style="width:11%">{{ $labels[$seq] ?? $c['term']->name }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($card['subjects'] as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            @if ($card['hasHours'])<td class="c">{{ $row['hours'] }}</td>@endif
                            @foreach ($row['cells'] as $cell)<td class="c">{{ $cell }}</td>@endforeach
                        </tr>
                    @endforeach
                    @foreach ([[$card['gpaLabel'], 'gpaRow'], ['POSITION', 'positionRow'], ['TOTAL NUMBER IN CLASS', 'totalRow'], ['DAYS ABSENT*', 'daysAbsentRow']] as [$label, $key])
                        <tr class="sum">
                            <td>{{ $label }}</td>
                            @if ($card['hasHours'])<td></td>@endif
                            @foreach ($card[$key] as $cell)<td class="c">{{ $cell }}</td>@endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="foot">* Any student who has more than ten per cent absence in a subject in a term may be denied permission to write the final examination. (Or) A minimum of 80% attendance is required to sit for any examination.</p>
        </td>
    </tr></tbody></table>
</body>
</html>
