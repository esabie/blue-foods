@extends('site.layout')

@section('title', 'Checkout')
@section('robots', 'noindex')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-bold text-gray-900">Checkout</h1>

        <form method="POST" action="{{ route('checkout.store') }}" class="mt-8 grid gap-10 md:grid-cols-5" id="checkout">
            @csrf
            <div class="space-y-6 md:col-span-3">
                <fieldset class="space-y-4">
                    <legend class="text-lg font-semibold text-gray-900">Your details</legend>
                    <div>
                        <label for="customer_name" class="block text-sm font-medium text-gray-700">Full name</label>
                        <input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" class="input mt-1" required autocomplete="name">
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">Phone number</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" class="input mt-1" required autocomplete="tel" placeholder="024 123 4567">
                        <p class="mt-1 text-xs text-gray-500">We send your order confirmation to this number by SMS.</p>
                    </div>
                </fieldset>

                <fieldset class="space-y-3">
                    <legend class="text-lg font-semibold text-gray-900">Delivery or collection</legend>
                    @php($delivery = old('delivery_method', 'delivery'))
                    <label class="flex cursor-pointer items-start gap-3 rounded-md border border-gray-200 p-4 has-[:checked]:border-sky-600 has-[:checked]:bg-sky-50">
                        <input type="radio" name="delivery_method" value="delivery" class="mt-1 text-sky-700" @checked($delivery === 'delivery')>
                        <span>
                            <span class="block font-medium">Delivery &middot; {{ \App\Support\Money::format($deliveryFee) }}</span>
                            <span class="block text-sm text-gray-500">{{ $site['delivery_areas'] }}</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-md border border-gray-200 p-4 has-[:checked]:border-sky-600 has-[:checked]:bg-sky-50">
                        <input type="radio" name="delivery_method" value="collection" class="mt-1 text-sky-700" @checked($delivery === 'collection')>
                        <span>
                            <span class="block font-medium">Collection &middot; free</span>
                            <span class="block text-sm text-gray-500">{{ $site['collection_point'] }}</span>
                        </span>
                    </label>
                    <div id="address-field">
                        <label for="address" class="block text-sm font-medium text-gray-700">Delivery address</label>
                        <textarea id="address" name="address" rows="3" class="input mt-1" autocomplete="street-address" placeholder="House number, street, area and a landmark">{{ old('address') }}</textarea>
                    </div>
                </fieldset>

                <fieldset class="space-y-3">
                    <legend class="text-lg font-semibold text-gray-900">Payment</legend>
                    @foreach ($paymentMethods as $i => $method)
                        <label class="flex cursor-pointer items-center gap-3 rounded-md border border-gray-200 p-4 has-[:checked]:border-sky-600 has-[:checked]:bg-sky-50">
                            <input type="radio" name="payment_method" value="{{ $method->value }}" class="text-sky-700" @checked(old('payment_method', $paymentMethods[0]->value) === $method->value)>
                            <span class="font-medium">{{ $method->label() }}</span>
                        </label>
                    @endforeach
                    <p class="text-xs text-gray-500">Online payments are handled securely by our payment provider; Blue Foods never sees or stores card details. Your items are held for {{ config('shop.hold_minutes') }} minutes while you pay.</p>
                </fieldset>
            </div>

            <aside class="md:col-span-2">
                <div class="rounded-lg bg-gray-50 p-6">
                    <h2 class="text-lg font-semibold text-gray-900">Order summary</h2>
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($lines as $line)
                            <li class="flex justify-between gap-4">
                                <span>{{ $line['quantity'] }} &times; {{ $line['product']->name }}</span>
                                <span class="whitespace-nowrap">{{ \App\Support\Money::format($line['total_pesewas']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <dl class="mt-4 space-y-2 border-t border-gray-200 pt-4 text-sm">
                        <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ \App\Support\Money::format($subtotal) }}</dd></div>
                        <div class="flex justify-between"><dt>Delivery</dt><dd id="fee">{{ \App\Support\Money::format($deliveryFee) }}</dd></div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-semibold"><dt>Total</dt><dd id="total">{{ \App\Support\Money::format($subtotal + $deliveryFee) }}</dd></div>
                    </dl>
                    <button type="submit" class="btn mt-6 w-full">Place order</button>
                </div>
            </aside>
        </form>
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
