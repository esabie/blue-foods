@extends('site.layout')

@section('title', $product->name)
@section('description', \Illuminate\Support\Str::limit(strip_tags($product->description ?: "{$product->name} from Blue Foods."), 155))
@if ($product->photoUrl())
    @section('og_image', $product->photoUrl())
@endif

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <nav class="text-sm text-gray-500"><a href="{{ route('shop.index') }}" class="hover:text-sky-800">Shop</a> / {{ $product->name }}</nav>

        <div class="mt-6 grid gap-8 md:grid-cols-2">
            @include('site.partials.product-photo', ['product' => $product, 'class' => 'aspect-square w-full rounded-lg'])

            <div>
                <p class="text-sm uppercase tracking-wide text-gray-400">{{ $product->category }}</p>
                <h1 class="mt-1 text-3xl font-bold text-gray-900">{{ $product->name }}</h1>
                <p class="mt-4 text-2xl font-semibold text-gray-900">{{ \App\Support\Money::format($product->currentPrice->unit_price_pesewas) }} <span class="text-base font-normal text-gray-500">per {{ $product->unit }}</span></p>
                <div class="mt-3"><x-availability :available="$available" /></div>

                @if ($product->description)
                    <div class="prose-site mt-6 text-gray-700">
                        @foreach (preg_split('/\n\s*\n/', $product->description) as $para)
                            <p>{!! nl2br(e($para)) !!}</p>
                        @endforeach
                    </div>
                @endif

                @if ($available > 0)
                    <form method="POST" action="{{ route('cart.add') }}" class="mt-8 flex items-end gap-3">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <div>
                            <label for="quantity" class="block text-sm font-medium text-gray-700">Quantity</label>
                            <input id="quantity" name="quantity" type="number" min="1" max="{{ $available }}" value="{{ old('quantity', 1) }}" class="input mt-1 w-24" required>
                        </div>
                        <button type="submit" class="btn">Add to cart</button>
                    </form>
                @else
                    <p class="mt-8 text-gray-600">This product is out of stock online. Please check back soon.</p>
                @endif
            </div>
        </div>
    </div>
@endsection
