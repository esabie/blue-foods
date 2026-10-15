<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case MobileMoney = 'mobile_money';
    case Card = 'card';
    case PayOnDelivery = 'pay_on_delivery';

    public function label(): string
    {
        return match ($this) {
            self::MobileMoney => 'Mobile money',
            self::Card => 'Card',
            self::PayOnDelivery => 'Pay on delivery',
        };
    }

    public function isOnline(): bool
    {
        return $this !== self::PayOnDelivery;
    }
}
