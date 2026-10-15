<?php

/**
 * Worker for LastUnitTest: boots the app in its own process and tries to order the last online unit.
 * Usage: php order_last_online_unit.php <productId> <startAt>
 */

use App\Exceptions\StockRuleException;
use App\Services\Shop\OnlineShop;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$_, $productId, $startAt] = $argv;

// Widen the race window: pause after reading availability and before writing the order.
DB::listen(function (QueryExecuted $query) {
    if (stripos($query->sql, 'sum(') !== false && str_contains($query->sql, 'order_items')) {
        usleep(300_000);
    }
});

while (microtime(true) < (float) $startAt) {
    usleep(1_000);
}

try {
    app(OnlineShop::class)->placeOrder([(int) $productId => 1], [
        'customer_name' => 'Racer',
        'phone' => '+233240000000',
        'address' => 'Accra',
        'delivery_method' => 'delivery',
        'payment_method' => 'mobile_money',
    ]);
    echo 'ordered';
} catch (StockRuleException) {
    echo 'refused';
}
