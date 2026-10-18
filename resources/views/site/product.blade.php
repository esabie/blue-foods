@extends('site.layout')

@section('title', $product->name)
@section('description', \Illuminate\Support\Str::limit(strip_tags($product->description ?: "{$product->name} from Blue Foods."), 155))
@if ($product->photoUrl())
    @section('og_image', $product->photoUrl())
@endif

@section('content')
    <div class="bg-[var(--bf-sand)]">
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
            <nav class="text-sm text-gray-500">
                <a href="{{ route('shop.index') }}" class="font-medium text-sky-800 hover:underline">Shop</a>
                <span class="mx-1.5 text-gray-300">/</span>
                <span class="text-gray-700">{{ $product->name }}</span>
            </nav>

            <div class="mt-6 grid gap-8 overflow-hidden rounded-2xl bg-white p-4 ring-1 ring-sky-950/5 sm:p-6 md:grid-cols-2 md:gap-10 lg:p-8">
                <div class="overflow-hidden rounded-xl bg-sky-50">
                    @include('site.partials.product-photo', ['product' => $product, 'class' => 'aspect-square w-full object-cover'])
                </div>

                <div class="flex flex-col">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-800/70">{{ $product->category }}</p>
                    <h1 class="mt-2 font-display text-3xl font-semibold tracking-tight text-sky-950 sm:text-4xl">{{ $product->name }}</h1>
                    <p class="mt-4 text-2xl font-semibold tabular-nums text-sky-950">
                        {{ \App\Support\Money::format($product->currentPrice->unit_price_pesewas) }}
                        <span class="text-base font-normal text-gray-500">per {{ $product->unit }}</span>
                    </p>
                    <div class="mt-3"><x-availability :available="$available" /></div>

                    @if ($product->description)
                        <div class="prose-site mt-6 text-gray-700">
                            @foreach (preg_split('/\n\s*\n/', $product->description) as $para)
                                <p>{!! nl2br(e($para)) !!}</p>
                            @endforeach
                        </div>
                    @endif

                    @if ($available > 0)
                        <form method="POST" action="{{ route('cart.add') }}" class="mt-auto flex flex-wrap items-end gap-3 pt-8">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <div>
                                <label for="quantity" class="block text-sm font-medium text-gray-700">Quantity</label>
                                <input id="quantity" name="quantity" type="number" min="1" max="{{ $available }}" value="{{ old('quantity', 1) }}" class="input mt-1 w-24" required>
                            </div>
                            <button type="submit" class="btn">Add to cart</button>
                        </form>
                    @else
                        <p class="mt-auto pt-8 text-gray-600">This product is out of stock online. Please check back soon.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
