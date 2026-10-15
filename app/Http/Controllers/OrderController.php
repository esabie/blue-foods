<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\OnlineOrder;
use App\Models\OrderStatusChange;
use App\Services\Shop\OnlineShop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * W8: the warehouse order queue. Management can also cancel unpaid orders.
 */
class OrderController extends Controller
{
    public const TABS = [
        'to_pack' => 'To pack',
        'packed' => 'Packed',
        'awaiting_payment' => 'Awaiting online payment',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public function __construct(private readonly OnlineShop $shop) {}

    public function index(Request $request): Response
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'to_pack';

        $query = fn () => OnlineOrder::query();
        $scopes = [
            'to_pack' => fn ($q) => $q->where(fn ($q) => $q->where('status', OrderStatus::Paid)
                ->orWhere(fn ($q) => $q->where('status', OrderStatus::PendingPayment)->where('payment_method', PaymentMethod::PayOnDelivery))),
            'packed' => fn ($q) => $q->where('status', OrderStatus::Packed),
            'awaiting_payment' => fn ($q) => $q->where('status', OrderStatus::PendingPayment)->where('payment_method', '!=', PaymentMethod::PayOnDelivery),
            'completed' => fn ($q) => $q->whereIn('status', [OrderStatus::Delivered, OrderStatus::Collected]),
            'cancelled' => fn ($q) => $q->where('status', OrderStatus::Cancelled),
        ];

        $orders = $scopes[$tab]($query())
            ->with(['items.product:id,name,unit', 'statusChanges.user:id,name', 'payments'])
            ->orderBy($tab === 'completed' || $tab === 'cancelled' ? 'updated_at' : 'created_at', $tab === 'completed' || $tab === 'cancelled' ? 'desc' : 'asc')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (OnlineOrder $o) => [
                'id' => $o->id,
                'reference' => $o->reference,
                'customer_name' => $o->customer_name,
                'phone' => $o->phone,
                'address' => $o->address,
                'delivery_method' => $o->delivery_method->value,
                'delivery_label' => $o->delivery_method->label(),
                'payment_method' => $o->payment_method->label(),
                'pay_on_delivery' => $o->payment_method === PaymentMethod::PayOnDelivery,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'paid' => $o->isPaid(),
                'needs_refund' => $o->needs_refund,
                'subtotal_pesewas' => $o->subtotal_pesewas,
                'delivery_fee_pesewas' => $o->delivery_fee_pesewas,
                'total_pesewas' => $o->total_pesewas,
                'reserved_until' => $o->reserved_until?->toIso8601String(),
                'created_at' => $o->created_at->toIso8601String(),
                'items' => $o->items->map(fn ($i) => [
                    'product' => $i->product->name,
                    'unit' => $i->product->unit,
                    'quantity' => $i->quantity,
                    'total_pesewas' => $i->total_pesewas,
                ]),
                'history' => $o->statusChanges->map(fn (OrderStatusChange $c) => [
                    'to' => $c->to_status->label(),
                    'by' => $c->user?->name ?? 'Customer / system',
                    'note' => $c->note,
                    'at' => $c->created_at->toIso8601String(),
                ]),
            ]);

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'tab' => $tab,
            'tabs' => self::TABS,
            'counts' => collect($scopes)->map(fn ($scope) => $scope($query())->count()),
            'canCancel' => $request->user()->hasRole(Role::Management),
        ]);
    }

    public function pack(Request $request, OnlineOrder $order): RedirectResponse
    {
        $this->shop->pack($order, $request->user());

        return back()->with('success', "Order {$order->reference} packed.");
    }

    public function complete(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['cash_received' => ['nullable', 'boolean']]);

        $this->shop->complete($order, $request->user(), (bool) ($data['cash_received'] ?? false));

        return back()->with('success', "Order {$order->reference} marked ".strtolower($order->fresh()->status->label()).'.');
    }

    public function cancel(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        $this->shop->cancel($order, $request->user(), $data['note'] ?? null);

        return back()->with('success', "Order {$order->reference} cancelled; its stock is available again.");
    }
}
