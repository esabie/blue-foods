<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Packed = 'packed';
    case Delivered = 'delivered';
    case Collected = 'collected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending payment',
            self::Paid => 'Paid',
            self::Packed => 'Packed',
            self::Delivered => 'Delivered',
            self::Collected => 'Collected',
            self::Cancelled => 'Cancelled',
        };
    }
}
