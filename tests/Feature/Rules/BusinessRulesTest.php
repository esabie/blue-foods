<?php

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Exceptions\StockRuleException;
use App\Models\Location;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockPosition;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->w = stockWorld();
    $this->warehouseId = Location::warehouse()->id;
    $this->memberLocationId = $this->w->member->location->id;
});

/*
| B1: no stock balance at any location can go below zero.
*/

test('B1: an adjustment cannot take the warehouse below zero', function () {
    ledger()->produce($this->w->product, 5, $this->w->warehouse);

    expect(fn () => ledger()->adjust($this->w->product, 6, false, AdjustmentReason::Damaged, null, $this->w->warehouse))
        ->toThrow(StockRuleException::class);

    expect(ledger()->balance($this->w->product->id, $this->warehouseId))->toBe(5);
});

test('B1: assignment cannot exceed unassigned stock', function () {
    ledger()->produce($this->w->product, 5, $this->w->warehouse);

    expect(fn () => ledger()->assign($this->w->product, $this->w->member, 6, $this->w->manager))
        ->toThrow(StockRuleException::class);
});

test('B1: online allocation cannot exceed unassigned stock', function () {
    ledger()->produce($this->w->product, 2, $this->w->warehouse);

    expect(fn () => ledger()->allocateOnline($this->w->product, 3, $this->w->manager))
        ->toThrow(StockRuleException::class);
});

test('B1: a member cannot sell more than they hold', function () {
    ledger()->produce($this->w->product, 10, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 3, $this->w->manager);

    expect(fn () => ledger()->recordSale($this->w->member, $this->w->product, 4))
        ->toThrow(StockRuleException::class);

    expect(ledger()->balance($this->w->product->id, $this->memberLocationId))->toBe(3);
});

test('B1: a return request cannot exceed the member balance', function () {
    ledger()->produce($this->w->product, 10, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 3, $this->w->manager);

    expect(fn () => ledger()->requestReturn($this->w->member, $this->w->product, 4))
        ->toThrow(StockRuleException::class);
});

test('B1: no location ever shows a negative balance in the ledger view', function () {
    ledger()->produce($this->w->product, 10, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 4, $this->w->manager);
    ledger()->recordSale($this->w->member, $this->w->product, 4);

    expect(app(StockPosition::class)->negativeBalances())->toBeEmpty();
});

/*
| B2: only management sets prices; sales and assignments store the price in force at the time.
*/

test('B2: only management can change a price', function (string $role) {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)
        ->post(route('products.price', $this->w->product), ['price' => '1.00'])
        ->assertForbidden();

    expect($this->w->product->currentPricePesewas())->toBe(2500);
})->with(['warehouse', 'marketing']);

test('B2: assignments and sales keep the price in force when they were made', function () {
    ledger()->produce($this->w->product, 10, $this->w->warehouse);
    $assignment = ledger()->assign($this->w->product, $this->w->member, 5, $this->w->manager);
    $firstSale = ledger()->recordSale($this->w->member, $this->w->product, 1);

    $this->actingAs($this->w->manager)
        ->post(route('products.price', $this->w->product), ['price' => '30.00'])
        ->assertSessionHasNoErrors();

    $secondSale = ledger()->recordSale($this->w->member, $this->w->product, 1);

    expect($assignment->fresh()->unit_price_pesewas)->toBe(2500)
        ->and($firstSale->fresh()->unit_price_pesewas)->toBe(2500)
        ->and($secondSale->unit_price_pesewas)->toBe(3000)
        ->and($this->w->product->prices()->count())->toBe(2);
});

/*
| B3: revenue = quantity × stored unit price; no free-text amounts.
*/

test('B3: a sale total is quantity times the stored unit price', function () {
    ledger()->produce($this->w->product, 10, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 10, $this->w->manager);

    $sale = ledger()->recordSale($this->w->member, $this->w->product, 7);

    expect($sale->total_pesewas)->toBe(7 * 2500)
        ->and($sale->movement->unit_price_pesewas)->toBe(2500);
});

test('B3: price and total sent by the client are ignored', function () {
    ledger()->produce($this->w->product, 10, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 10, $this->w->manager);

    $this->actingAs($this->w->member)->post(route('member.sales.store'), [
        'product_id' => $this->w->product->id,
        'quantity' => 2,
        'unit_price_pesewas' => 1,
        'total_pesewas' => 1,
    ])->assertSessionHasNoErrors();

    $sale = Sale::sole();
    expect($sale->unit_price_pesewas)->toBe(2500)->and($sale->total_pesewas)->toBe(5000);
});

/*
| B4: every movement records the user, the timestamp and, for adjustments and reversals, a reason.
*/

test('B4: every movement records who made it and when', function () {
    ledger()->produce($this->w->product, 10, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 5, $this->w->manager);
    ledger()->recordSale($this->w->member, $this->w->product, 1);

    StockMovement::all()->each(function (StockMovement $m) {
        expect($m->created_by)->not->toBeNull()
            ->and($m->created_at)->not->toBeNull()
            ->and($m->occurred_at)->not->toBeNull();
    });

    expect(StockMovement::where('type', MovementType::Produced)->sole()->created_by)->toBe($this->w->warehouse->id)
        ->and(StockMovement::where('type', MovementType::Sold)->sole()->created_by)->toBe($this->w->member->id);
});

test('B4: an adjustment cannot be saved without a reason', function () {
    $this->actingAs($this->w->warehouse)->post(route('stock.adjust'), [
        'product_id' => $this->w->product->id,
        'direction' => 'in',
        'quantity' => 1,
    ])->assertSessionHasErrors('reason');

    expect(StockMovement::count())->toBe(0);
});

test('B4: an "other" adjustment needs an explanation', function () {
    expect(fn () => ledger()->adjust($this->w->product, 1, true, AdjustmentReason::Other, '', $this->w->warehouse))
        ->toThrow(StockRuleException::class);
});

test('B4: a sale reversal requires a reason and stores it', function () {
    ledger()->produce($this->w->product, 5, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 5, $this->w->manager);
    $sale = ledger()->recordSale($this->w->member, $this->w->product, 1);

    expect(fn () => ledger()->reverseSale($sale, ' ', $this->w->manager))->toThrow(StockRuleException::class);

    $reversal = ledger()->reverseSale($sale, 'Customer returned spoiled pack', $this->w->manager);
    expect($reversal->reason)->toBe('Customer returned spoiled pack')
        ->and($reversal->created_by)->toBe($this->w->manager->id);
});

/*
| B5: records are never edited or deleted; corrections are new entries that reference the original.
*/

test('B5: ledger records cannot be edited or deleted through the application', function () {
    $movement = ledger()->produce($this->w->product, 5, $this->w->warehouse);

    expect(fn () => $movement->update(['quantity' => 50]))->toThrow(LogicException::class)
        ->and(fn () => $movement->delete())->toThrow(LogicException::class);
});

test('B5: the database itself refuses edits and deletes to append-only tables', function (string $table) {
    ledger()->produce($this->w->product, 5, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 5, $this->w->manager);
    ledger()->recordSale($this->w->member, $this->w->product, 1);

    expect(DB::table($table)->count())->toBeGreaterThan(0);

    $column = Schema::hasColumn($table, 'quantity') ? 'quantity' : 'created_at';

    expect(fn () => DB::table($table)->update([$column => null]))->toThrow(QueryException::class)
        ->and(fn () => DB::table($table)->delete())->toThrow(QueryException::class);
})->with(['stock_movements', 'sales', 'product_prices', 'audit_logs']);

test('B5: a reversal is a new entry that references the original, which is kept', function () {
    ledger()->produce($this->w->product, 5, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 5, $this->w->manager);
    $sale = ledger()->recordSale($this->w->member, $this->w->product, 2);

    $reversal = ledger()->reverseSale($sale, 'Entered twice', $this->w->manager);

    expect($reversal->ref_movement_id)->toBe($sale->movement_id)
        ->and(Sale::find($sale->id))->not->toBeNull()
        ->and(StockMovement::find($sale->movement_id)->quantity)->toBe(2)
        ->and($sale->fresh()->reversal->id)->toBe($reversal->id);
});

/*
| B6: produced + adjustments = unassigned + with team + online + sold, for every product.
*/

test('B6: stock reconciles for every product after every kind of movement', function () {
    $w = $this->w;
    $other = User::factory()->marketing()->create();
    $second = Product::factory()->priced(1000)->create();

    ledger()->produce($w->product, 100, $w->warehouse);
    ledger()->produce($second, 40, $w->warehouse);
    ledger()->adjust($w->product, 3, false, AdjustmentReason::Damaged, null, $w->warehouse);
    ledger()->adjust($second, 2, true, AdjustmentReason::Recount, null, $w->warehouse);
    ledger()->assign($w->product, $w->member, 30, $w->manager);
    ledger()->assign($w->product, $other, 20, $w->manager);
    ledger()->assign($second, $w->member, 10, $w->manager);
    ledger()->allocateOnline($w->product, 15, $w->manager);
    $sale = ledger()->recordSale($w->member, $w->product, 12);
    ledger()->recordSale($other, $w->product, 5);
    ledger()->recordSale($w->member, $second, 4);
    ledger()->reverseSale($sale, 'Wrong product', $w->manager);
    $return = ledger()->requestReturn($other, $w->product, 6);
    ledger()->confirmReturn($return, $w->warehouse);

    $rows = app(StockPosition::class)->byProduct()->keyBy('product_id');

    foreach ([$w->product, $second] as $product) {
        $row = $rows[$product->id];
        expect($row['produced'] + $row['adjusted'])
            ->toBe($row['unassigned'] + $row['with_team'] + $row['online'] + $row['sold'])
            ->and($row['reconciles'])->toBeTrue();
    }

    expect($rows[$w->product->id])->toMatchArray([
        'produced' => 100, 'adjusted' => -3, 'unassigned' => 38,
        'with_team' => 39, 'online' => 15, 'sold' => 5,
    ]);

    expect(Artisan::call('stock:check'))->toBe(0);
});

/*
| B7: two sales for the last unit cannot both succeed.
| The truly concurrent version runs against MySQL in tests/Concurrency.
*/

test('B7: a second sale for the last unit is refused', function () {
    ledger()->produce($this->w->product, 1, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 1, $this->w->manager);

    ledger()->recordSale($this->w->member, $this->w->product, 1);

    expect(fn () => ledger()->recordSale($this->w->member, $this->w->product, 1))
        ->toThrow(StockRuleException::class);

    expect(Sale::count())->toBe(1);
});

/*
| B8: all amounts are Ghana cedis stored as integer pesewas.
*/

test('B8: cedi amounts convert to integer pesewas without rounding errors', function (string $cedis, int $pesewas) {
    expect(Money::toPesewas($cedis))->toBe($pesewas)
        ->and(Money::toCedis($pesewas))->toBe(number_format($pesewas / 100, 2, '.', ''));
})->with([
    ['0.1', 10],
    ['0.29', 29],
    ['19.99', 1999],
    ['1234567.89', 123456789],
    ['25', 2500],
]);

test('B8: amounts with fractions of a pesewa are rejected', function () {
    $this->actingAs($this->w->manager)
        ->post(route('products.price', $this->w->product), ['price' => '12.345'])
        ->assertSessionHasErrors('price');

    expect(fn () => Money::toPesewas('12.345'))->toThrow(InvalidArgumentException::class);
});

test('B8: money columns are integers', function () {
    ledger()->produce($this->w->product, 3, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 3, $this->w->manager);
    ledger()->recordSale($this->w->member, $this->w->product, 3);

    $row = DB::table('sales')->first();
    expect((string) $row->total_pesewas)->toMatch('/^\d+$/')
        ->and((int) $row->total_pesewas)->toBe(7500);
});
