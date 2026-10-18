@extends('site.layout')

@section('title', $category ? "{$category} | Shop" : 'Shop')
@section('description', 'Order Blue Foods fish and seafood online for delivery or collection.')

@section('content')
    <section class="shop-band relative overflow-hidden text-white">
        <div class="pointer-events-none absolute -right-16 top-0 h-56 w-56 rounded-full bg-white/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -left-10 bottom-0 h-40 w-40 rounded-full bg-cyan-300/20 blur-2xl"></div>
        <div class="relative mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
            <p class="fade-up text-sm font-medium uppercase tracking-[0.2em] text-sky-100/80">Blue Foods</p>
            <h1 class="fade-up fade-up-delay-1 mt-2 font-display text-4xl font-semibold tracking-tight sm:text-5xl">
                {{ $category ?: 'Shop fresh from the sea' }}
            </h1>
            <p class="fade-up fade-up-delay-2 mt-3 max-w-xl text-base text-sky-100/90 sm:text-lg">
                Smoked, frozen and fresh fish, packed with care. Order online for delivery or collection.
            </p>
        </div>
    </section>

    <div class="bg-[var(--bf-sand)]">
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
            @if ($categories->count() > 1)
                <div class="fade-up fade-up-delay-3 flex flex-wrap items-center gap-2" role="navigation" aria-label="Product categories">
                    <a href="{{ route('shop.index') }}" @class([
                        'rounded-full px-3.5 py-1.5 text-sm font-medium transition',
                        'bg-sky-900 text-white shadow-sm' => ! $category,
                        'bg-white text-sky-900 ring-1 ring-sky-900/10 hover:ring-sky-900/25' => $category,
                    ])>All</a>
                    @foreach ($categories as $c)
                        <a href="{{ route('shop.index', ['category' => $c]) }}" @class([
                            'rounded-full px-3.5 py-1.5 text-sm font-medium transition',
                            'bg-sky-900 text-white shadow-sm' => $category === $c,
                            'bg-white text-sky-900 ring-1 ring-sky-900/10 hover:ring-sky-900/25' => $category !== $c,
                        ])>{{ $c }}</a>
                    @endforeach
                </div>
            @endif

            @if ($products->isEmpty())
                <div class="mt-12 rounded-2xl bg-white px-6 py-16 text-center ring-1 ring-sky-950/5">
                    <svg class="mx-auto h-12 w-12 text-sky-300" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true"><path d="M3 16c4-6 10-8 15-6l6-5v8l5 3-5 3v8l-6-5c-5 2-11 0-15-6z"/></svg>
                    <p class="mt-4 font-display text-xl font-semibold text-sky-950">Nothing in this category right now</p>
                    <p class="mt-2 text-sm text-gray-500">Try another filter, or check back soon for the next catch.</p>
                    @if ($category)
                        <a href="{{ route('shop.index') }}" class="btn mt-6">See all products</a>
                    @endif
                </div>
            @else
                <p class="mt-6 text-sm text-gray-500">
                    {{ $products->count() }} {{ Str::plural('product', $products->count()) }}
                    @if ($category) in {{ $category }}@endif
                </p>
                <div class="mt-5 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($products as $i => $product)
                        <div class="fade-up" style="animation-delay: {{ min($i * 0.05, 0.3) }}s">
                            @include('site.partials.product-card', ['product' => $product, 'available' => $availability[$product->id]])
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
