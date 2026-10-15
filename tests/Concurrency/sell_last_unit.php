<?php

/**
 * Worker for LastUnitTest: boots the app in its own process and tries to sell one unit.
 * Usage: php sell_last_unit.php <memberId> <productId> <startAt>
 */

use App\Exceptions\StockRuleException;
use App\Models\Product;
use App\Models\User;
use App\Services\StockLedger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$_, $memberId, $productId, $startAt] = $argv;

$member = User::findOrFail($memberId);
$product = Product::findOrFail($productId);

// Widen the race window: pause after reading the balance and before writing the sale.
DB::listen(function (QueryExecuted $query) {
    if (str_contains($query->sql, 'sum(') && str_contains($query->sql, 'stock_movements')) {
        usleep(300_000);
    }
});

// Start every worker at the same moment.
while (microtime(true) < (float) $startAt) {
    usleep(1_000);
}

try {
    app(StockLedger::class)->recordSale($member, $product, 1);
    echo 'sold';
} catch (StockRuleException) {
    echo 'refused';
}
