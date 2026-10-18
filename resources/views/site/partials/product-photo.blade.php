@if ($product->photoUrl())
    <img src="{{ $product->photoUrl() }}" alt="{{ $product->name }}" class="{{ $class ?? '' }}" loading="lazy">
@else
    <div class="{{ $class ?? '' }} relative flex items-center justify-center overflow-hidden bg-gradient-to-br from-sky-100 via-cyan-50 to-sky-200 text-sky-800/50" role="img" aria-label="{{ $product->name }}">
        <div class="absolute inset-0 opacity-30" style="background-image: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.7) 0, transparent 40%), radial-gradient(circle at 80% 70%, rgba(12,74,110,0.12) 0, transparent 45%);"></div>
        <svg class="relative h-1/3 w-1/3" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true"><path d="M3 16c4-6 10-8 15-6l6-5v8l5 3-5 3v8l-6-5c-5 2-11 0-15-6z"/></svg>
    </div>
@endif
