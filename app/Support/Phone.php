<?php

namespace App\Support;

/**
 * Bangladeshi mobile numbers: one canonical form (01XXXXXXXXX, 11 digits) everywhere.
 * Pure helper with no database or framework dependency.
 *
 *   +8801712-345678, 8801712345678, 008801712345678, ০১৭১২৩৪৫৬৭৮, 1712345678  ->  01712345678
 */
class Phone
{
    private const MAP = [
        // Bengali
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
        // Arabic-Indic and Eastern Arabic-Indic
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /** Bangla / Arabic digits -> 0-9 (other characters are kept) */
    public static function latinDigits(string $text): string
    {
        return strtr($text, self::MAP);
    }

    /** Only the digits 0-9 of the text (after converting Bangla digits) */
    public static function digits(?string $text): string
    {
        return preg_replace('/\D+/', '', self::latinDigits((string) $text));
    }

    /** 01XXXXXXXXX, or null when the text is not a valid Bangladeshi mobile number */
    public static function normalize(?string $phone): ?string
    {
        $d = self::digits($phone);

        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        }
        if (str_starts_with($d, '880')) {
            $d = substr($d, 3);
        }
        if (strlen($d) === 10 && $d[0] === '1') {
            $d = '0' . $d;
        }

        return preg_match('/^01[3-9]\d{8}$/', $d) ? $d : null;
    }

    public static function isValid(?string $phone): bool
    {
        return self::normalize($phone) !== null;
    }

    /** +8801XXXXXXXXX */
    public static function international(?string $phone): ?string
    {
        $n = self::normalize($phone);

        return $n ? '+88' . $n : null;
    }

    /**
     * Digits to use in a LIKE search for part of a number: country code and leading 00 are dropped,
     * so "+8801712" finds "01712345678". Returns '' when fewer than 4 digits are left.
     */
    public static function fragment(?string $text): string
    {
        $d = self::digits($text);

        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        }
        if (strlen($d) > 3 && str_starts_with($d, '880')) {
            $d = substr($d, 3);
        }

        return strlen($d) >= 4 ? $d : '';
    }

    /** Every way the same number may have been stored in older data (for exact-match lookups) */
    public static function variants(string $normalized): array
    {
        $n = self::normalize($normalized);
        if ($n === null) {
            return [$normalized];
        }
        $core = substr($n, 1); // 1712345678

        return [$n, '88' . $n, '+88' . $n, '880' . $core, '+880' . $core, $core];
    }
}
