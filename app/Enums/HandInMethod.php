<?php

namespace App\Enums;

enum HandInMethod: string
{
    case Cash = 'cash';
    case MobileMoney = 'mobile_money';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::MobileMoney => 'Mobile money',
        };
    }
}
