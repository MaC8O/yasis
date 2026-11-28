<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Models\SunAccount;
use App\Models\SunLedgerLine;
use App\Models\User;
use App\Services\Sun\SunAccountService;
use App\Services\Sun\SunLedgerImporter;
use App\Support\Sun\ListingWriter;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * A synthetic Sun Accounts listing for 2026-2027 (financial year 2027), for the demo: the seeded
 * (invented) students of Grade 9-A and Grade 12-A, imported through the real parser and importer,
 * linked, and published, so the Treasurer, leadership, guardians and students all have ledger
 * fees after a fresh seed. Never real data.
 *
 * It shows: owing, settled and in-credit accounts; a 20% education allowance families can see
 * (T4); a monthly ferry fee (T9); a yearly fee that differs from the fee schedule; a line the
 * Treasurer hid by hand (shown to families as "Adjustments"); and one code left unmatched.
 */
class SunLedgerDemoSeeder extends Seeder
{
    private const FY = 2027;

    public function run(): void
    {
        $treasurer = User::where('email', 'treasurer@yasis.edu')->first();
        $year = AcademicYear::where('year_label', '2026-2027')->first();
        if (! $treasurer || ! $year) {
            return;
        }
        $students = Section::where('academic_year_id', $year->id)->whereIn('name', ['Grade 9-A', 'Grade 12-A'])->get()
            ->flatMap(fn ($s) => Enrollment::where('section_id', $s->id)->where('status', 'Active')->orderBy('student_id')->pluck('student_id'))
            ->map(fn ($id) => Student::find($id))->filter()->values();
        if ($students->isEmpty()) {
            return;
        }

        $writer = new ListingWriter(self::FY, 50);
        $codes = [];
        foreach ($students as $i => $student) {
            $code = (string) (1000290000 + ($i + 1) * 10);
            $codes[$code] = $student;
            $writer->account($code, mb_substr($student->name, 0, 30), '0.00', $this->lines($i));
        }
        // An old account from before the ISMS, left for the Treasurer to match or mark.
        $writer->account('1000299990', 'Former Student Before ISMS', '-2000.00', [[2, '2000.00', 'Transferring deposit bal', 'JV', null, true, 1]]);

        $path = tempnam(sys_get_temp_dir(), 'sun');
        file_put_contents($path, $writer->render());
        $importer = app(SunLedgerImporter::class);
        $staged = $importer->stage(new UploadedFile($path, 'Demo Student GL 2026-2027.PRN', 'text/plain', null, true), $treasurer);
        @unlink($path);
        if (! $staged['token']) {
            return;
        }
        $batch = $importer->import($staged['token'], 'Demo Student GL 2026-2027.PRN', $treasurer);

        $accounts = app(SunAccountService::class);
        foreach ($codes as $code => $student) {
            $accounts->link(SunAccount::where('code', $code)->firstOrFail(), $student, $treasurer);
        }
        // An exceptional line the Treasurer hides: families see it only inside "Adjustments".
        $hidden = SunLedgerLine::where('memo', 'like', 'Internal correction%')->first();
        if ($hidden) {
            $accounts->setRestricted($hidden, true, $treasurer);
        }

        $importer->publish($batch, true, $treasurer);
    }

    /** One student's 2026-2027 lines: periods 3 (June), 5 (August), 7 (October). Varied by position. */
    private function lines(int $i): array
    {
        $n = 0;
        $line = function (int $period, string $amount, string $memo, string $type = 'JV') use (&$n) {
            return [$period, $amount, $memo, $type, null, true, ++$n];
        };
        $yearly = $i === 2 ? '350000.00' : '300000.00'; // one yearly fee differs from the schedule
        $lines = [
            $line(3, $yearly, 'Charging 2026-2027 yearly fee'),
            $line(3, '650000.00', 'Charging tuition fee 1st instalment for Jun 2026'),
            $line(5, '650000.00', 'Charging tuition fee 2nd instalment for Aug 2026'),
            $line(7, '650000.00', 'Charging tuition fee 3rd instalment for Oct 2026'),
        ];
        if ($i % 4 === 1) {
            $lines[] = $line(3, '-130000.00', 'Providing 20% edu allow on tuition fee for Jun 2026');
        }
        if ($i % 5 === 3) {
            foreach ([3, 4, 5, 6, 7] as $p) {
                $lines[] = $line($p, '15000.00', 'Charging school ferry fee');
            }
        }
        if ($i === 0) {
            $lines[] = $line(6, '-5000.00', 'Internal correction of a posting error');
        }

        // Payments: settled, owing, in credit or partly paid.
        $charged = array_sum(array_map(fn ($l) => (float) $l[1], $lines));
        $paid = match ($i % 4) {
            0 => $charged,                // settled
            1 => $charged - 650000,       // owes the October instalment
            2 => $charged + 100000,       // paid in advance: in credit
            default => $charged - 300000, // part of the yearly fee still owed
        };
        $lines[] = $line(4, sprintf('-%.2f', $paid * 0.6), 'Payment for fees', 'CR');
        $lines[] = $line(8, sprintf('-%.2f', $paid - round($paid * 0.6, 2)), 'Payment for fees', 'CR');

        return $lines;
    }
}
