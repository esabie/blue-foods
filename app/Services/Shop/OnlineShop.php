<?php

namespace App\Services\Shop;

use App\Enums\DeliveryMethod;
use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\StockRuleException;
use App\Jobs\SendSms;
use App\Models\Location;
use App\Models\OnlineOrder;
use App\Models\OrderItem;
use App\Models\OrderStatusChange;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StaffNotifier;
use App\Services\StockLedger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The website sells only from the online shop location (A5). Stock is held for a
 * customer while they pay (W6) and only leaves the shop as SOLD_ONLINE once the
 * payment provider confirms payment (W5), or the warehouse records cash (W9).
 *
 * Locking follows StockLedger: lock the online shop location row first, then read.
 */
class OnlineShop
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly StaffNotifier $notifier,
    ) {}

    /**
     * Units customers can still buy: online shop balance minus stock held for unpaid orders.
     *
     * @param  list<int>  $productIds
     * @return array<int, int> product id => available
     */
    public function availability(array $productIds, ?int $exceptOrderId = null): array
    {
        if ($productIds === []) {
            return [];
        }

        $online = Location::onlineShop()->id;

        $in = StockMovement::whereIn('product_id', $productIds)->where('to_location_id', $online)
            ->groupBy('product_id')->selectRaw('product_id, SUM(quantity) AS qty')->pluck('qty', 'product_id');
        $out = StockMovement::whereIn('product_id', $productIds)->where('from_location_id', $online)
            ->groupBy('product_id')->selectRaw('product_id, SUM(quantity) AS qty')->pluck('qty', 'product_id');
        $held = OrderItem::whereIn('product_id', $productIds)
            ->whereHas('order', fn ($q) => $q->holdingStock()->when($exceptOrderId, fn ($q) => $q->whereKeyNot($exceptOrderId)))
            ->groupBy('product_id')->selectRaw('product_id, SUM(quantity) AS qty')->pluck('qty', 'product_id');

        return collect($productIds)->mapWithKeys(fn ($id) => [
            $id => max(0, (int) ($in[$id] ?? 0) - (int) ($out[$id] ?? 0) - (int) ($held[$id] ?? 0)),
        ])->all();
    }

    /**
     * W4: create the order and hold its stock. Total = sum of item totals + delivery fee.
     *
     * @param  array<int, int>  $items  product id => quantity
     * @param  array{customer_name: string, phone: string, address: ?string, delivery_method: string, payment_method: string}  $customer
     */
    public function placeOrder(array $items, array $customer): OnlineOrder
    {
        $items = array_filter($items, fn ($qty) => $qty > 0);
        if ($items === []) {
            throw StockRuleException::for('cart', 'Your cart is empty.');
        }

        $delivery = DeliveryMethod::from($customer['delivery_method']);
        $method = PaymentMethod::from($customer['payment_method']);
        $online = Location::onlineShop();

        $order = DB::transaction(function () use ($items, $customer, $delivery, $method, $online) {
            Location::whereKey($online->id)->lockForUpdate()->firstOrFail();

            $products = Product::sellable()->findMany(array_keys($items))->keyBy('id');
            $available = $this->availability(array_keys($items));

            foreach ($items as $productId => $qty) {
                $product = $products[$productId] ?? throw StockRuleException::for('cart', 'A product in your cart is no longer sold.');
                if ($qty > $available[$productId]) {
                    throw StockRuleException::for('cart', "Only {$available[$productId]} {$product->unit} of {$product->name} left.");
                }
            }

            $subtotal = collect($items)->map(fn ($qty, $id) => $qty * $products[$id]->currentPrice->unit_price_pesewas)->sum();
            $fee = $delivery === DeliveryMethod::Delivery ? SiteSetting::deliveryFeePesewas() : 0;

            $order = OnlineOrder::create([
                'reference' => $this->newReference(),
                'access_token' => Str::random(40),
                'customer_name' => $customer['customer_name'],
                'phone' => $customer['phone'],
                'address' => $delivery === DeliveryMethod::Delivery ? $customer['address'] : null,
                'delivery_method' => $delivery,
                'payment_method' => $method,
                'status' => OrderStatus::PendingPayment,
                'subtotal_pesewas' => $subtotal,
                'delivery_fee_pesewas' => $fee,
                'total_pesewas' => $subtotal + $fee,
                'reserved_until' => $method->isOnline() ? now()->addMinutes(config('shop.hold_minutes')) : null,
            ]);

            foreach ($items as $productId => $qty) {
                $price = $products[$productId]->currentPrice->unit_price_pesewas;
                $order->items()->create([
                    'product_id' => $productId,
                    'quantity' => $qty,
                    'unit_price_pesewas' => $price,
                    'total_pesewas' => $qty * $price,
                ]);
            }

            $this->logStatus($order, null, OrderStatus::PendingPayment, null, 'Order placed');

            return $order;
        });

        if ($method === PaymentMethod::PayOnDelivery) {
            $this->notifier->orderToPack($order);
            $this->textCustomer($order, "Blue Foods: order {$order->reference} received. Total ".Money::format($order->total_pesewas).', to pay on '.strtolower($delivery->label()).'.');
        }

        return $order;
    }

    /**
     * Start (or restart) an online payment while the order still holds its stock.
     */
    public function startPayment(OnlineOrder $order, string $provider): Payment
    {
        if ($order->isPaid() || $order->status === OrderStatus::Cancelled || ! $order->payment_method->isOnline()) {
            throw StockRuleException::for('payment', 'This order cannot be paid online.');
        }

        if ($order->reserved_until->isPast()) {
            throw StockRuleException::for('payment', 'The 15 minutes to pay have passed. Please place the order again.');
        }

        return $order->payments()->create([
            'reference' => $order->reference.'-'.Str::upper(Str::random(6)),
            'provider' => $provider,
            'method' => $order->payment_method,
            'amount_pesewas' => $order->total_pesewas,
            'status' => PaymentStatus::Pending,
        ]);
    }

    /**
     * W5: called only with a payment the provider has confirmed. Idempotent.
     * Stock moves to SOLD_ONLINE here and nowhere else.
     */
    public function confirmPayment(Payment $payment, string $providerRef, int $amountPesewas): OnlineOrder
    {
        $online = Location::onlineShop();

        $order = DB::transaction(function () use ($payment, $providerRef, $amountPesewas, $online) {
            Location::whereKey($online->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $order = OnlineOrder::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentStatus::Paid) {
                return null;
            }

            if ($amountPesewas !== $payment->amount_pesewas) {
                $payment->update(['status' => PaymentStatus::Failed, 'provider_ref' => $providerRef, 'failure_reason' => "Amount mismatch: received {$amountPesewas}"]);
                Log::warning('Payment amount mismatch', ['payment' => $payment->reference, 'amount' => $amountPesewas]);

                return null;
            }

            $payment->update(['status' => PaymentStatus::Paid, 'provider_ref' => $providerRef, 'paid_at' => now()]);

            if ($order->isPaid()) {
                $order->update(['needs_refund' => true]);
                $this->notifier->refundNeeded($order);

                return null;
            }

            // A late payment after the hold expired is honoured only if the stock is still free.
            $available = $this->availability($order->items->pluck('product_id')->all(), exceptOrderId: $order->id);
            $short = $order->items->first(fn (OrderItem $item) => $item->quantity > ($available[$item->product_id] ?? 0));
            $stillHeld = $order->status === OrderStatus::PendingPayment && $order->reserved_until?->isFuture();

            if (! $stillHeld && $short !== null) {
                $order->update(['needs_refund' => true]);
                if ($order->status !== OrderStatus::Cancelled) {
                    $this->transition($order, OrderStatus::Cancelled, null, 'Paid after stock was released; refund needed');
                }
                $this->notifier->refundNeeded($order);

                return null;
            }

            $this->sellItems($order);
            $from = $order->status;
            $order->update(['status' => OrderStatus::Paid, 'paid_at' => now(), 'reserved_until' => null]);
            $this->logStatus($order, $from, OrderStatus::Paid, null, "Payment confirmed by {$payment->provider}");

            return $order;
        });

        if ($order !== null) {
            $this->notifier->orderToPack($order);
            $this->textCustomer($order, 'Blue Foods: payment of '.Money::format($order->total_pesewas)." received for order {$order->reference}. We will let you know when it is on its way.");
        }

        return $payment->order->fresh();
    }

    public function failPayment(Payment $payment, string $reason): void
    {
        if ($payment->status === PaymentStatus::Pending) {
            $payment->update(['status' => PaymentStatus::Failed, 'failure_reason' => $reason]);
        }
    }

    /**
     * W6: unpaid online orders release their stock after 15 minutes.
     * Availability already ignores expired holds; this makes the status say so.
     */
    public function releaseExpired(): int
    {
        $expired = OnlineOrder::where('status', OrderStatus::PendingPayment)
            ->whereNull('paid_at')
            ->where('payment_method', '!=', PaymentMethod::PayOnDelivery)
            ->where('reserved_until', '<=', now())
            ->get();

        foreach ($expired as $order) {
            $this->transition($order, OrderStatus::Cancelled, null, 'Not paid within '.config('shop.hold_minutes').' minutes; stock released');
        }

        return $expired->count();
    }

    /**
     * W8/W9: warehouse fulfilment. Every status change is logged with user and time.
     */
    public function pack(OnlineOrder $order, User $user): void
    {
        $podWaiting = $order->status === OrderStatus::PendingPayment && $order->payment_method === PaymentMethod::PayOnDelivery;

        if ($order->status !== OrderStatus::Paid && ! $podWaiting) {
            throw StockRuleException::for('order', 'Only paid or pay-on-delivery orders can be packed.');
        }

        $this->transition($order, OrderStatus::Packed, $user);
    }

    /**
     * Mark a packed order as delivered or collected. For pay on delivery, this is
     * when the cash is received and the stock is sold.
     */
    public function complete(OnlineOrder $order, User $user, bool $cashReceived = false): void
    {
        if ($order->status !== OrderStatus::Packed) {
            throw StockRuleException::for('order', 'Pack the order before marking it delivered or collected.');
        }

        $final = $order->delivery_method === DeliveryMethod::Delivery ? OrderStatus::Delivered : OrderStatus::Collected;

        if ($order->isPaid()) {
            $this->transition($order, $final, $user);

            return;
        }

        if (! $cashReceived) {
            throw StockRuleException::for('order', 'Confirm the cash was received for this pay-on-delivery order.');
        }

        DB::transaction(function () use ($order, $user, $final) {
            Location::whereKey(Location::onlineShop()->id)->lockForUpdate()->firstOrFail();
            $order = OnlineOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $order->payments()->create([
                'reference' => $order->reference.'-CASH',
                'provider' => 'cash',
                'method' => PaymentMethod::PayOnDelivery,
                'amount_pesewas' => $order->total_pesewas,
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'provider_ref' => 'received by '.$user->name,
            ]);
            $this->sellItems($order);
            $order->update(['paid_at' => now()]);
            $this->transition($order, $final, $user, 'Cash received');
        });
    }

    public function cancel(OnlineOrder $order, User $user, ?string $note = null): void
    {
        if ($order->isPaid() || ! in_array($order->status, [OrderStatus::PendingPayment, OrderStatus::Packed], true)) {
            throw StockRuleException::for('order', 'Only unpaid orders can be cancelled.');
        }

        $this->transition($order, OrderStatus::Cancelled, $user, $note);
    }

    /**
     * Move every item out of the online shop as SOLD_ONLINE at the price on the order (B2).
     */
    private function sellItems(OnlineOrder $order): void
    {
        $online = Location::onlineShop()->id;
        foreach ($order->items()->with('product')->get() as $item) {
            if ($item->quantity > $this->ledger->balance($item->product_id, $online)) {
                throw StockRuleException::for('order', "Not enough {$item->product->name} in the online shop.");
            }

            $movement = StockMovement::create([
                'type' => MovementType::SoldOnline,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'from_location_id' => $online,
                'unit_price_pesewas' => $item->unit_price_pesewas,
                'created_by' => $this->systemUserId(),
                'occurred_at' => now(),
            ]);

            $item->update(['movement_id' => $movement->id]);
        }
    }

    private function transition(OnlineOrder $order, OrderStatus $to, ?User $user, ?string $note = null): void
    {
        $from = $order->status;
        $order->update(['status' => $to]);
        $this->logStatus($order, $from, $to, $user, $note);
    }

    private function logStatus(OnlineOrder $order, ?OrderStatus $from, OrderStatus $to, ?User $user, ?string $note): void
    {
        OrderStatusChange::create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $user?->id ?? auth()->id(),
            'note' => $note,
        ]);
    }

    private function textCustomer(OnlineOrder $order, string $message): void
    {
        SendSms::dispatch($order->phone, $message)->afterCommit();
    }

    /**
     * B4: every movement records a user. Online sales are recorded by the "Online shop" system account.
     */
    private function systemUserId(): int
    {
        return User::systemAccount()->id;
    }

    private function newReference(): string
    {
        do {
            $reference = 'BF'.now()->format('ymd').Str::upper(Str::random(4));
        } while (OnlineOrder::where('reference', $reference)->exists());

        return $reference;
    }
}
