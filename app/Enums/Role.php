<?php

namespace App\Enums;

enum Role: string
{
    case Warehouse = 'warehouse';
    case Marketing = 'marketing';
    case Management = 'management';

    public function label(): string
    {
        return match ($this) {
            self::Warehouse => 'Warehouse staff',
            self::Marketing => 'Marketing team member',
            self::Management => 'Management',
        };
    }
}
