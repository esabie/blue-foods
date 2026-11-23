@extends('site.layout')

@section('title', 'Checkout')
@section('robots', 'noindex')

@section('content')
    <div class="bg-[var(--bf-sand)]">
        <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-12">
            <div class="fade-up flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.18em] text-sky-800/70">Blue Foods</p>
                    <h1 class="mt-1 font-display text-3xl font-semibold tracking-tight text-sky-950 sm:text-4xl">Checkout</h1>
                    <p class="mt-2 text-sm text-gray-500">Confirm your details and how you’d like to receive your order.</p>
                </div>
                <a href="{{ route('cart.show') }}" class="text-sm font-medium text-sky-800 hover:underline">Back to cart</a>
            </div>

            <form method="POST" action="{{ route('checkout.store') }}" class="fade-up fade-up-delay-1 mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]" id="checkout">
                @csrf
                <div class="space-y-6">
                    <fieldset class="overflow-hidden rounded-2xl border border-sky-950/10 bg-white shadow-sm shadow-sky-950/5">
                        <legend class="sr-only">Your details</legend>
                        <h2 class="border-b border-sky-950/5 px-5 py-4 font-display text-lg font-semibold text-sky-950">Your details</h2>
                        <div class="space-y-4 px-5 py-5">
                            <div>
                                <label for="customer_name" class="block text-sm font-medium text-sky-950">Full name</label>
                                <input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" class="input mt-1.5" required autocomplete="name">
                            </div>
                            <div>
                                <label for="phone" class="block text-sm font-medium text-sky-950">Phone number</label>
                                <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" class="input mt-1.5" required autocomplete="tel" placeholder="024 123 4567">
                                <p class="mt-1.5 text-xs text-gray-500">We’ll text your order number here. You can track status anytime with that number and this phone.</p>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="overflow-hidden rounded-2xl border border-sky-950/10 bg-white shadow-sm shadow-sky-950/5">
                        <legend class="sr-only">Delivery or collection</legend>
                        <h2 class="border-b border-sky-950/5 px-5 py-4 font-display text-lg font-semibold text-sky-950">Delivery or collection</h2>
                        <div class="space-y-3 px-5 py-5">
                            @php($delivery = old('delivery_method', 'delivery'))
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-sky-950/10 p-4 transition has-[:checked]:border-sky-800 has-[:checked]:bg-sky-50/80">
                                <input type="radio" name="delivery_method" value="delivery" class="mt-1 text-sky-800 focus:ring-sky-600" @checked($delivery === 'delivery')>
                                <span>
                                    <span class="block font-medium text-sky-950">Delivery <span class="text-sky-800">&middot; {{ \App\Support\Money::format($deliveryFee) }}</span></span>
                                    <span class="mt-0.5 block text-sm text-gray-500">{{ $site['delivery_areas'] }}</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-sky-950/10 p-4 transition has-[:checked]:border-sky-800 has-[:checked]:bg-sky-50/80">
                                <input type="radio" name="delivery_method" value="collection" class="mt-1 text-sky-800 focus:ring-sky-600" @checked($delivery === 'collection')>
                                <span>
                                    <span class="block font-medium text-sky-950">Collection <span class="text-sky-800">&middot; free</span></span>
                                    <span class="mt-0.5 block text-sm text-gray-500">{{ $site['collection_point'] }}</span>
                                </span>
                            </label>
                            <div id="address-field" class="pt-1">
                                <label for="address" class="block text-sm font-medium text-sky-950">Delivery address</label>
                                <textarea id="address" name="address" rows="3" class="input mt-1.5" autocomplete="street-address" placeholder="House number, street, area and a landmark">{{ old('address') }}</textarea>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="overflow-hidden rounded-2xl border border-sky-950/10 bg-white shadow-sm shadow-sky-950/5">
                        <legend class="sr-only">Payment</legend>
                        <h2 class="border-b border-sky-950/5 px-5 py-4 font-display text-lg font-semibold text-sky-950">Payment</h2>
                        <div class="space-y-3 px-5 py-5">
                            @foreach ($paymentMethods as $i => $method)
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-sky-950/10 p-4 transition has-[:checked]:border-sky-800 has-[:checked]:bg-sky-50/80">
                                    <input type="radio" name="payment_method" value="{{ $method->value }}" class="text-sky-800 focus:ring-sky-600" @checked(old('payment_method', $paymentMethods[0]->value) === $method->value)>
                                    <span class="font-medium text-sky-950">{{ $method->label() }}</span>
                                </label>
                            @endforeach
                            <p class="text-xs leading-relaxed text-gray-500">Mobile money payments are handled securely by our payment provider. Your items are held for {{ config('shop.hold_minutes') }} minutes while you pay.</p>
                        </div>
                    </fieldset>
                </div>

                <aside class="h-fit lg:sticky lg:top-24">
                    <div class="rounded-2xl bg-sky-950 p-5 text-white shadow-lg shadow-sky-950/20 sm:p-6">
                        <h2 class="font-display text-lg font-semibold">Order summary</h2>
                        <ul class="mt-4 space-y-3 border-b border-white/10 pb-4 text-sm">
                            @foreach ($lines as $line)
                                <li class="flex gap-3">
                                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-sky-900 ring-1 ring-white/10">
                                        @include('site.partials.product-photo', ['product' => $line['product'], 'class' => 'h-12 w-12 object-cover'])
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-medium text-sky-50">{{ $line['product']->name }}</p>
                                        <p class="text-xs text-sky-200/80">{{ $line['quantity'] }} &times; {{ \App\Support\Money::format($line['unit_price_pesewas']) }}</p>
                                    </div>
                                    <p class="shrink-0 tabular-nums text-sky-50">{{ \App\Support\Money::format($line['total_pesewas']) }}</p>
                                </li>
                            @endforeach
                        </ul>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-sky-200">Subtotal</dt>
                                <dd class="tabular-nums">{{ \App\Support\Money::format($subtotal) }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-sky-200">Delivery</dt>
                                <dd id="fee" class="tabular-nums">{{ \App\Support\Money::format($deliveryFee) }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-t border-white/10 pt-3 text-base">
                                <dt class="font-medium">Total</dt>
                                <dd id="total" class="font-semibold tabular-nums">{{ \App\Support\Money::format($subtotal + $deliveryFee) }}</dd>
                            </div>
                        </dl>
                        <button type="submit" class="mt-5 flex w-full items-center justify-center rounded-md bg-amber-400 px-5 py-3 text-sm font-semibold text-sky-950 transition hover:bg-amber-300 disabled:cursor-not-allowed disabled:opacity-60">Place order</button>
                        <a href="{{ route('cart.show') }}" class="mt-3 flex w-full items-center justify-center rounded-md px-5 py-2.5 text-sm font-medium text-sky-100 ring-1 ring-white/20 transition hover:bg-white/5">Edit cart</a>
                    </div>
                </aside>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('checkout');
            const fee = @json(\App\Support\Money::format($deliveryFee));
            const free = @json(\App\Support\Money::format(0));
            const withFee = @json(\App\Support\Money::format($subtotal + $deliveryFee));
            const withoutFee = @json(\App\Support\Money::format($subtotal));
            function sync() {
                const delivery = form.querySelector('input[name=delivery_method]:checked')?.value === 'delivery';
                document.getElementById('address-field').hidden = !delivery;
                document.getElementById('address').required = delivery;
                document.getElementById('fee').textContent = delivery ? fee : free;
                document.getElementById('total').textContent = delivery ? withFee : withoutFee;
            }
            form.addEventListener('change', sync);
            form.addEventListener('submit', () => form.querySelector('button[type=submit]').disabled = true);
            sync();
        })();
    </script>
@endsection
