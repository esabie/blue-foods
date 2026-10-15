<a href="{{ route('shop.show', $product->sku) }}" class="group flex flex-col overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm transition hover:shadow-md">
    @include('site.partials.product-photo', ['product' => $product, 'class' => 'aspect-[4/3] w-full'])
    <div class="flex flex-1 flex-col gap-2 p-4">
        <p class="text-xs uppercase tracking-wide text-gray-400">{{ $product->category }}</p>
        <h3 class="font-semibold text-gray-900 group-hover:text-sky-800">{{ $product->name }}</h3>
        <div class="mt-auto flex items-center justify-between gap-2 pt-2">
            <p class="font-semibold text-gray-900">{{ \App\Support\Money::format($product->currentPrice->unit_price_pesewas) }} <span class="text-xs font-normal text-gray-500">/ {{ $product->unit }}</span></p>
            <x-availability :available="$available" />
        </div>
    </div>
</a>
