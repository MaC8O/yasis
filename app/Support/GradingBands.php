<?php

namespace App\Support;

/**
 * The rules a grading scheme's band set must meet: bands cover the whole range from 0 to the
 * top, with no gaps and no overlaps at the scheme's rounding step (1 for whole numbers, 0.01 for
 * the GPA), the fail bands are the lowest, and points or grade values never go up as the mark
 * goes down. Numbers are handled as integer hundredths, never floats.
 */
final class GradingBands
{
    public const MIN_BANDS = 2;

    public const MAX_BANDS = 15;

    /** "84.5" => 8450. Null when the value is not a plain number with at most two decimals. */
    public static function hundredths(mixed $value): ?int
    {
        if ($value === null || ! preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', trim((string) $value), $m)) {
            return null;
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    /** 8450 => "84.5", 400 => "4", 368 => "3.68". */
    public static function display(int $hundredths): string
    {
        $text = intdiv($hundredths, 100).'.'.sprintf('%02d', $hundredths % 100);

        return rtrim(rtrim($text, '0'), '.');
    }

    /**
     * Problems with a band set, as sentences for the Admin; empty when the set is valid.
     *
     * @param  array<int, array{label?: mixed, min?: mixed, max?: mixed, value?: mixed, is_fail?: mixed}>  $bands
     * @param  string|null  $valueLabel  "Points" or "Grade value" when each band needs one; null when bands carry none
     * @param  string|null  $valueMax  the highest value a band may carry (the GPA's top), or null
     * @return list<string>
     */
    public static function problems(array $bands, int $decimals, string $top, ?string $valueLabel = null, ?string $valueMax = null): array
    {
        $errors = [];
        $step = 10 ** (2 - $decimals);
        $topH = self::hundredths($top);
        $valueMaxH = $valueMax === null ? null : self::hundredths($valueMax);

        if (count($bands) < self::MIN_BANDS) {
            return ['A band set needs at least two bands: one that passes and one that fails.'];
        }
        if (count($bands) > self::MAX_BANDS) {
            return ['A band set can have at most '.self::MAX_BANDS.' bands.'];
        }

        $rows = [];
        $labels = [];
        foreach (array_values($bands) as $i => $band) {
            $label = trim((string) ($band['label'] ?? ''));
            $name = $label === '' ? 'Band '.($i + 1) : "“{$label}”";

            if ($label === '') {
                $errors[] = "{$name} needs a label.";
            } elseif (isset($labels[mb_strtolower($label)])) {
                $errors[] = "Two bands are labelled {$name}. Each label must be different.";
            }
            $labels[mb_strtolower($label)] = true;

            $min = self::hundredths($band['min'] ?? null);
            $max = self::hundredths($band['max'] ?? null);
            if ($min === null || $max === null) {
                $errors[] = "{$name}: the range must be numbers such as 40 or 2.68.";

                continue;
            }
            if ($min % $step !== 0 || $max % $step !== 0) {
                $errors[] = $decimals === 0
                    ? "{$name}: use whole numbers. This scheme rounds to whole numbers before the band lookup."
                    : "{$name}: use at most {$decimals} decimal ".($decimals === 1 ? 'place' : 'places').'. This scheme rounds to that before the band lookup.';
            }
            if ($min > $max) {
                $errors[] = "{$name}: the bottom of the range (".self::display($min).') is above the top ('.self::display($max).').';
            }
            if ($max > $topH) {
                $errors[] = "{$name}: goes above ".self::display($topH).', the top of the range.';
            }

            $rawValue = trim((string) ($band['value'] ?? ''));
            $value = null;
            if ($valueLabel !== null) {
                $value = self::hundredths($rawValue);
                if ($value === null || $value >= 10000) {
                    $errors[] = "{$name} needs its ".mb_strtolower($valueLabel).', a number such as 10 or 3.67.';
                } elseif ($valueMaxH !== null && $value > $valueMaxH) {
                    $errors[] = "{$name}: the ".mb_strtolower($valueLabel).' can\'t be above '.self::display($valueMaxH).'.';
                }
            } elseif ($rawValue !== '') {
                $errors[] = "{$name}: this band set has no points or grade values. Leave the value empty.";
            }

            $rows[] = ['name' => $name, 'min' => $min, 'max' => $max, 'value' => $value, 'fail' => filter_var($band['is_fail'] ?? false, FILTER_VALIDATE_BOOLEAN)];
        }

        if ($errors !== []) {
            return $errors;
        }

        usort($rows, fn ($a, $b) => [$a['min'], $a['max']] <=> [$b['min'], $b['max']]);

        if ($rows[0]['min'] !== 0) {
            $errors[] = "The bands must start at 0. The lowest band, {$rows[0]['name']}, starts at ".self::display($rows[0]['min']).'.';
        }
        for ($i = 1; $i < count($rows); $i++) {
            [$below, $band] = [$rows[$i - 1], $rows[$i]];
            if ($band['min'] <= $below['max']) {
                $errors[] = "{$below['name']} and {$band['name']} overlap: ".self::display($band['min']).' to '.self::display(min($below['max'], $band['max'])).' is in both.';
            } elseif ($band['min'] > $below['max'] + $step) {
                $errors[] = "There is a gap between {$below['name']} (up to ".self::display($below['max']).") and {$band['name']} (from ".self::display($band['min']).').';
            }
        }
        $last = $rows[count($rows) - 1];
        if ($last['max'] !== $topH) {
            $errors[] = 'The bands must reach '.self::display($topH).". The highest band, {$last['name']}, stops at ".self::display($last['max']).'.';
        }

        $passSeen = false;
        foreach ($rows as $row) {
            if (! $row['fail']) {
                $passSeen = true;
            } elseif ($passSeen) {
                $errors[] = "{$row['name']} is a fail band above a passing band. Fail bands must be the lowest.";
            }
        }
        if (! collect($rows)->contains('fail', true)) {
            $errors[] = 'Mark the lowest band as a fail band. A set needs at least one.';
        }
        if (! $passSeen) {
            $errors[] = 'At least one band must pass.';
        }

        if ($valueLabel !== null) {
            for ($i = 1; $i < count($rows); $i++) {
                if ($rows[$i]['value'] < $rows[$i - 1]['value']) {
                    $errors[] = "{$rows[$i]['name']} is worth less (".self::display($rows[$i]['value']).") than the lower band {$rows[$i - 1]['name']} (".self::display($rows[$i - 1]['value']).'). A higher band can\'t be worth less.';
                }
            }
        }

        return $errors;
    }
}
