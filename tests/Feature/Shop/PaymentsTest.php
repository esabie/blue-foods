<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SendSms;
use App\Models\OnlineOrder;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->w = stockWorld(2500);
    ledger()->produce($this->w->product, 50, $this->w->warehouse);
    ledger()->allocateOnline($this->w->product, 10, $this->w->manager);
});

function usePaystack(): void
{
    config(['services.payments.driver' => 'paystack', 'services.paystack.secret_key' => 'sk_test_secret', 'services.paystack.base_url' => 'https://api.paystack.co']);
}

function paystackWebhook(array $payload, ?string $secret = 'sk_test_secret')
{
    $body = json_encode($payload);

    return test()->call('POST', route('shop.payments.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => $secret === null ? '' : hash_hmac('sha512', $body, $secret),
    ], $body);
}

function chargeSuccess(Payment $payment, ?int $amount = null): array
{
    return ['event' => 'charge.success', 'data' => [
        'id' => 987654, 'status' => 'success', 'reference' => $payment->reference,
        'amount' => $amount ?? $payment->amount_pesewas, 'currency' => 'GHS',
    ]];
}

test('a customer can buy online end to end with the sandbox provider', function () {
    $this->post(route('cart.add'), ['product_id' => $this->w->product->id, 'quantity' => 2])->assertRedirect(route('cart.show'));
    $this->get(route('checkout.show'))->assertOk()->assertSee('GHS 50.00');

    $response = $this->post(route('checkout.store'), [
        'customer_name' => 'Efua Mensah',
        'phone' => '024 123 4567',
        'delivery_method' => 'collection',
        'payment_method' => 'mobile_money',
    ]);

    $payment = Payment::sole();
    $response->assertRedirect(route('shop.sandbox.show', $payment->reference));
    expect($payment->order->phone)->toBe('+233241234567')
        ->and($payment->amount_pesewas)->toBe(5000)
        ->and(session('cart'))->toBeNull();

    $this->get(route('shop.sandbox.show', $payment->reference))->assertOk()->assertSee('GHS 50.00');
    $this->post(route('shop.sandbox.complete', $payment->reference), ['outcome' => 'success'])
        ->assertRedirect(route('shop.payments.return', $payment->reference));
    $this->get(route('shop.payments.return', $payment->reference))->assertRedirect($payment->order->statusUrl());

    expect($payment->order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(soldOnline())->toBe(2);
    Queue::assertPushed(SendSms::class);
});

test('a declined payment leaves the order unpaid and the customer can retry while stock is held', function () {
    $order = placeOnlineOrder([$this->w->product->id => 1]);
    $payment = shop()->startPayment($order, 'sandbox');

    $this->post(route('shop.sandbox.complete', $payment->reference), ['outcome' => 'fail']);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($order->fresh()->status)->toBe(OrderStatus::PendingPayment);

    $this->post(route('shop.orders.pay', $order->reference), ['token' => $order->access_token])
        ->assertRedirect();
    expect($order->payments()->count())->toBe(2);

    $this->post(route('shop.orders.pay', $order->reference), ['token' => 'wrong'])->assertNotFound();
});

test('the sandbox provider is unavailable when a real provider is configured', function () {
    usePaystack();
    $order = placeOnlineOrder([$this->w->product->id => 1]);
    $payment = shop()->startPayment($order, 'paystack');

    $this->get(route('shop.sandbox.show', $payment->reference))->assertNotFound();
    $this->post(route('shop.sandbox.complete', $payment->reference), ['outcome' => 'success'])->assertNotFound();
    expect($order->fresh()->isPaid())->toBeFalse();
});

test('W5: a signed Paystack charge.success webhook marks the order paid', function () {
    usePaystack();
    $order = placeOnlineOrder([$this->w->product->id => 3]);
    $payment = shop()->startPayment($order, 'paystack');

    paystackWebhook(chargeSuccess($payment))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($payment->fresh()->provider_ref)->toBe('987654')
        ->and(soldOnline())->toBe(3);

    // Providers retry webhooks; the second delivery changes nothing.
    paystackWebhook(chargeSuccess($payment))->assertOk();
    expect(soldOnline())->toBe(3);
});

test('W5: webhooks with a bad or missing signature are rejected', function () {
    usePaystack();
    $order = placeOnlineOrder([$this->w->product->id => 1]);
    $payment = shop()->startPayment($order, 'paystack');

    paystackWebhook(chargeSuccess($payment), 'forged-secret')->assertUnauthorized();
    paystackWebhook(chargeSuccess($payment), null)->assertUnauthorized();

    expect($order->fresh()->isPaid())->toBeFalse();
});

test('W5: webhooks are rejected when no Paystack secret is configured', function () {
    usePaystack();
    config(['services.paystack.secret_key' => '']);
    $order = placeOnlineOrder([$this->w->product->id => 1]);
    $payment = shop()->startPayment($order, 'paystack');

    paystackWebhook(chargeSuccess($payment), '')->assertUnauthorized();
    expect($order->fresh()->isPaid())->toBeFalse();
});

test('W5: a webhook for the wrong amount does not mark the order paid', function () {
    usePaystack();
    $order = placeOnlineOrder([$this->w->product->id => 1]);
    $payment = shop()->startPayment($order, 'paystack');

    paystackWebhook(chargeSuccess($payment, 100))->assertOk();

    expect($order->fresh()->isPaid())->toBeFalse()->and($payment->fresh()->status)->toBe(PaymentStatus::Failed);
});

test('W5: returning from Paystack proves nothing until the provider verifies the payment', function () {
    usePaystack();
    $order = placeOnlineOrder([$this->w->product->id => 1]);
    $payment = shop()->startPayment($order, 'paystack');

    Http::fake(['api.paystack.co/transaction/verify/*' => Http::sequence()
        ->push(['status' => true, 'data' => ['status' => 'abandoned', 'currency' => 'GHS']])
        ->push(['status' => true, 'data' => ['id' => 55, 'status' => 'success', 'currency' => 'GHS', 'amount' => $payment->amount_pesewas]]),
    ]);

    $this->get(route('shop.payments.return', $payment->reference))->assertRedirect($order->statusUrl());
    expect($order->fresh()->isPaid())->toBeFalse();

    $this->get(route('shop.payments.return', $payment->reference));
    expect($order->fresh()->isPaid())->toBeTrue();
});

test('Paystack checkout is started with the order amount in pesewas', function () {
    usePaystack();
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc']])]);

    $this->post(route('cart.add'), ['product_id' => $this->w->product->id, 'quantity' => 2]);
    $this->post(route('checkout.store'), [
        'customer_name' => 'Yaw', 'phone' => '0201234567', 'delivery_method' => 'delivery',
        'address' => 'East Legon', 'payment_method' => 'card',
    ])->assertRedirect('https://checkout.paystack.com/abc');

    Http::assertSent(fn ($request) => $request['amount'] === 5000 + 2000
        && $request['currency'] === 'GHS'
        && $request['channels'] === ['card']
        && $request['reference'] === Payment::sole()->reference);
});

test('pay on delivery skips the provider and sends an SMS', function () {
    $this->post(route('cart.add'), ['product_id' => $this->w->product->id, 'quantity' => 1]);
    $this->post(route('checkout.store'), [
        'customer_name' => 'Ama', 'phone' => '0551234567', 'delivery_method' => 'collection', 'payment_method' => 'pay_on_delivery',
    ])->assertRedirect(OnlineOrder::sole()->statusUrl());

    expect(Payment::count())->toBe(0);
    Queue::assertPushed(SendSms::class, fn ($job) => $job->to === '+233551234567');
});

test('payment methods not offered (Q4) are refused at checkout', function () {
    config(['shop.payment_methods' => ['mobile_money']]);
    $this->post(route('cart.add'), ['product_id' => $this->w->product->id, 'quantity' => 1]);

    $this->post(route('checkout.store'), [
        'customer_name' => 'Ama', 'phone' => '0551234567', 'delivery_method' => 'collection', 'payment_method' => 'card',
    ])->assertSessionHasErrors('payment_method');
});
