<?php

namespace App\Support\Sun;

use Brick\Math\BigDecimal;

/**
 * Writes a synthetic Sun Accounts "ACCOUNT LISTING" in the exact print layout of
 * sdp_doc/YASIS_Sun_Finance_Import.md §2: page headers repeated every $pageLines lines (so pages
 * break mid-account, and an account heading repeats on the new page), opening "Period Total"
 * lines, Cr amounts and balances, memo lines, period totals, closing lines, the report balance
 * and END OF REPORT. Invented names and amounts only: never real data.
 *
 * The Student Control account (1000285005) is added with one line that brings the report to 0.00.
 *
 * Used by the demo seeder (a synthetic 2026-2027 listing for the seeded invented students) and by
 * the tests, which can also ask for a file broken in each way the import refuses ($break).
 */
class ListingWriter
{
    private array $accounts = [];

    public function __construct(private int $fiscalYear = 2021, private int $pageLines = 40) {}

    /**
     * @param  list<array{0: int, 1: string, 2: string, 3?: string, 4?: string, 5?: bool, 6?: int}>  $lines
     *                                                                                                       each [period, signed amount, memo, journal type (JV), typed date d/m/Y, allocated (true), journal line no.]
     */
    public function account(string $code, string $name, string $opening = '0.00', array $lines = [], bool $openingLine = true): self
    {
        $this->accounts[$code] = compact('code', 'name', 'opening', 'lines', 'openingLine');

        return $this;
    }

    public function render(array $break = []): string
    {
        $fy = $this->fiscalYear;
        $codes = array_keys($this->accounts);
        sort($codes);
        $sum = BigDecimal::zero();
        foreach ($this->accounts as $a) {
            $sum = $sum->plus($this->closing($a));
        }
        $control = ['code' => '1000285005', 'name' => 'Student Control', 'opening' => '0.00', 'openingLine' => false,
            'lines' => $sum->isZero() ? [] : [[6, (string) $sum->negated(), 'Students - Payment for yearly fee', 'JV', null, true, 1]]];
        $all = array_merge([$control], array_map(fn ($c) => $this->accounts[$c], $codes));
        $last = end($codes) ?: '1000285005';

        $out = [];
        $page = 0;
        $current = null;
        $emit = function (string $line) use (&$out, &$page, &$current, $fy, $last) {
            if ($page === 0 || (count($out) % $this->pageLines) === 0) {
                $page++;
                array_push($out, '', '', sprintf('Yangon Adv. Inter. Academy                        ACCOUNT LISTING               Page %4d          Printed   19/08/2025        11:55', $page), '',
                    sprintf('Account Code: 1000285005  Accounting Period: 01/%d', $fy),
                    sprintf('          To: %s                 To: 12/%d', $last, $fy),
                    str_repeat('-', 132),
                    'Account  Trans  Reference  Journal   Journal             Memo        Amount              Balance        A n a l y s i s   C o d e s',
                    'Period   Date   Description Type    No.  Line                                                          ALLOC',
                    str_repeat('-', 132), '');
                if ($current) {
                    $out[] = $current; // the account heading repeats on the new page
                    $out[] = '';
                }
            }
            $out[] = $line;
        };

        $jno = 100;
        foreach ($all as $a) {
            $current = sprintf('Account Code: %s   Name: %-30s Type: BALANCE SHEET', $a['code'], $a['name']);
            $emit($current);
            $emit('');
            if ($a['openingLine'] && ! BigDecimal::of($a['opening'])->isZero()) {
                $emit(sprintf('12/%d Period Total %20s', $fy - 1, $this->money($a['opening'])));
                $emit('');
            }
            $running = BigDecimal::of($a['opening']);
            $byPeriod = [];
            foreach ($a['lines'] as $i => $l) {
                $byPeriod[$l[0]][] = [$i, $l];
            }
            ksort($byPeriod);
            foreach ($byPeriod as $period => $lines) {
                $net = BigDecimal::zero();
                foreach ($lines as [$i, $l]) {
                    [$p, $amount, $memo] = $l;
                    $type = $l[3] ?? 'JV';
                    $date = $l[4] ?? sprintf('15/%02d/%d', ((3 + $p - 1) % 12) + 1, ((3 + $p - 1) % 12) + 1 >= 4 ? $fy - 1 : $fy);
                    $allocated = $l[5] ?? true;
                    $lineNo = $l[6] ?? ($i + 1);
                    $running = $running->plus($amount);
                    $net = $net->plus($amount);
                    $balance = ($break['balance'] ?? null) === $a['code'].'#'.$i ? $running->plus(1) : $running;
                    $emit(sprintf('%02d/%d%s %s %s %-5s %-5s %05d %4d %26s %20s', $p, $fy, $allocated ? '*' : ' ', $date,
                        ucfirst(strtolower($type)), $jno + $i, $type, $l[7] ?? $jno, $lineNo, $this->money($amount), $this->money((string) $balance)));
                    $emit($memo);
                }
                $emit(sprintf('%02d/%d Period Total %28s', $period, $fy, $this->money((string) $net)));
                $emit('');
            }
            $closing = ($break['closing'] ?? null) === $a['code'] ? $running->plus(5) : $running;
            if (($break['no_closing'] ?? null) !== $a['code']) {
                $emit(sprintf('Account: %s Balance %20s', $a['code'], $this->money((string) $closing)));
            }
            $emit('');
            $current = null;
        }
        if (! ($break['no_end'] ?? false)) {
            $out[] = sprintf('Report Balance %20s', $this->money(($break['report_balance'] ?? '0.00')));
            $out[] = '';
            $out[] = 'END OF REPORT';
        }
        if (isset($break['garbage'])) {
            array_splice($out, (int) $break['garbage'], 0, ['%%% this line is not part of a listing %%%']);
        }
        if ($break['not_listing'] ?? false) {
            $out = array_map(fn ($l) => str_replace('ACCOUNT LISTING', 'TRIAL BALANCE  ', $l), $out);
        }

        return implode("\r\n", $out)."\r\n";
    }

    public function closing(array $a): string
    {
        $sum = BigDecimal::of($a['opening']);
        foreach ($a['lines'] as $l) {
            $sum = $sum->plus($l[1]);
        }

        return (string) $sum->toScale(2);
    }

    /** "-52500.00" as "52,500.00Cr". */
    private function money(string $amount): string
    {
        $v = BigDecimal::of($amount)->toScale(2);

        return number_format(abs((float) (string) $v), 2).($v->isNegative() ? 'Cr' : '');
    }
}
