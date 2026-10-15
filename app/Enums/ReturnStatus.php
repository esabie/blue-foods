<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
