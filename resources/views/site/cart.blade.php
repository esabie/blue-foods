@extends('site.layout')

@section('title', 'Your cart')
@section('robots', 'noindex')

@section('content')
    <div class="bg-[var(--bf-sand)]">
        <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-12">
            <div class="fade-up flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.18em] text-sky-800/70">Blue Foods</p>
                    <h1 class="mt-1 font-display text-3xl font-semibold tracking-tight text-sky-950 sm:text-4xl">Your cart</h1>
                </div>
                @unless ($lines->isEmpty())
                    <a href="{{ route('shop.index') }}" class="text-sm font-medium text-sky-800 hover:underline">Continue shopping</a>
                @endunless
            </div>

            @if ($lines->isEmpty())
                <div class="fade-up fade-up-delay-1 mx-auto mt-10 max-w-lg rounded-2xl bg-white px-6 py-14 text-center ring-1 ring-sky-950/5">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-sky-50 text-sky-700">
                        <svg class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/></svg>
                    </div>
                    <p class="mt-5 font-display text-xl font-semibold text-sky-950">Your cart is empty</p>
                    <p class="mt-2 text-sm text-gray-500">Browse smoked, frozen and fresh fish, then add what you need.</p>
                    <a href="{{ route('shop.index') }}" class="btn mt-7">Go to the shop</a>
                </div>
            @else
                <div class="fade-up fade-up-delay-1 mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <ul class="overflow-hidden rounded-2xl bg-white ring-1 ring-sky-950/5">
                        @foreach ($lines as $line)
                            @php($product = $line['product'])
                            <li class="flex gap-4 border-b border-sky-950/5 p-4 last:border-b-0 sm:gap-5 sm:p-5">
                                <a href="{{ route('shop.show', $product->sku) }}" class="shrink-0 overflow-hidden rounded-xl bg-sky-50 ring-1 ring-sky-950/5">
                                    @include('site.partials.product-photo', ['product' => $product, 'class' => 'h-20 w-20 object-cover sm:h-24 sm:w-24'])
                                </a>
                                <div class="flex min-w-0 flex-1 flex-col gap-3">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <a href="{{ route('shop.show', $product->sku) }}" class="font-display text-lg font-semibold text-sky-950 hover:text-sky-800">{{ $product->name }}</a>
                                            <p class="mt-0.5 text-sm text-gray-500">{{ \App\Support\Money::format($line['unit_price_pesewas']) }} per {{ $product->unit }}</p>
                                            @if ($line['quantity'] > $line['available'])
                                                <p class="mt-1 text-sm font-medium text-red-700">Only {{ $line['available'] }} available now. Please reduce the quantity.</p>
                                            @endif
                                        </div>
                                        <p class="shrink-0 font-semibold tabular-nums text-sky-950">{{ \App\Support\Money::format($line['total_pesewas']) }}</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <form method="POST" action="{{ route('cart.update', $product) }}" class="flex items-center gap-2 rounded-lg bg-sky-50/80 p-1 ring-1 ring-sky-900/5">
                                            @csrf
                                            @method('PATCH')
                                            <label for="qty-{{ $product->id }}" class="sr-only">Quantity</label>
                                            <input id="qty-{{ $product->id }}" name="quantity" type="number" min="0" max="{{ max($line['available'], $line['quantity']) }}" value="{{ $line['quantity'] }}" class="input w-16 border-0 bg-transparent py-1.5 text-center shadow-none focus:ring-0">
                                            <button type="submit" class="rounded-md bg-white px-2.5 py-1.5 text-xs font-semibold text-sky-900 shadow-sm ring-1 ring-sky-900/10 hover:bg-sky-50">Update</button>
                                        </form>
                                        <form method="POST" action="{{ route('cart.update', $product) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="quantity" value="0">
                                            <button type="submit" class="text-sm text-gray-500 hover:text-red-700">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <aside class="h-fit rounded-2xl bg-sky-950 p-5 text-white shadow-lg shadow-sky-950/20 sm:p-6 lg:sticky lg:top-24">
                        <h2 class="font-display text-lg font-semibold">Order summary</h2>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-sky-200">Items</dt>
                                <dd class="tabular-nums">{{ $lines->sum('quantity') }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-t border-white/10 pt-3 text-base">
                                <dt class="font-medium">Subtotal</dt>
                                <dd class="font-semibold tabular-nums">{{ \App\Support\Money::format($subtotal) }}</dd>
                            </div>
                        </dl>
                        <p class="mt-3 text-xs leading-relaxed text-sky-200/80">Delivery fee, if any, is added at checkout. You can also collect free.</p>
                        <a href="{{ route('checkout.show') }}" class="mt-5 flex w-full items-center justify-center rounded-md bg-amber-400 px-5 py-3 text-sm font-semibold text-sky-950 transition hover:bg-amber-300">Checkout</a>
                        <a href="{{ route('shop.index') }}" class="mt-3 flex w-full items-center justify-center rounded-md px-5 py-2.5 text-sm font-medium text-sky-100 ring-1 ring-white/20 transition hover:bg-white/5">Continue shopping</a>
                    </aside>
                </div>
            @endif
        </div>
    </div>
@endsection
