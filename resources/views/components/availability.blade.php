@props(['available'])
{{-- W2: In stock, Only N left (below the threshold) or Out of stock, from the online shop balance. --}}
@if ($available <= 0)
    <span {{ $attributes->class('inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600') }}>Out of stock</span>
@elseif ($available < config('shop.low_stock_threshold'))
    <span {{ $attributes->class('inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-amber-200') }}>Only {{ $available }} left</span>
@else
    <span {{ $attributes->class('inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-800 ring-1 ring-emerald-200') }}>In stock</span>
@endif
