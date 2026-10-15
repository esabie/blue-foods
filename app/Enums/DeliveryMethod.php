<?php

namespace App\Enums;

enum DeliveryMethod: string
{
    case Delivery = 'delivery';
    case Collection = 'collection';

    public function label(): string
    {
        return match ($this) {
            self::Delivery => 'Delivery',
            self::Collection => 'Collection',
        };
    }
}
