{{--
    §7.2 Statement of Marks, A4 portrait, laid out like the school's samples. $s is the statement's
    content from StatementOfMarksService::content() (or the issued copy); $draft adds a watermark.
    The seal and the signatures stay manual: blank lines, no names or dates.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Statement of Marks</title>
<style>
    @page { margin: 14mm 16mm 12mm 16mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #111; }
    .center { text-align: center; }
    .head p { margin: 0; line-height: 1.35; }
    .head .dept { font-weight: bold; font-size: 12pt; letter-spacing: 0.5px; }
    .head .title { font-weight: bold; font-size: 14pt; letter-spacing: 1px; margin-top: 6px; }
    .fields { width: 100%; margin-top: 12px; border-collapse: collapse; font-weight: bold; }
    .fields td { padding: 2px 0; vertical-align: top; }
    .nowrap { white-space: nowrap; }
    .fields .v { font-weight: bold; }
    table.marks { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.marks th, table.marks td { border: 1px solid #333; padding: 3px 6px; }
    table.marks th { font-size: 9pt; text-align: left; background: #f2f2f2; }
    table.marks td.n, table.marks th.n { text-align: center; }
    table.marks td.inner { border-top: none; border-bottom: none; }
    table.marks tr.last td.inner { border-bottom: 1px solid #333; }
    .group { font-weight: bold; }
    .big { font-size: 13pt; font-weight: bold; text-align: center; vertical-align: bottom; }
    .result { width: 100%; margin-top: 12px; font-weight: bold; font-size: 10.5pt; }
    .sign { width: 100%; margin-top: 36px; }
    .sign td { width: 50%; text-align: center; }
    .line { display: inline-block; width: 60mm; border-top: 1px solid #111; padding-top: 3px; }
    .footer { margin-top: 18px; border: 1px solid #333; padding: 6px 8px; font-size: 8pt; line-height: 1.35; text-align: justify; }
    table.conv { border-collapse: collapse; margin-top: 5px; width: 100%; font-size: 8pt; }
    table.conv th, table.conv td { border: 1px solid #333; padding: 2px 3px; text-align: center; }
    table.conv th { background: #f2f2f2; }
    table.conv td.k, table.conv th.k { text-align: left; white-space: nowrap; }
    .watermark { position: fixed; top: 42%; left: 0; width: 100%; text-align: center; font-size: 40pt; font-weight: bold; white-space: nowrap;
                 color: rgba(200, 0, 0, 0.16); transform: rotate(-30deg); }
</style>
</head>
<body>
@if ($draft)
    <div class="watermark">DRAFT – NOT ISSUED</div>
@endif

<div class="head center">
    <p class="dept">THE DEPARTMENT OF EDUCATION</p>
    <p>Myanmar Union Mission of Seventh-day Adventists</p>
    <p>Yangon, MYANMAR</p>
    <p class="title">STATEMENT OF MARKS</p>
</div>

<table class="fields">
    <tr>
        <td style="width: 48%">NAME: <span class="v">{{ $s['name'] }}</span></td>
        <td style="width: 14%" class="nowrap">GRADE: <span class="v">{{ $s['grade'] }}</span></td>
        <td class="nowrap" style="text-align: right">DATE OF BIRTH: <span class="v">{{ $s['date_of_birth'] }}</span></td>
    </tr>
    <tr><td colspan="3">EXAMINATION CENTRE: {{ $s['examination_centre'] }}</td></tr>
    <tr><td colspan="3">DATE OF EXAMINATION: {{ $s['examination_date'] }}</td></tr>
    <tr><td colspan="3">DATE OF ISSUE: {{ $s['issue_date'] }}</td></tr>
</table>

@if ($s['layout'] === 'kg9')
    @php $n = max(count($s['left']), count($s['right'])); @endphp
    <table class="marks" data-layout="kg9">
        <thead>
            <tr><th>SUBJECT</th><th class="n">SCORE %</th><th>SUBJECT</th><th class="n">SCORE %</th><th class="n">ACCUMULATIVE SCORE</th></tr>
        </thead>
        <tbody>
            <tr>
                <td class="inner group">COMPULSORY:</td><td class="inner"></td><td class="inner group">COMPULSORY:</td><td class="inner"></td>
                <td class="big" rowspan="{{ $n + 1 }}">{{ $s['accumulative'] }}</td>
            </tr>
            @for ($i = 0; $i < $n; $i++)
                <tr class="{{ $i === $n - 1 ? 'last' : '' }}">
                    <td class="inner">{{ $s['left'][$i]['label'] ?? '' }}</td>
                    <td class="inner n">{{ $s['left'][$i]['score'] ?? '' }}</td>
                    <td class="inner">{{ $s['right'][$i]['label'] ?? '' }}</td>
                    <td class="inner n">{{ $s['right'][$i]['score'] ?? '' }}</td>
                </tr>
            @endfor
        </tbody>
    </table>
@else
    @php $n = max(count($s['compulsory']), count($s['optional'])); @endphp
    <table class="marks" data-layout="g11_12">
        <thead>
            <tr><th>SUBJECT</th><th class="n">SCORE</th><th class="n">HRS</th><th>SUBJECT</th><th class="n">SCORE</th><th class="n">HRS</th><th class="n">GPA</th></tr>
        </thead>
        <tbody>
            <tr>
                <td class="inner group">COMPULSORY:</td><td class="inner"></td><td class="inner"></td>
                <td class="inner group">OPTIONAL:</td><td class="inner"></td><td class="inner"></td><td class="inner"></td>
            </tr>
            @for ($i = 0; $i < $n; $i++)
                <tr>
                    <td class="inner">{{ $s['compulsory'][$i]['label'] ?? '' }}</td>
                    <td class="inner n">{{ $s['compulsory'][$i]['score'] ?? '' }}</td>
                    <td class="inner n">{{ $s['compulsory'][$i]['hours'] ?? '' }}</td>
                    <td class="inner">{{ $s['optional'][$i]['label'] ?? '' }}</td>
                    <td class="inner n">{{ $s['optional'][$i]['score'] ?? '' }}</td>
                    <td class="inner n">{{ $s['optional'][$i]['hours'] ?? '' }}</td>
                    <td class="inner"></td>
                </tr>
            @endfor
            <tr class="last">
                <td class="inner"></td><td class="inner"></td><td class="inner"></td><td class="inner"></td><td class="inner"></td><td class="inner"></td>
                <td class="inner big">{{ $s['gpa'] }}</td>
            </tr>
        </tbody>
    </table>
@endif

<table class="result">
    <tr>
        <td style="width: 50%">RESULT&nbsp;&nbsp;&nbsp;&nbsp;{{ $s['result'] }}</td>
        <td>CLASS OF PASS&nbsp;&nbsp;&nbsp;&nbsp;{{ $s['class_of_pass'] }}</td>
    </tr>
</table>

<table class="sign">
    <tr>
        <td><span class="line">Registrar</span></td>
        <td><span class="line">Principal</span></td>
    </tr>
</table>

@if ($s['layout'] === 'kg9')
    <div class="footer">These examinations are conducted by YAS, Myanmar Union of Seventh-day Adventists operated by the Seventh-day Adventists. This mark list is awarded upon completing all subjects mentioned with a minimum of 40% marks in each subject. Classes are awarded on the following basis: 40-60% aggregate: "Third Class," 61-80% "Second Class," 81-94 % "First," and 95 and above "Distinction."</div>
@else
    <div class="footer" data-gpa-footer>
        <strong>How are the accumulated GPA calculated and CP grouped?</strong>
        <table class="conv" data-conversion>
            <tr><th class="k">Score/Letter Grade</th>@foreach ($s['conversion'] as $c)<th>{{ $c['letter'] }}</th>@endforeach</tr>
            <tr><td class="k">Grade Value</td>@foreach ($s['conversion'] as $c)<td>{{ $c['value'] }}</td>@endforeach</tr>
            <tr><td class="k">(3) HRS</td>@foreach ($s['conversion'] as $c)<td>{{ $c['hours3'] }}</td>@endforeach</tr>
            <tr><td class="k">(2) HRS</td>@foreach ($s['conversion'] as $c)<td>{{ $c['hours2'] }}</td>@endforeach</tr>
        </table>
        <table class="conv" data-classes>
            <tr><th class="k">Class of Pass</th>@foreach ($s['classes'] as $c)<th>{{ $c['label'] }}</th>@endforeach</tr>
            <tr><td class="k">GPA</td>@foreach ($s['classes'] as $c)<td>{{ $c['range'] }}</td>@endforeach</tr>
        </table>
        <p style="margin: 6px 0 0"><strong>Note:</strong> Using the conversion tables above, multiply the equivalent grade value (that matches the score/letter grade) with corresponding credit HRS (Hours) of each course to get the total grade points. It will then divide the total number of grade points by the total number of credit hours which will be resulted in the Grade Point Average (GPA), and awarded the Class of Pass (CP).</p>
    </div>
@endif
</body>
</html>
