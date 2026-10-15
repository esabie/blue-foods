<?php

use App\Enums\ReturnStatus;
use App\Exceptions\StockRuleException;
use App\Models\Location;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\StockMovement;
use App\Models\User;

beforeEach(function () {
    $this->w = stockWorld();
});

test('S1: recording production raises the warehouse balance', function () {
    $this->actingAs($this->w->warehouse)->post(route('stock.produce'), [
        'product_id' => $this->w->product->id,
        'quantity' => 40,
        'produced_on' => now()->subDay()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect(ledger()->balance($this->w->product->id, Location::warehouse()->id))->toBe(40)
        ->and(StockMovement::sole()->occurred_at->toDateString())->toBe(now()->subDay()->toDateString());
});

test('S1: production cannot be dated in the future', function () {
    $this->actingAs($this->w->warehouse)->post(route('stock.produce'), [
        'product_id' => $this->w->product->id,
        'quantity' => 1,
        'produced_on' => now()->addDay()->toDateString(),
    ])->assertSessionHasErrors('produced_on');
});

test('P3: retired products cannot be produced or assigned but stay in history', function () {
    ledger()->produce($this->w->product, 5, $this->w->warehouse);
    $this->w->product->update(['status' => 'retired']);

    expect(fn () => ledger()->produce($this->w->product, 1, $this->w->warehouse))->toThrow(StockRuleException::class)
        ->and(fn () => ledger()->assign($this->w->product, $this->w->member, 1, $this->w->manager))->toThrow(StockRuleException::class)
        ->and(StockMovement::count())->toBe(1);
});

test('A1: management assigns stock to a member through the form', function () {
    ledger()->produce($this->w->product, 10, $this->w->warehouse);

    $this->actingAs($this->w->manager)->post(route('stock.assign'), [
        'product_id' => $this->w->product->id,
        'member_id' => $this->w->member->id,
        'quantity' => 4,
    ])->assertSessionHasNoErrors();

    expect(ledger()->balance($this->w->product->id, $this->w->member->location->id))->toBe(4);
});

test('A1: stock can only be assigned to an active marketing member', function () {
    ledger()->produce($this->w->product, 10, $this->w->warehouse);
    $disabled = User::factory()->marketing()->disabled()->create();

    expect(fn () => ledger()->assign($this->w->product, $this->w->warehouse, 1, $this->w->manager))->toThrow(StockRuleException::class)
        ->and(fn () => ledger()->assign($this->w->product, $disabled, 1, $this->w->manager))->toThrow(StockRuleException::class);
});

test('Q1: warehouse staff may assign only when the setting allows it', function () {
    ledger()->produce($this->w->product, 10, $this->w->warehouse);
    $payload = ['product_id' => $this->w->product->id, 'member_id' => $this->w->member->id, 'quantity' => 1];

    $this->actingAs($this->w->warehouse)->post(route('stock.assign'), $payload)->assertForbidden();

    config(['inventory.warehouse_can_assign' => true]);
    $this->actingAs($this->w->warehouse)->post(route('stock.assign'), $payload)->assertSessionHasNoErrors();
});

test('A3/A4: a return moves stock only when the warehouse confirms it', function () {
    ledger()->produce($this->w->product, 10, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 6, $this->w->manager);
    $memberLoc = $this->w->member->location->id;
    $warehouseLoc = Location::warehouse()->id;

    $this->actingAs($this->w->member)->post(route('member.returns.store'), [
        'product_id' => $this->w->product->id,
        'quantity' => 4,
    ])->assertSessionHasNoErrors();

    expect(ledger()->balance($this->w->product->id, $memberLoc))->toBe(6)
        ->and(ledger()->balance($this->w->product->id, $warehouseLoc))->toBe(4);

    $request = ReturnRequest::sole();
    $this->actingAs($this->w->warehouse)->post(route('returns.confirm', $request))->assertSessionHasNoErrors();

    expect(ledger()->balance($this->w->product->id, $memberLoc))->toBe(2)
        ->and(ledger()->balance($this->w->product->id, $warehouseLoc))->toBe(8)
        ->and($request->fresh()->status)->toBe(ReturnStatus::Confirmed)
        ->and($request->fresh()->confirmed_by)->toBe($this->w->warehouse->id);
});

test('A4: a return cannot be confirmed twice, and a rejected return moves nothing', function () {
    ledger()->produce($this->w->product, 10, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 6, $this->w->manager);

    $first = ledger()->requestReturn($this->w->member, $this->w->product, 2);
    ledger()->confirmReturn($first, $this->w->warehouse);
    expect(fn () => ledger()->confirmReturn($first, $this->w->warehouse))->toThrow(StockRuleException::class);

    $second = ledger()->requestReturn($this->w->member, $this->w->product, 2);
    ledger()->rejectReturn($second, $this->w->warehouse, 'Not received');

    expect(ledger()->balance($this->w->product->id, $this->w->member->location->id))->toBe(4)
        ->and($second->fresh()->status)->toBe(ReturnStatus::Rejected);
});

test('stock awaiting return cannot also be sold', function () {
    ledger()->produce($this->w->product, 5, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 5, $this->w->manager);
    ledger()->requestReturn($this->w->member, $this->w->product, 4);

    expect(fn () => ledger()->recordSale($this->w->member, $this->w->product, 2))->toThrow(StockRuleException::class);

    ledger()->recordSale($this->w->member, $this->w->product, 1);
    expect(fn () => ledger()->requestReturn($this->w->member, $this->w->product, 1))->toThrow(StockRuleException::class);
});

test('A5: allocating to the online shop moves stock out of the warehouse', function () {
    ledger()->produce($this->w->product, 10, $this->w->warehouse);

    $this->actingAs($this->w->manager)->post(route('stock.allocate-online'), [
        'product_id' => $this->w->product->id,
        'quantity' => 7,
    ])->assertSessionHasNoErrors();

    expect(ledger()->balance($this->w->product->id, Location::onlineShop()->id))->toBe(7)
        ->and(ledger()->balance($this->w->product->id, Location::warehouse()->id))->toBe(3);
});

test('L1: a member sells only from their own stock', function () {
    $other = User::factory()->marketing()->create();
    ledger()->produce($this->w->product, 10, $this->w->warehouse);
    ledger()->assign($this->w->product, $other, 5, $this->w->manager);

    $this->actingAs($this->w->member)->post(route('member.sales.store'), [
        'product_id' => $this->w->product->id,
        'quantity' => 1,
    ])->assertSessionHasErrors('quantity');

    expect(ledger()->balance($this->w->product->id, $other->location->id))->toBe(5);
});

test('L4: reversing a sale returns stock to the member and cannot be done twice', function () {
    ledger()->produce($this->w->product, 5, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 5, $this->w->manager);
    $sale = ledger()->recordSale($this->w->member, $this->w->product, 3);

    $this->actingAs($this->w->manager)
        ->post(route('sales.reverse', $sale), ['reason' => 'Customer cancelled'])
        ->assertSessionHasNoErrors();

    expect(ledger()->balance($this->w->product->id, $this->w->member->location->id))->toBe(5);

    $this->actingAs($this->w->manager)
        ->post(route('sales.reverse', $sale), ['reason' => 'Again'])
        ->assertSessionHasErrors('reason');
});

test('L5: the member home shows their own stock, sales today and revenue', function () {
    $second = Product::factory()->priced(1000)->create();
    ledger()->produce($this->w->product, 10, $this->w->warehouse);
    ledger()->produce($second, 10, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 6, $this->w->manager);
    ledger()->assign($second, $this->w->member, 2, $this->w->manager);
    ledger()->recordSale($this->w->member, $this->w->product, 2);
    $reversed = ledger()->recordSale($this->w->member, $second, 1);
    ledger()->reverseSale($reversed, 'Mistake', $this->w->manager);

    $this->actingAs($this->w->member)->get(route('member.home'))
        ->assertInertia(fn ($page) => $page
            ->component('Member/Home')
            ->has('stock', 2)
            ->where('stock.0.available', $this->w->product->name < $second->name ? 4 : 2)
            ->where('today.sales', 1)
            ->where('today.revenue_pesewas', 5000)
            ->where('month_revenue_pesewas', 5000));
});

test('L6: a customer note is optional', function () {
    ledger()->produce($this->w->product, 5, $this->w->warehouse);
    ledger()->assign($this->w->product, $this->w->member, 5, $this->w->manager);

    $this->actingAs($this->w->member)->post(route('member.sales.store'), [
        'product_id' => $this->w->product->id,
        'quantity' => 1,
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->w->member)->post(route('member.sales.store'), [
        'product_id' => $this->w->product->id,
        'quantity' => 1,
        'customer_note' => 'Ama, 024 000 0000',
    ])->assertSessionHasNoErrors();
});
