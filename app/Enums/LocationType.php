<?php

namespace App\Enums;

enum LocationType: string
{
    case Warehouse = 'warehouse';
    case Member = 'member';
    case OnlineShop = 'online_shop';
}
