@extends('site.layout')

@section('title', 'Track your order')
@section('description', 'Check the status of your Blue Foods order with your order number and phone.')

@section('content')
    <div class="bg-[var(--bf-sand)]">
        <div class="mx-auto max-w-lg px-4 py-10 sm:px-6 sm:py-14">
            <div class="fade-up">
                <p class="text-sm font-medium uppercase tracking-[0.18em] text-sky-800/70">Orders</p>
                <h1 class="mt-1 font-display text-3xl font-semibold tracking-tight text-sky-950 sm:text-4xl">Track your order</h1>
                <p class="mt-2 text-sm leading-relaxed text-gray-500">Enter the order number from your SMS and the phone you used at checkout.</p>
            </div>

            <form method="POST" action="{{ route('shop.track.lookup') }}" class="fade-up fade-up-delay-1 mt-8 space-y-5 rounded-2xl border border-sky-950/10 bg-white p-5 shadow-sm shadow-sky-950/5 sm:p-6">
                @csrf
                <div>
                    <label for="reference" class="block text-sm font-medium text-sky-950">Order number</label>
                    <input id="reference" name="reference" value="{{ old('reference') }}" class="input mt-1.5 font-mono uppercase" required autocomplete="off" placeholder="BF261018ABCD" autocapitalize="characters">
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-sky-950">Phone number</label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" class="input mt-1.5" required autocomplete="tel" placeholder="024 123 4567">
                </div>
                <button type="submit" class="btn w-full sm:w-auto">View order status</button>
            </form>

            <p class="fade-up fade-up-delay-2 mt-8 text-sm text-gray-500">Still need help? Call <a href="tel:{{ preg_replace('/[^\d+]/', '', $site['contact_phone']) }}" class="font-medium text-sky-800 hover:underline">{{ $site['contact_phone'] }}</a>.</p>
        </div>
    </div>
@endsection
