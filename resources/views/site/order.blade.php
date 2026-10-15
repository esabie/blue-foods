@extends('site.layout')

@section('title', "Order {$order->reference}")
@section('robots', 'noindex')

@php
    use App\Enums\OrderStatus;
    use App\Support\Money;
    $tz = config('inventory.display_timezone');
    $awaitingOnline = $order->status === OrderStatus::PendingPayment && $order->payment_method->isOnline();
    $canPay = $awaitingOnline && $order->reserved_until?->isFuture();
@endphp

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <p class="text-sm text-gray-500">Order</p>
        <h1 class="text-3xl font-bold text-gray-900">{{ $order->reference }}</h1>

        <div @class([
            'mt-6 rounded-lg p-5 ring-1',
            'bg-emerald-50 ring-emerald-200 text-emerald-900' => $order->isPaid() || in_array($order->status, [OrderStatus::Packed, OrderStatus::Delivered, OrderStatus::Collected]),
            'bg-amber-50 ring-amber-200 text-amber-900' => $order->status === OrderStatus::PendingPayment,
            'bg-gray-50 ring-gray-200 text-gray-800' => $order->status === OrderStatus::Cancelled,
        ])>
            <p class="text-lg font-semibold">{{ $order->status->label() }}</p>
            @if ($canPay)
                <p class="mt-1 text-sm">Your items are held until {{ $order->reserved_until->timezone($tz)->format('H:i') }}. Complete payment before then to keep them.</p>
                <form method="POST" action="{{ route('shop.orders.pay', $order->reference) }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="token" value="{{ request('token') }}">
                    <button type="submit" class="btn">Pay {{ Money::format($order->total_pesewas) }} now</button>
                </form>
            @elseif ($awaitingOnline)
                <p class="mt-1 text-sm">The time to pay has passed and the items were released. If you were charged, we will refund you; please contact us.</p>
            @elseif ($order->status === OrderStatus::PendingPayment)
                <p class="mt-1 text-sm">Your items are reserved. Pay {{ Money::format($order->total_pesewas) }} when you {{ $order->delivery_method->value === 'delivery' ? 'receive your order' : 'collect your order' }}.</p>
            @elseif ($order->status === OrderStatus::Paid)
                <p class="mt-1 text-sm">Payment received. We are preparing your order.</p>
            @elseif ($order->status === OrderStatus::Packed)
                <p class="mt-1 text-sm">{{ $order->delivery_method->value === 'delivery' ? 'Your order is packed and on its way soon.' : 'Your order is packed and ready to collect from '.$site['collection_point'].'.' }}</p>
            @endif
            @if ($order->needs_refund)
                <p class="mt-2 text-sm font-medium">A refund is due on this order. Our team will contact you.</p>
            @endif
        </div>

        <div class="mt-8 rounded-lg border border-gray-100">
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-4 px-5 py-3">
                        <span>{{ $item->quantity }} &times; {{ $item->product->name }} <span class="whitespace-nowrap text-gray-500">@ {{ Money::format($item->unit_price_pesewas) }}</span></span>
                        <span class="whitespace-nowrap">{{ Money::format($item->total_pesewas) }}</span>
                    </li>
                @endforeach
            </ul>
            <dl class="space-y-1 border-t border-gray-100 px-5 py-4 text-sm">
                <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ Money::format($order->subtotal_pesewas) }}</dd></div>
                <div class="flex justify-between"><dt>{{ $order->delivery_method->label() }}</dt><dd>{{ Money::format($order->delivery_fee_pesewas) }}</dd></div>
                <div class="flex justify-between text-base font-semibold"><dt>Total</dt><dd>{{ Money::format($order->total_pesewas) }}</dd></div>
            </dl>
        </div>

        <dl class="mt-8 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-500">Name</dt><dd>{{ $order->customer_name }}</dd></div>
            <div><dt class="text-gray-500">Phone</dt><dd>{{ $order->phone }}</dd></div>
            <div><dt class="text-gray-500">Payment</dt><dd>{{ $order->payment_method->label() }}</dd></div>
            <div><dt class="text-gray-500">{{ $order->delivery_method->label() }}</dt><dd class="whitespace-pre-line">{{ $order->address ?? $site['collection_point'] }}</dd></div>
        </dl>

        <h2 class="mt-10 font-semibold text-gray-900">History</h2>
        <ol class="mt-3 space-y-2 border-l-2 border-sky-100 pl-4 text-sm">
            @foreach ($order->statusChanges as $change)
                <li>
                    <span class="font-medium">{{ $change->to_status->label() }}</span>
                    <span class="text-gray-500">&middot; {{ $change->created_at->timezone($tz)->format('j M Y, H:i') }}</span>
                </li>
            @endforeach
        </ol>

        <p class="mt-10 text-sm text-gray-500">Keep this page's link to check your order. Questions? Call {{ $site['contact_phone'] }}.</p>
    </div>
@endsection
