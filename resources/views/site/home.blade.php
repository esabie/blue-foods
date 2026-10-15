@extends('site.layout')

@section('title', $site['home_title'])
@section('description', $site['seo_description'])
@if ($site['home_image'])
    @section('og_image', Storage::disk('public')->url($site['home_image']))
@endif

@section('content')
    <section class="relative overflow-hidden bg-sky-900 text-white">
        @if ($site['home_image'])
            <img src="{{ Storage::disk('public')->url($site['home_image']) }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-40">
        @endif
        <div class="relative mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
            <h1 class="max-w-2xl text-3xl font-bold leading-tight sm:text-5xl">{{ $site['home_title'] }}</h1>
            <p class="mt-4 max-w-xl text-lg text-sky-100">{{ $site['home_intro'] }}</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('shop.index') }}" class="btn bg-white text-sky-900 hover:bg-sky-50">Shop now</a>
                <a href="{{ route('site.about') }}" class="btn-secondary bg-transparent text-white ring-white/40 hover:bg-white/10">About us</a>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <div class="flex items-end justify-between gap-4">
            <h2 class="text-2xl font-bold text-gray-900">Our products</h2>
            <a href="{{ route('shop.index') }}" class="text-sm font-medium text-sky-800 hover:underline">See all &rarr;</a>
        </div>
        @if ($products->isEmpty())
            <p class="mt-6 text-gray-500">Products will appear here soon.</p>
        @else
            <div class="mt-6 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
                @foreach ($products as $product)
                    @include('site.partials.product-card', ['product' => $product, 'available' => $availability[$product->id]])
                @endforeach
            </div>
        @endif
    </section>

    <section class="bg-gray-50">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:grid-cols-3 sm:px-6">
            <div>
                <h3 class="font-semibold text-gray-900">Delivery</h3>
                <p class="mt-2 text-sm text-gray-600">{{ $site['delivery_areas'] }} Delivery costs {{ \App\Support\Money::format((int) $site['delivery_fee_pesewas']) }}.</p>
            </div>
            <div>
                <h3 class="font-semibold text-gray-900">Collection</h3>
                <p class="mt-2 text-sm text-gray-600">Collect free from {{ $site['collection_point'] }}.</p>
            </div>
            <div>
                <h3 class="font-semibold text-gray-900">Pay your way</h3>
                <p class="mt-2 text-sm text-gray-600">Mobile money, card, or pay when you receive your order.</p>
            </div>
        </div>
    </section>
@endsection
