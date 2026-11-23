<?php

namespace App\Enums;

enum StockRequestStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
