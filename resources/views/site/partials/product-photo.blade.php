@if ($product->photoUrl())
    <img src="{{ $product->photoUrl() }}" alt="{{ $product->name }}" class="{{ $class ?? '' }} object-cover" loading="lazy">
@else
    <div class="{{ $class ?? '' }} flex items-center justify-center bg-gradient-to-br from-sky-100 to-sky-200 text-sky-700/60" role="img" aria-label="{{ $product->name }}">
        <svg class="h-1/3 w-1/3" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true"><path d="M3 16c4-6 10-8 15-6l6-5v8l5 3-5 3v8l-6-5c-5 2-11 0-15-6z"/></svg>
    </div>
@endif
