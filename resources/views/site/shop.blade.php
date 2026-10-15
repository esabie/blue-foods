@extends('site.layout')

@section('title', $category ? "{$category} | Shop" : 'Shop')
@section('description', 'Order Blue Foods fish and seafood online for delivery or collection.')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-bold text-gray-900">Shop</h1>

        @if ($categories->count() > 1)
            <div class="mt-6 flex flex-wrap gap-2 text-sm">
                <a href="{{ route('shop.index') }}" @class(['rounded-full px-3 py-1 ring-1', 'bg-sky-700 text-white ring-sky-700' => ! $category, 'text-gray-700 ring-gray-300 hover:bg-gray-50' => $category])>All</a>
                @foreach ($categories as $c)
                    <a href="{{ route('shop.index', ['category' => $c]) }}" @class(['rounded-full px-3 py-1 ring-1', 'bg-sky-700 text-white ring-sky-700' => $category === $c, 'text-gray-700 ring-gray-300 hover:bg-gray-50' => $category !== $c])>{{ $c }}</a>
                @endforeach
            </div>
        @endif

        @if ($products->isEmpty())
            <p class="mt-10 text-gray-500">No products to show right now.</p>
        @else
            <div class="mt-8 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
                @foreach ($products as $product)
                    @include('site.partials.product-card', ['product' => $product, 'available' => $availability[$product->id]])
                @endforeach
            </div>
        @endif
    </div>
@endsection
