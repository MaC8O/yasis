<?php

namespace App\Services\Sun;

use Brick\Math\BigDecimal;

/**
 * Reads a Sun Accounts "ACCOUNT LISTING" report printed to a file (sdp_doc/YASIS_Sun_Finance_Import.md
 * §2, gitignored) and checks it (§3). Fixed-width print layout, Latin-1, page headers repeated on
 * every page, an account block possibly split across pages.
 *
 * Privacy (§0): errors and warnings carry line numbers and counts only, never a name, an amount
 * or a memo, so nothing from the file can reach a log or a flash message.
 *
 * Amounts are signed decimal strings with two places: a debit (no suffix) is positive, a credit
 * ("Cr") negative. A positive balance means the student owes the school.
 */
class AccountListingParser
{
    private const ACCOUNT = '/^Account Code: (\d+)\s+Name: (.*?)\s+Type: (.+?)\s*$/';

    private const TRANSACTION = '/^(\d{2})\/(\d{4})(\*?)\s+(\d{2})\/(\d{2})\/(\d{4})\s+(\w+)\s+(\S+)\s+(\w{2})\s+(\d+)\s+(\d+)\s+([\d,]+\.\d{2})(Cr)?\s+([\d,]+\.\d{2})(Cr)?\s*(.*)$/';

    private const PERIOD_TOTAL = '/^(\d{2})\/(\d{4})\s+Period Total\s+([\d,]+\.\d{2})(Cr)?\s*$/';

    private const CLOSING = '/^Account: (\d+) Balance\s+([\d,]+\.\d{2})(Cr)?\s*$/';

    private const REPORT_BALANCE = '/^Report Balance\s+([\d,]+\.\d{2})(Cr)?\s*$/';

    private const PAGE_TITLE = '/ACCOUNT LISTING\s+Page\s+\d+\s+Printed\s+(\d{2}\/\d{2}\/\d{4})\s+(\d{2}:\d{2})/';

    private const RANGE_FROM = '/^Account Code: (\d+)\s+Accounting Period: (\d{2})\/(\d{4})\s*$/';

    private const RANGE_TO = '/^To: (\d+)\s+To: (\d{2})\/(\d{4})\s*$/';

    /** At most this many errors are listed; the count is still exact. */
    private const MAX_LISTED = 50;

    /**
     * @return array{
     *   ok: bool, errors: list<array{line: ?int, message: string}>, error_count: int,
     *   header: array{printed_at: ?string, account_from: ?string, account_to: ?string, period_from: ?string, period_to: ?string, fiscal_year: ?int},
     *   accounts: array<string, array{code: string, name: string, opening: string, closing: ?string, line: int, lines: list<array>}>,
     *   report_balance: ?string, closing_sum: string, line_count: int, unallocated: int,
     * }
     */
    public function parse(string $contents): array
    {
        $text = mb_convert_encoding($contents, 'UTF-8', 'ISO-8859-1');
        $rows = explode("\n", str_replace("\r", '', $text));

        $errors = [];
        $errorCount = 0;
        $fail = function (?int $line, string $message) use (&$errors, &$errorCount) {
            $errorCount++;
            if (count($errors) < self::MAX_LISTED) {
                $errors[] = ['line' => $line, 'message' => $message];
            }
        };

        $header = ['printed_at' => null, 'account_from' => null, 'account_to' => null, 'period_from' => null, 'period_to' => null, 'fiscal_year' => null];
        $accounts = [];
        $current = null;          // the open account's code
        $expectMemo = null;       // index of the transaction waiting for its memo line
        $reportBalance = null;
        $ended = false;
        $lineCount = 0;
        $unallocated = 0;
        $sawTitle = false;

        foreach ($rows as $i => $raw) {
            $n = $i + 1;
            $line = rtrim($raw);
            $trim = ltrim($line);
            if ($trim === '') {
                continue;
            }

            // Page header lines (§2), skipped wherever they fall; the first page's give the report's ranges.
            if (preg_match(self::PAGE_TITLE, $line, $m)) {
                $sawTitle = true;
                $header['printed_at'] ??= $m[1].' '.$m[2];

                continue;
            }
            if (! $sawTitle) {
                $fail($n, 'The file does not start with an ACCOUNT LISTING report header.');

                return $this->result(false, $errors, $errorCount, $header, [], null, $lineCount, $unallocated);
            }
            if (preg_match(self::RANGE_FROM, $trim, $m)) {
                $header['account_from'] ??= $m[1];
                $header['period_from'] ??= "{$m[2]}/{$m[3]}";

                continue;
            }
            if (preg_match(self::RANGE_TO, $trim, $m)) {
                $header['account_to'] ??= $m[1];
                $header['period_to'] ??= "{$m[2]}/{$m[3]}";
                $header['fiscal_year'] ??= (int) $m[3];

                continue;
            }
            if (str_starts_with($trim, '-----') || str_starts_with($trim, 'Account  Trans') || str_starts_with($trim, 'Period   Date')) {
                continue;
            }

            if ($ended) {
                $fail($n, 'Text after END OF REPORT.');

                continue;
            }

            // The memo: the next line after a transaction that isn't a page header (handled above).
            if ($expectMemo !== null) {
                // A page break between a line and its memo repeats the account's heading first.
                if (preg_match(self::ACCOUNT, $trim, $m) && $m[1] === $current) {
                    continue;
                }
                $accounts[$current]['lines'][$expectMemo]['memo'] = mb_substr(trim($line), 0, 255);
                $expectMemo = null;

                continue;
            }

            if (preg_match(self::ACCOUNT, $trim, $m)) {
                if ($current !== null && $current !== $m[1] && $accounts[$current]['closing'] === null) {
                    $fail($accounts[$current]['line'], 'An account has no closing balance line.');
                }
                if ($current !== $m[1]) {
                    if (isset($accounts[$m[1]])) {
                        $fail($n, 'An account appears twice in the report.');
                    }
                    $current = $m[1];
                    $accounts[$current] = ['code' => $m[1], 'name' => trim($m[2]), 'opening' => '0.00', 'closing' => null, 'line' => $n, 'lines' => [], 'running' => '0.00', 'has_opening' => false];
                }

                continue;
            }

            if (preg_match(self::TRANSACTION, $trim, $m)) {
                if ($current === null || $accounts[$current]['closing'] !== null) {
                    $fail($n, 'A transaction line outside an account.');

                    continue;
                }
                $amount = $this->signed($m[12], $m[13] ?? '');
                $balance = $this->signed($m[14], $m[15] ?? '');
                $expected = (string) BigDecimal::of($accounts[$current]['running'])->plus($amount)->toScale(2);
                if ($expected !== $balance) {
                    $fail($n, 'A running balance is not the previous balance plus the line amount.');
                }
                $accounts[$current]['running'] = $balance;
                $allocated = $m[3] === '*';
                $unallocated += $allocated ? 0 : 1;
                $accounts[$current]['lines'][] = [
                    'period' => (int) $m[1], 'period_year' => (int) $m[2], 'allocated' => $allocated,
                    'txn_date' => checkdate((int) $m[5], (int) $m[4], (int) $m[6]) ? "{$m[6]}-{$m[5]}-{$m[4]}" : null,
                    'reference' => mb_substr("{$m[7]} {$m[8]}", 0, 20), 'journal_type' => strtoupper($m[9]),
                    'journal_no' => $m[10], 'journal_line' => (int) $m[11],
                    'amount' => $amount, 'running_balance' => $balance, 'memo' => '', 'file_line' => $n,
                ];
                $lineCount++;
                $expectMemo = array_key_last($accounts[$current]['lines']);

                continue;
            }

            if (preg_match(self::PERIOD_TOTAL, $trim, $m)) {
                if ($current === null) {
                    $fail($n, 'A period total outside an account.');

                    continue;
                }
                // Before any transaction, a total for a period of the previous year is the opening balance (§2).
                $isOpening = $accounts[$current]['lines'] === [] && ! $accounts[$current]['has_opening']
                    && $header['fiscal_year'] !== null && (int) $m[2] < $header['fiscal_year'];
                if ($isOpening) {
                    $accounts[$current]['opening'] = $this->signed($m[3], $m[4] ?? '');
                    $accounts[$current]['running'] = $accounts[$current]['opening'];
                    $accounts[$current]['has_opening'] = true;
                }

                continue;
            }

            if (preg_match(self::CLOSING, $trim, $m)) {
                if ($current === null || $current !== $m[1]) {
                    $fail($n, 'A closing balance line for a different account.');

                    continue;
                }
                $closing = $this->signed($m[2], $m[3] ?? '');
                if ($closing !== $accounts[$current]['running']) {
                    $fail($n, "An account's closing balance is not its last running balance.");
                }
                $accounts[$current]['closing'] = $closing;

                continue;
            }

            if (preg_match(self::REPORT_BALANCE, $trim, $m)) {
                $reportBalance = $this->signed($m[1], $m[2] ?? '');

                continue;
            }

            if ($trim === 'END OF REPORT') {
                $ended = true;

                continue;
            }

            $fail($n, 'A line that is not part of an Account Listing.');
        }

        if ($current !== null && $accounts[$current]['closing'] === null) {
            $fail($accounts[$current]['line'], 'An account has no closing balance line.');
        }
        if (! $ended) {
            $fail(null, 'The file does not end with END OF REPORT. It may be cut short.');
        }
        if ($header['fiscal_year'] === null) {
            $fail(null, 'The report header has no accounting period range.');
        }

        $sum = array_reduce($accounts, fn (BigDecimal $s, $a) => $s->plus($a['closing'] ?? '0'), BigDecimal::zero())->toScale(2);
        if (! $sum->isZero()) {
            $fail(null, 'The closing balances do not add up to 0.00.');
        }
        if ($reportBalance === null || ! $sum->isEqualTo($reportBalance)) {
            $fail(null, 'The closing balances do not equal the Report Balance line.');
        }

        foreach ($accounts as &$account) {
            unset($account['running'], $account['has_opening']);
        }

        return $this->result($errorCount === 0, $errors, $errorCount, $header, $accounts, $reportBalance, $lineCount, $unallocated, (string) $sum);
    }

    /** "52,500.00" with "Cr" as "-52500.00". */
    private function signed(string $amount, string $cr): string
    {
        $value = BigDecimal::of(str_replace(',', '', $amount))->toScale(2);

        return (string) ($cr === 'Cr' ? $value->negated() : $value);
    }

    private function result(bool $ok, array $errors, int $count, array $header, array $accounts, ?string $reportBalance, int $lineCount, int $unallocated, string $sum = '0.00'): array
    {
        return [
            'ok' => $ok, 'errors' => $errors, 'error_count' => $count, 'header' => $header, 'accounts' => $accounts,
            'report_balance' => $reportBalance, 'closing_sum' => $sum, 'line_count' => $lineCount, 'unallocated' => $unallocated,
        ];
    }
}
