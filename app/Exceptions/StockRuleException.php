<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * A stock business rule (section 4) refused the movement.
 * Extends ValidationException so the form shows the message next to the field.
 */
class StockRuleException extends ValidationException
{
    public static function for(string $field, string $message): self
    {
        return self::withMessages([$field => $message]);
    }
}
