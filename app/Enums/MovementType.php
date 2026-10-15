<?php

namespace App\Enums;

enum MovementType: string
{
    case Produced = 'PRODUCED';
    case AdjustedIn = 'ADJUSTED_IN';
    case AdjustedOut = 'ADJUSTED_OUT';
    case Assigned = 'ASSIGNED';
    case Returned = 'RETURNED';
    case AllocatedOnline = 'ALLOCATED_ONLINE';
    case Sold = 'SOLD';
    case SoldOnline = 'SOLD_ONLINE';
    case SaleReversed = 'SALE_REVERSED';

    public function label(): string
    {
        return match ($this) {
            self::Produced => 'Produced',
            self::AdjustedIn => 'Adjusted in',
            self::AdjustedOut => 'Adjusted out',
            self::Assigned => 'Assigned',
            self::Returned => 'Returned',
            self::AllocatedOnline => 'Allocated online',
            self::Sold => 'Sold',
            self::SoldOnline => 'Sold online',
            self::SaleReversed => 'Sale reversed',
        };
    }
}
