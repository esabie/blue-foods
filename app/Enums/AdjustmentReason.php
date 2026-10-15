<?php

namespace App\Enums;

enum AdjustmentReason: string
{
    case Damaged = 'damaged';
    case Expired = 'expired';
    case Recount = 'recount';
    case Other = 'other';
}
