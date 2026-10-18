@php($inStock = $available > 0)
<article class="product-card">
    <a href="{{ route('shop.show', $product->sku) }}" class="product-card__media">
        @include('site.partials.product-photo', ['product' => $product, 'class' => 'h-full w-full'])
        @unless ($inStock)
            <div class="absolute inset-0 bg-white/50"></div>
        @endunless
    </a>

    <div class="flex flex-1 flex-col gap-3 p-4 sm:p-5">
        <div class="min-h-[3.25rem]">
            <h3 class="font-display text-base font-semibold leading-snug text-sky-950 sm:text-lg">
                <a href="{{ route('shop.show', $product->sku) }}" class="hover:text-sky-800">{{ $product->name }}</a>
            </h3>
            <p class="mt-1.5 font-semibold tabular-nums text-sky-950">
                {{ \App\Support\Money::format($product->currentPrice->unit_price_pesewas) }}
                <span class="text-xs font-normal text-gray-500">/ {{ $product->unit }}</span>
            </p>
        </div>

        @if ($inStock)
            <form method="POST" action="{{ route('cart.add') }}" class="mt-auto">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="btn w-full px-3 py-2 text-xs sm:text-sm">Add to cart</button>
            </form>
        @else
            <p class="mt-auto flex min-h-[2.375rem] items-center justify-center rounded-md bg-gray-50 text-sm text-gray-500 ring-1 ring-gray-200/80">Out of stock</p>
        @endif
    </div>
</article>
