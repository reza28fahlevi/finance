<?php

namespace App\Support;

/**
 * Aritmetika uang presisi tetap (2 desimal) berbasis bcmath — tidak memakai float.
 */
final class Money
{
    public const SCALE = 2;

    /** Normalisasi input (string/int/float/null) ke string desimal 2 digit. */
    public static function normalize(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        $str = trim((string) $value);
        $str = str_replace([' ', '_'], '', $str);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $str)) {
            throw new \InvalidArgumentException("Nilai uang tidak valid: {$value}");
        }

        return bcadd($str, '0', self::SCALE);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    public static function cmp(string $a, string $b): int
    {
        return bccomp($a, $b, self::SCALE);
    }

    public static function isPositive(string $a): bool
    {
        return self::cmp($a, '0') === 1;
    }

    public static function isZero(string $a): bool
    {
        return self::cmp($a, '0') === 0;
    }

    public static function abs(string $a): string
    {
        return ltrim($a, '-');
    }

    /** Format tampilan Rupiah: "Rp 1.250.000" (desimal hanya bila ada sen). */
    public static function format(mixed $value, bool $withSymbol = true): string
    {
        $value = self::normalize($value);
        $negative = str_starts_with($value, '-');
        [$int, $dec] = explode('.', self::abs($value));
        $formatted = number_format((int) $int, 0, ',', '.');
        if ($dec !== '00') {
            $formatted .= ','.$dec;
        }

        return ($negative ? '-' : '').($withSymbol ? 'Rp ' : '').$formatted;
    }
}
