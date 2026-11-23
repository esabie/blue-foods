@extends('site.layout')

@section('title', "Order {$order->reference}")
@section('robots', 'noindex, nofollow')
@section('referrer', 'no-referrer')

@php
    use App\Enums\OrderStatus;
    use App\Support\Money;
    $tz = config('inventory.display_timezone');
    $awaitingOnline = $order->status === OrderStatus::PendingPayment && $order->payment_method->isOnline();
    $canPay = $awaitingOnline && $order->reserved_until?->isFuture();
@endphp

@section('content')
    <div class="bg-[var(--bf-sand)]">
        <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-12">
            <div class="fade-up">
                <p class="text-sm font-medium uppercase tracking-[0.18em] text-sky-800/70">Order</p>
                <h1 class="mt-1 font-display text-3xl font-semibold tracking-tight text-sky-950 sm:text-4xl">{{ $order->reference }}</h1>
            </div>

            <div @class([
                'fade-up fade-up-delay-1 mt-8 rounded-2xl border p-5 sm:p-6',
                'border-emerald-200 bg-emerald-50 text-emerald-950' => $order->isPaid() || in_array($order->status, [OrderStatus::Packed, OrderStatus::Delivered, OrderStatus::Collected]),
                'border-amber-200 bg-amber-50 text-amber-950' => $order->status === OrderStatus::PendingPayment,
                'border-gray-200 bg-white text-gray-800' => $order->status === OrderStatus::Cancelled,
            ])>
                <p class="font-display text-xl font-semibold">{{ $order->status->label() }}</p>
                @if ($canPay)
                    <p class="mt-2 text-sm leading-relaxed opacity-90">Your items are held until {{ $order->reserved_until->timezone($tz)->format('H:i') }}. Complete payment before then to keep them.</p>
                    <form method="POST" action="{{ route('shop.orders.pay', $order->reference) }}" class="mt-5">
                        @csrf
                        <button type="submit" class="btn">Pay {{ Money::format($order->total_pesewas) }} now</button>
                    </form>
                @elseif ($awaitingOnline)
                    <p class="mt-2 text-sm leading-relaxed opacity-90">The time to pay has passed and the items were released. If you were charged, we will refund you; please contact us.</p>
                @elseif ($order->status === OrderStatus::PendingPayment)
                    <p class="mt-2 text-sm leading-relaxed opacity-90">Your items are reserved. Pay {{ Money::format($order->total_pesewas) }} when you {{ $order->delivery_method->value === 'delivery' ? 'receive your order' : 'collect your order' }}.</p>
                @elseif ($order->status === OrderStatus::Paid)
                    <p class="mt-2 text-sm leading-relaxed opacity-90">Payment received. We are preparing your order.</p>
                @elseif ($order->status === OrderStatus::Packed)
                    <p class="mt-2 text-sm leading-relaxed opacity-90">{{ $order->delivery_method->value === 'delivery' ? 'Your order is packed and on its way soon.' : 'Your order is packed and ready to collect from '.$site['collection_point'].'.' }}</p>
                @endif
                @if ($order->needs_refund)
                    <p class="mt-3 text-sm font-medium">A refund is due on this order. Our team will contact you.</p>
                @endif
            </div>

            <div class="fade-up fade-up-delay-2 mt-6 overflow-hidden rounded-2xl border border-sky-950/10 bg-white shadow-sm shadow-sky-950/5">
                <ul class="divide-y divide-sky-950/5 text-sm">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between gap-4 px-5 py-3.5">
                            <span class="text-sky-950">{{ $item->quantity }} &times; {{ $item->product->name }} <span class="whitespace-nowrap text-gray-500">@ {{ Money::format($item->unit_price_pesewas) }}</span></span>
                            <span class="whitespace-nowrap font-medium tabular-nums text-sky-950">{{ Money::format($item->total_pesewas) }}</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="space-y-2 border-t border-sky-950/5 bg-[var(--bf-sand)]/60 px-5 py-4 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Subtotal</dt><dd class="tabular-nums text-sky-950">{{ Money::format($order->subtotal_pesewas) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ $order->delivery_method->label() }}</dt><dd class="tabular-nums text-sky-950">{{ Money::format($order->delivery_fee_pesewas) }}</dd></div>
                    <div class="flex justify-between gap-4 border-t border-sky-950/5 pt-2 text-base font-semibold"><dt class="text-sky-950">Total</dt><dd class="tabular-nums text-sky-950">{{ Money::format($order->total_pesewas) }}</dd></div>
                </dl>
            </div>

            <dl class="fade-up fade-up-delay-3 mt-6 grid gap-4 rounded-2xl border border-sky-950/10 bg-white p-5 text-sm shadow-sm shadow-sky-950/5 sm:grid-cols-2 sm:p-6">
                <div><dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Name</dt><dd class="mt-1 font-medium text-sky-950">{{ $order->customer_name }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Phone</dt><dd class="mt-1 font-medium text-sky-950">{{ $order->phone }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Payment</dt><dd class="mt-1 font-medium text-sky-950">{{ $order->payment_method->label() }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wider text-gray-400">{{ $order->delivery_method->label() }}</dt><dd class="mt-1 whitespace-pre-line font-medium text-sky-950">{{ $order->address ?? $site['collection_point'] }}</dd></div>
            </dl>

            <div class="mt-8">
                <h2 class="font-display text-lg font-semibold text-sky-950">History</h2>
                <ol class="mt-3 space-y-2.5 border-l-2 border-sky-200 pl-4 text-sm">
                    @foreach ($order->statusChanges as $change)
                        <li>
                            <span class="font-medium text-sky-950">{{ $change->to_status->label() }}</span>
                            <span class="text-gray-500">&middot; {{ $change->created_at->timezone($tz)->format('j M Y, H:i') }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>

            <p class="mt-10 text-sm text-gray-500">To check this order later, use <a href="{{ route('shop.track') }}" class="font-medium text-sky-800 hover:underline">Track order</a> with your order number and phone. Questions? Call <a href="tel:{{ preg_replace('/[^\d+]/', '', $site['contact_phone']) }}" class="font-medium text-sky-800 hover:underline">{{ $site['contact_phone'] }}</a>.</p>
        </div>
    </div>
@endsection
