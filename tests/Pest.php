<?php

use App\Enums\MovementType;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Shop\OnlineShop;
use App\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Concurrency tests run real parallel processes against MySQL, so they manage their own database.
pest()->extend(TestCase::class)
    ->in('Concurrency');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function ledger(): StockLedger
{
    return app(StockLedger::class);
}

/**
 * A management user, a warehouse user, a marketing member and a product priced at GHS 25.00.
 *
 * @return object{manager: User, warehouse: User, member: User, product: Product}
 */
function stockWorld(int $pricePesewas = 2500): object
{
    return (object) [
        'manager' => User::factory()->management()->create(),
        'warehouse' => User::factory()->warehouse()->create(),
        'member' => User::factory()->marketing()->create(),
        'product' => Product::factory()->priced($pricePesewas)->create(),
    ];
}

function soldOnline(): int
{
    return (int) StockMovement::where('type', MovementType::SoldOnline)->sum('quantity');
}

function shop(): OnlineShop
{
    return app(OnlineShop::class);
}

/**
 * Place an online order for the given product quantities.
 *
 * @param  array<int, int>  $items  product id => quantity
 */
function placeOnlineOrder(array $items, string $payment = 'mobile_money', string $delivery = 'delivery'): OnlineOrder
{
    return shop()->placeOrder($items, [
        'customer_name' => 'Abena Customer',
        'phone' => '+233241234567',
        'address' => $delivery === 'delivery' ? '12 Ring Road, Accra' : null,
        'delivery_method' => $delivery,
        'payment_method' => $payment,
    ]);
}
