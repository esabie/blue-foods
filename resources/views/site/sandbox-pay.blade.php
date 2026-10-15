@extends('site.layout')

@section('title', 'Test payment')
@section('robots', 'noindex')

@section('content')
    <div class="mx-auto max-w-md px-4 py-16 sm:px-6">
        <div class="rounded-lg border-2 border-dashed border-amber-300 bg-amber-50 p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Sandbox payment provider</p>
            <h1 class="mt-2 text-xl font-bold text-gray-900">Pay {{ \App\Support\Money::format($payment->amount_pesewas) }}</h1>
            <p class="mt-1 text-sm text-gray-600">Order {{ $payment->order->reference }} &middot; {{ $payment->method->label() }}</p>
            <p class="mt-4 text-sm text-gray-600">This page stands in for the payment provider while testing. No money moves. Set <code>PAYMENT_DRIVER=paystack</code> to use the real provider.</p>

            <div class="mt-6 flex gap-3">
                <form method="POST" action="{{ route('shop.sandbox.complete', $payment->reference) }}">
                    @csrf
                    <input type="hidden" name="outcome" value="success">
                    <button type="submit" class="btn">Approve payment</button>
                </form>
                <form method="POST" action="{{ route('shop.sandbox.complete', $payment->reference) }}">
                    @csrf
                    <input type="hidden" name="outcome" value="fail">
                    <button type="submit" class="btn-secondary">Decline</button>
                </form>
            </div>
        </div>
    </div>
@endsection
