<?php

namespace App\Support;

final class Phone
{
    /**
     * Normalise Ghana numbers so "024 123 4567" and "+233241234567" match the same account.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '233'.substr($digits, 1);
        }

        return '+'.$digits;
    }

    public static function looksLikePhone(string $value): bool
    {
        return (bool) preg_match('/^\+?[\d\s\-()]{9,20}$/', trim($value));
    }
}
