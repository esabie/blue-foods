<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Rule B8: amounts are Ghana cedis stored as integer pesewas.
 */
final class Money
{
    public const PATTERN = '/^\d{1,9}(\.\d{1,2})?$/';

    /**
     * Convert a cedi string such as "12.5" into pesewas without floating point.
     */
    public static function toPesewas(string $cedis): int
    {
        $cedis = trim($cedis);

        if (! preg_match(self::PATTERN, $cedis)) {
            throw new InvalidArgumentException("Invalid cedi amount: {$cedis}");
        }

        [$whole, $fraction] = array_pad(explode('.', $cedis, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function toCedis(int $pesewas): string
    {
        return intdiv($pesewas, 100).'.'.str_pad((string) ($pesewas % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function format(int $pesewas): string
    {
        return 'GHS '.number_format(intdiv($pesewas, 100)).'.'.str_pad((string) ($pesewas % 100), 2, '0', STR_PAD_LEFT);
    }
}
