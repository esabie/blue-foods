<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * A cash hand-in rule refused the entry; shown next to the form field like a validation error.
 */
class CashRuleException extends ValidationException
{
    public static function for(string $field, string $message): self
    {
        return self::withMessages([$field => $message]);
    }
}
