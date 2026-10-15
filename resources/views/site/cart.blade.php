@extends('site.layout')

@section('title', 'Your cart')
@section('robots', 'noindex')

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-bold text-gray-900">Your cart</h1>

        @if ($lines->isEmpty())
            <p class="mt-6 text-gray-600">Your cart is empty.</p>
            <a href="{{ route('shop.index') }}" class="btn mt-6">Go to the shop</a>
        @else
            <ul class="mt-8 divide-y divide-gray-100 border-y border-gray-100">
                @foreach ($lines as $line)
                    @php($product = $line['product'])
                    <li class="flex gap-4 py-4">
                        @include('site.partials.product-photo', ['product' => $product, 'class' => 'h-16 w-16 shrink-0 rounded-md sm:h-20 sm:w-20'])
                        <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <a href="{{ route('shop.show', $product->sku) }}" class="font-semibold text-gray-900 hover:text-sky-800">{{ $product->name }}</a>
                                <p class="text-sm text-gray-500">{{ \App\Support\Money::format($line['unit_price_pesewas']) }} per {{ $product->unit }}</p>
                                @if ($line['quantity'] > $line['available'])
                                    <p class="mt-1 text-sm font-medium text-red-700">Only {{ $line['available'] }} available now. Please reduce the quantity.</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                                <form method="POST" action="{{ route('cart.update', $product) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <label for="qty-{{ $product->id }}" class="sr-only">Quantity</label>
                                    <input id="qty-{{ $product->id }}" name="quantity" type="number" min="0" max="{{ max($line['available'], $line['quantity']) }}" value="{{ $line['quantity'] }}" class="input w-20">
                                    <button type="submit" class="text-sm font-medium text-sky-800 hover:underline">Update</button>
                                </form>
                                <form method="POST" action="{{ route('cart.update', $product) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="quantity" value="0">
                                    <button type="submit" class="text-sm text-gray-500 hover:text-red-700">Remove</button>
                                </form>
                                <p class="ml-auto font-semibold sm:w-28 sm:text-right">{{ \App\Support\Money::format($line['total_pesewas']) }}</p>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6 flex flex-col items-end gap-4">
                <p class="text-lg">Subtotal <span class="ml-4 font-semibold">{{ \App\Support\Money::format($subtotal) }}</span></p>
                <p class="text-sm text-gray-500">Delivery fee, if any, is added at checkout.</p>
                <div class="flex gap-3">
                    <a href="{{ route('shop.index') }}" class="btn-secondary">Continue shopping</a>
                    <a href="{{ route('checkout.show') }}" class="btn">Checkout</a>
                </div>
            </div>
        @endif
    </div>
@endsection
