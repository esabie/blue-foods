<?php

use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\StockRuleException;
use App\Jobs\SendSms;
use App\Models\Location;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Shop\Cart;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->w = stockWorld(2500);
    ledger()->produce($this->w->product, 500, $this->w->warehouse);
    ledger()->allocateOnline($this->w->product, 10, $this->w->manager);
    $this->online = Location::onlineShop();
});

function onlineBalance(): int
{
    return ledger()->balance(test()->w->product->id, test()->online->id);
}

test('W2: the shop shows availability from the online shop balance only', function () {
    $this->get(route('shop.index'))->assertOk()->assertSee('Only 10 left')->assertSee('GHS 25.00');

    ledger()->allocateOnline($this->w->product, 100, $this->w->manager);
    $this->get(route('shop.index'))->assertSee('In stock');

    // Warehouse and team stock are never offered online.
    expect(shop()->availability([$this->w->product->id])[$this->w->product->id])->toBe(110);
});

test('W2: products with no online stock show Out of stock and cannot be added', function () {
    $other = Product::factory()->priced(1000)->create();
    ledger()->produce($other, 50, $this->w->warehouse);

    $this->get(route('shop.show', $other->sku))->assertOk()->assertSee('Out of stock')->assertDontSee('Add to cart');
    $this->post(route('cart.add'), ['product_id' => $other->id, 'quantity' => 1])->assertSessionHasErrors('quantity');
});

test('W2: retired and unpriced products are not sold', function () {
    $retired = Product::factory()->priced(1000)->retired()->create();
    $unpriced = Product::factory()->create();

    $this->get(route('shop.show', $retired->sku))->assertNotFound();
    $this->get(route('shop.show', $unpriced->sku))->assertNotFound();
    $this->get(route('shop.index'))->assertOk()->assertDontSee($retired->name)->assertDontSee($unpriced->name);
});

test('W3: cannot add more than the online shop balance, including what is already in the cart', function () {
    $this->post(route('cart.add'), ['product_id' => $this->w->product->id, 'quantity' => 11])->assertSessionHasErrors('quantity');
    $this->post(route('cart.add'), ['product_id' => $this->w->product->id, 'quantity' => 8])->assertRedirect(route('cart.show'));
    $this->post(route('cart.add'), ['product_id' => $this->w->product->id, 'quantity' => 3])->assertSessionHasErrors('quantity');

    expect(app(Cart::class)->raw())->toBe([$this->w->product->id => 8]);
});

test('W4: order total = sum of item totals + delivery fee; collection has no fee', function () {
    SiteSetting::put(['delivery_fee_pesewas' => '1550']);
    $second = Product::factory()->priced(1299)->create();
    ledger()->produce($second, 20, $this->w->warehouse);
    ledger()->allocateOnline($second, 20, $this->w->manager);

    $delivered = placeOnlineOrder([$this->w->product->id => 3, $second->id => 2]);
    expect($delivered->subtotal_pesewas)->toBe(3 * 2500 + 2 * 1299)
        ->and($delivered->delivery_fee_pesewas)->toBe(1550)
        ->and($delivered->total_pesewas)->toBe(3 * 2500 + 2 * 1299 + 1550)
        ->and($delivered->items->sum('total_pesewas'))->toBe($delivered->subtotal_pesewas);

    $collected = placeOnlineOrder([$second->id => 1], 'card', 'collection');
    expect($collected->delivery_fee_pesewas)->toBe(0)->and($collected->total_pesewas)->toBe(1299)->and($collected->address)->toBeNull();
});

test('W4: checkout validates customer details and requires an address for delivery', function () {
    $this->post(route('cart.add'), ['product_id' => $this->w->product->id, 'quantity' => 1]);

    $this->post(route('checkout.store'), [
        'customer_name' => 'Kwame', 'phone' => 'abc', 'delivery_method' => 'delivery', 'payment_method' => 'mobile_money',
    ])->assertSessionHasErrors(['phone', 'address']);

    expect(OnlineOrder::count())->toBe(0);
});

test('W5: placing an order does not sell stock; only the confirmed payment does', function () {
    $order = placeOnlineOrder([$this->w->product->id => 4]);

    expect($order->status)->toBe(OrderStatus::PendingPayment)
        ->and(onlineBalance())->toBe(10)
        ->and(StockMovement::where('type', MovementType::SoldOnline)->count())->toBe(0)
        ->and(shop()->availability([$this->w->product->id])[$this->w->product->id])->toBe(6);

    $payment = shop()->startPayment($order, 'sandbox');
    shop()->confirmPayment($payment, 'PROVIDER-1', $order->total_pesewas);

    $order->refresh();
    $sold = StockMovement::where('type', MovementType::SoldOnline)->sole();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->isPaid())->toBeTrue()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and(onlineBalance())->toBe(6)
        ->and($sold->quantity)->toBe(4)
        ->and($sold->unit_price_pesewas)->toBe(2500)
        ->and($sold->creator->email)->toBe(User::SYSTEM_EMAIL)
        ->and($order->items->first()->fresh()->movement_id)->toBe($sold->id);

    Queue::assertPushed(SendSms::class, fn ($job) => $job->to === '+233241234567' && str_contains($job->message, $order->reference));
});

test('W5: confirming the same payment twice sells once', function () {
    $order = placeOnlineOrder([$this->w->product->id => 2]);
    $payment = shop()->startPayment($order, 'sandbox');

    shop()->confirmPayment($payment, 'P1', $order->total_pesewas);
    shop()->confirmPayment($payment, 'P1', $order->total_pesewas);

    expect(soldOnline())->toBe(2)->and(onlineBalance())->toBe(8);
});

test('W5: a payment for the wrong amount does not mark the order paid', function () {
    $order = placeOnlineOrder([$this->w->product->id => 2]);
    $payment = shop()->startPayment($order, 'sandbox');

    shop()->confirmPayment($payment, 'P1', $order->total_pesewas - 100);

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and(onlineBalance())->toBe(10);
});

test('W5: a second successful payment for a paid order is flagged for refund, not sold again', function () {
    $order = placeOnlineOrder([$this->w->product->id => 2]);
    $first = shop()->startPayment($order, 'sandbox');
    $second = shop()->startPayment($order, 'sandbox');

    shop()->confirmPayment($first, 'P1', $order->total_pesewas);
    shop()->confirmPayment($second, 'P2', $order->total_pesewas);

    expect($order->fresh()->needs_refund)->toBeTrue()->and(onlineBalance())->toBe(8);
});

test('W6: stock is held for 15 minutes, then released', function () {
    $order = placeOnlineOrder([$this->w->product->id => 10]);
    expect(shop()->availability([$this->w->product->id])[$this->w->product->id])->toBe(0);

    $this->travel(14)->minutes();
    Artisan::call('shop:release-expired');
    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and(shop()->availability([$this->w->product->id])[$this->w->product->id])->toBe(0);

    $this->travel(2)->minutes();
    expect(shop()->availability([$this->w->product->id])[$this->w->product->id])->toBe(10);
    Artisan::call('shop:release-expired');
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(onlineBalance())->toBe(10);

    expect(fn () => shop()->startPayment($order->fresh(), 'sandbox'))->toThrow(StockRuleException::class);
});

test('W6: held stock cannot be sold to a second customer (no online oversell)', function () {
    placeOnlineOrder([$this->w->product->id => 7]);

    expect(fn () => placeOnlineOrder([$this->w->product->id => 4]))->toThrow(StockRuleException::class);
    expect(placeOnlineOrder([$this->w->product->id => 3])->exists)->toBeTrue();
});

test('W6: a late payment is honoured if stock is still free, otherwise flagged for refund', function () {
    $late = placeOnlineOrder([$this->w->product->id => 6]);
    $payment = shop()->startPayment($late, 'sandbox');
    $this->travel(16)->minutes();
    Artisan::call('shop:release-expired');

    // Someone else buys the released stock.
    $other = placeOnlineOrder([$this->w->product->id => 8]);
    shop()->confirmPayment(shop()->startPayment($other, 'sandbox'), 'P-OTHER', $other->total_pesewas);

    shop()->confirmPayment($payment, 'P-LATE', $late->total_pesewas);

    expect($late->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($late->fresh()->needs_refund)->toBeTrue()
        ->and(onlineBalance())->toBe(2);
});

test('W8: paid orders are packed then delivered, each step logged with user and time', function () {
    $order = placeOnlineOrder([$this->w->product->id => 1]);
    shop()->confirmPayment(shop()->startPayment($order, 'sandbox'), 'P1', $order->total_pesewas);

    $this->actingAs($this->w->warehouse)->post(route('orders.complete', $order))->assertSessionHasErrors('order');
    $this->actingAs($this->w->warehouse)->post(route('orders.pack', $order))->assertSessionHasNoErrors();
    $this->actingAs($this->w->warehouse)->post(route('orders.complete', $order))->assertSessionHasNoErrors();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Delivered);
    $log = $order->statusChanges->map(fn ($c) => [$c->to_status->value, $c->user_id])->all();
    expect($log)->toBe([
        ['pending_payment', null],
        ['paid', null],
        ['packed', $this->w->warehouse->id],
        ['delivered', $this->w->warehouse->id],
    ]);
    expect($order->statusChanges->every(fn ($c) => $c->created_at !== null))->toBeTrue();
});

test('W8: unpaid online orders cannot be packed', function () {
    $order = placeOnlineOrder([$this->w->product->id => 1]);

    $this->actingAs($this->w->warehouse)->post(route('orders.pack', $order))->assertSessionHasErrors('order');
    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

test('W9: pay on delivery reserves stock at order time and sells when cash is received', function () {
    $order = placeOnlineOrder([$this->w->product->id => 3], 'pay_on_delivery', 'collection');
    expect($order->reserved_until)->toBeNull();

    // Held beyond 15 minutes.
    $this->travel(2)->hours();
    Artisan::call('shop:release-expired');
    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and(shop()->availability([$this->w->product->id])[$this->w->product->id])->toBe(7)
        ->and(onlineBalance())->toBe(10);

    $this->actingAs($this->w->warehouse)->post(route('orders.pack', $order));
    $this->actingAs($this->w->warehouse)->post(route('orders.complete', $order))->assertSessionHasErrors('order');
    expect(onlineBalance())->toBe(10);

    $this->actingAs($this->w->warehouse)->post(route('orders.complete', $order), ['cash_received' => true])->assertSessionHasNoErrors();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Collected)
        ->and($order->isPaid())->toBeTrue()
        ->and(onlineBalance())->toBe(7)
        ->and($order->payments()->sole()->amount_pesewas)->toBe($order->total_pesewas);
});

test('management can cancel an unpaid order and its stock is released', function () {
    $order = placeOnlineOrder([$this->w->product->id => 5], 'pay_on_delivery');

    $this->actingAs($this->w->manager)->post(route('orders.cancel', $order))->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(shop()->availability([$this->w->product->id])[$this->w->product->id])->toBe(10);
});

test('paid orders cannot be cancelled', function () {
    $order = placeOnlineOrder([$this->w->product->id => 1]);
    shop()->confirmPayment(shop()->startPayment($order, 'sandbox'), 'P1', $order->total_pesewas);

    $this->actingAs($this->w->manager)->post(route('orders.cancel', $order))->assertSessionHasErrors('order');
});

test('the order status page needs the secret token', function () {
    $order = placeOnlineOrder([$this->w->product->id => 1]);

    $this->get(route('shop.orders.show', ['order' => $order->reference]))->assertNotFound();
    $this->get(route('shop.orders.show', ['order' => $order->reference, 'token' => 'wrong']))->assertNotFound();
    $this->get($order->statusUrl())->assertOk()->assertSee($order->reference)->assertSee('GHS 45.00');
});

test('online sales keep rule B6 balanced', function () {
    $order = placeOnlineOrder([$this->w->product->id => 4]);
    shop()->confirmPayment(shop()->startPayment($order, 'sandbox'), 'P1', $order->total_pesewas);

    expect(Artisan::call('stock:check'))->toBe(0);
});
