<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Support;

/**
 * Single source of truth for dashboard money arithmetic. DECIMAL columns come
 * out of PDO as strings; bcmath preserves precision across subtract / percent /
 * divide before we flatten to float at the API boundary.
 *
 * Callers still receive floats (JSON-friendly). The difference is that sum/
 * subtract/percent happen against full-precision DECIMAL strings rather than
 * chained float operations — eliminates drift when refunds / targets / deltas
 * compose across multiple scopes.
 */
final class Money
{
    public const int SCALE = 4;

    public const int OUTPUT_SCALE = 2;

    public static function toFloat(int|float|string|null $value, int $scale = self::OUTPUT_SCALE): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) bcadd((string) $value, '0', $scale);
    }

    public static function subtract(int|float|string|null $a, int|float|string|null $b): float
    {
        $result = bcsub(self::normalize($a), self::normalize($b), self::SCALE);

        return (float) bcadd($result, '0', self::OUTPUT_SCALE);
    }

    /**
     * Percentage change ((current - previous) / previous) * 100. Returns null
     * when previous is zero or negative — a "+∞%" delta carries no signal.
     */
    public static function percentDelta(int|float|string|null $current, int|float|string|null $previous): ?float
    {
        $prev = self::normalize($previous);

        if (bccomp($prev, '0', self::SCALE) <= 0) {
            return null;
        }

        $cur = self::normalize($current);
        $delta = bcsub($cur, $prev, self::SCALE);
        $pct = bcmul(bcdiv($delta, $prev, self::SCALE), '100', self::SCALE);

        return (float) bcadd($pct, '0', 1);
    }

    /**
     * Percent of target, clamped-null when target is missing or zero.
     */
    public static function percentOf(int|float|string|null $value, int|float|string|null $target): ?float
    {
        $tgt = self::normalize($target);

        if (bccomp($tgt, '0', self::SCALE) <= 0) {
            return null;
        }

        $pct = bcmul(bcdiv(self::normalize($value), $tgt, self::SCALE), '100', self::SCALE);

        return (float) bcadd($pct, '0', 1);
    }

    /**
     * Safe divide — returns 0.0 on zero denominator instead of throwing.
     */
    public static function divide(int|float|string|null $a, int|float|string|null $b): float
    {
        $denom = self::normalize($b);

        if (bccomp($denom, '0', self::SCALE) === 0) {
            return 0.0;
        }

        return (float) bcdiv(self::normalize($a), $denom, self::OUTPUT_SCALE);
    }

    private static function normalize(int|float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, self::SCALE, '.', ''), '0'), '.') ?: '0';
        }

        return (string) $value;
    }
}
