{{-- Right-side cart slider: opens from the header cart button (and after add-to-cart). --}}
<div
    id="cart-drawer"
    class="cart-drawer"
    data-open="false"
    aria-hidden="true"
    role="dialog"
    aria-modal="true"
    aria-labelledby="cart-drawer-title"
>
    <div class="cart-drawer__backdrop" data-cart-close tabindex="-1" aria-label="Close cart"></div>

    <aside class="cart-drawer__panel">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <h2 id="cart-drawer-title" class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-sky-950">
                <svg class="h-5 w-5 text-sky-800" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/></svg>
                Your cart ({{ $cartCount }})
            </h2>
            <button type="button" data-cart-close class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-800" aria-label="Close cart">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-4">
            @if ($cartLines->isEmpty())
                <p class="py-10 text-center text-sm text-gray-500">Your cart is empty.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($cartLines as $line)
                        @php($product = $line['product'])
                        <li class="flex gap-3 py-4 first:pt-0 last:pb-0">
                            <a href="{{ route('shop.show', $product->sku) }}" class="shrink-0 overflow-hidden rounded-lg bg-sky-50 ring-1 ring-sky-950/5">
                                @include('site.partials.product-photo', ['product' => $product, 'class' => 'h-16 w-16 object-cover'])
                            </a>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('shop.show', $product->sku) }}" class="font-medium text-sky-950 hover:text-sky-800">{{ $product->name }}</a>
                                <p class="mt-0.5 text-xs text-gray-500">{{ \App\Support\Money::format($line['unit_price_pesewas']) }} / {{ $product->unit }}</p>
                                @if ($line['quantity'] > $line['available'])
                                    <p class="mt-1 text-xs font-medium text-red-700">Only {{ $line['available'] }} available.</p>
                                @endif
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('cart.update', $product) }}" class="flex items-center gap-1.5">
                                        @csrf
                                        @method('PATCH')
                                        <label for="drawer-qty-{{ $product->id }}" class="sr-only">Quantity</label>
                                        <input id="drawer-qty-{{ $product->id }}" name="quantity" type="number" min="0" max="{{ max($line['available'], $line['quantity']) }}" value="{{ $line['quantity'] }}" class="input w-14 py-1 text-center text-sm">
                                        <button type="submit" class="text-xs font-medium text-sky-800 hover:underline">Update</button>
                                    </form>
                                    <form method="POST" action="{{ route('cart.update', $product) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="quantity" value="0">
                                        <button type="submit" class="text-xs text-gray-500 hover:text-red-700">Remove</button>
                                    </form>
                                    <p class="ml-auto text-sm font-semibold tabular-nums">{{ \App\Support\Money::format($line['total_pesewas']) }}</p>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="border-t border-gray-100 px-5 py-4">
            <div class="flex items-center justify-between text-sm font-semibold uppercase tracking-wide text-sky-950">
                <span>Subtotal</span>
                <span class="tabular-nums">{{ \App\Support\Money::format($cartSubtotal) }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">Shipping and taxes calculated at checkout.</p>
            @if ($cartCount > 0)
                <a href="{{ route('checkout.show') }}" class="btn mt-4 w-full">Proceed to checkout</a>
                <form method="POST" action="{{ route('cart.clear') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="btn-secondary w-full">Clear cart</button>
                </form>
            @else
                <a href="{{ route('shop.index') }}" class="btn mt-4 w-full">Continue shopping</a>
            @endif
        </div>
    </aside>
</div>
