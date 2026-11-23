<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php($pageTitle = trim($__env->yieldContent('title')))
    <title>{{ $pageTitle ? $pageTitle.' | Blue Foods' : 'Blue Foods' }}</title>
    <meta name="description" content="{{ trim($__env->yieldContent('description')) ?: $site['seo_description'] }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:site_name" content="Blue Foods">
    <meta property="og:title" content="{{ $pageTitle ?: 'Blue Foods' }}">
    <meta property="og:description" content="{{ trim($__env->yieldContent('description')) ?: $site['seo_description'] }}">
    @hasSection('og_image')<meta property="og:image" content="@yield('og_image')">@endif
    @hasSection('robots')<meta name="robots" content="@yield('robots')">@endif
    @hasSection('referrer')<meta name="referrer" content="@yield('referrer')">@endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|fraunces:500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/site.css', 'resources/js/site.js'])
</head>
<body class="flex min-h-screen flex-col bg-white font-sans text-gray-800 antialiased" @if (session('open_cart')) data-open-cart="1" @endif>
    <header class="sticky top-0 z-30 border-b border-sky-950/10 bg-white/90 backdrop-blur-md">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-2 px-4 py-3.5 sm:gap-4 sm:px-6">
            <a href="{{ route('site.home') }}" class="flex shrink-0 items-center gap-1.5 font-display text-lg font-semibold tracking-tight text-sky-900 sm:gap-2 sm:text-xl">
                <svg class="h-7 w-7 sm:h-8 sm:w-8" viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M3 16c4-6 10-8 15-6l6-5v8l5 3-5 3v8l-6-5c-5 2-11 0-15-6z" fill="currentColor"/><circle cx="11" cy="15" r="1.5" fill="#fff"/></svg>
                <span class="max-[399px]:sr-only">Blue Foods</span>
            </a>
            <nav class="flex min-w-0 items-center gap-0.5 text-sm font-medium sm:gap-4">
                @foreach (['site.home' => 'Home', 'shop.index' => 'Shop', 'shop.track' => 'Track', 'site.about' => 'About us', 'site.contact' => 'Contact'] as $name => $label)
                    <a href="{{ route($name) }}" @class([
                        'whitespace-nowrap rounded px-1 py-1 sm:px-2',
                        'hidden sm:inline' => in_array($name, ['site.home', 'site.about'], true),
                        'text-sky-800 underline underline-offset-8 decoration-2' => request()->routeIs($name) || ($name === 'shop.index' && request()->routeIs('shop.show')),
                        'text-gray-600 hover:text-sky-800' => ! (request()->routeIs($name) || ($name === 'shop.index' && request()->routeIs('shop.show'))),
                    ])>{{ $label }}</a>
                @endforeach
                <button type="button" data-cart-open class="ml-1 inline-flex shrink-0 items-center gap-1.5 rounded-full bg-sky-900 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-sky-800 sm:px-3.5" aria-haspopup="dialog" aria-controls="cart-drawer">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/></svg>
                    <span class="sr-only sm:not-sr-only">Cart</span>
                    @if ($cartCount > 0)
                        <span data-cart-count class="rounded-full bg-amber-400 px-1.5 text-xs font-semibold text-sky-950">{{ $cartCount }}</span>
                    @endif
                </button>
            </nav>
        </div>
    </header>

    @if (session('status'))
        <div class="mx-auto mt-4 w-full max-w-6xl px-4 sm:px-6">
            <div class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200">{{ session('status') }}</div>
        </div>
    @endif
    @if ($errors->any())
        <div class="mx-auto mt-4 w-full max-w-6xl px-4 sm:px-6">
            <div class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        </div>
    @endif

    <main class="flex-1">
        @yield('content')
    </main>

    <footer class="mt-16 border-t border-sky-950/10 bg-[var(--bf-sand)]">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:gap-8">
            <div class="sm:col-span-2 lg:col-span-1">
                <a href="{{ route('site.home') }}" class="inline-flex items-center gap-2 font-display text-lg font-semibold tracking-tight text-sky-950">
                    <svg class="h-8 w-8 text-sky-800" viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M3 16c4-6 10-8 15-6l6-5v8l5 3-5 3v8l-6-5c-5 2-11 0-15-6z" fill="currentColor"/><circle cx="11" cy="15" r="1.5" fill="#fff"/></svg>
                    Blue Foods
                </a>
                <p class="mt-3 max-w-xs text-sm leading-relaxed text-gray-600">{{ $site['contact_address'] }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-800/70">Explore</p>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach (['shop.index' => 'Shop', 'shop.track' => 'Track order', 'site.about' => 'About us', 'site.contact' => 'Contact', 'site.faq' => 'FAQ'] as $name => $label)
                        <li><a href="{{ route($name) }}" class="font-medium text-sky-950 transition hover:text-sky-700">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-800/70">Contact</p>
                <ul class="mt-3 space-y-2 text-sm text-gray-600">
                    <li>
                        <a href="tel:{{ preg_replace('/[^\d+]/', '', $site['contact_phone']) }}" class="font-medium text-sky-950 transition hover:text-sky-700">{{ $site['contact_phone'] }}</a>
                    </li>
                    <li>
                        <a href="mailto:{{ $site['contact_email'] }}" class="transition hover:text-sky-800">{{ $site['contact_email'] }}</a>
                    </li>
                </ul>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-800/70">Opening hours</p>
                <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-gray-600">{{ $site['contact_hours'] }}</p>
            </div>
        </div>
        <div class="border-t border-sky-950/10">
            <div class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-3 px-4 py-4 text-xs text-gray-500 sm:flex-row sm:items-center sm:px-6">
                <p>&copy; {{ now()->year }} Blue Foods</p>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <a href="{{ route('site.privacy') }}" class="transition hover:text-sky-800">Privacy</a>
                    <a href="{{ route('site.terms') }}" class="transition hover:text-sky-800">Terms</a>
                    <a href="{{ route('site.faq') }}" class="transition hover:text-sky-800">FAQ</a>
                    <a href="{{ route('login') }}" class="transition hover:text-sky-800">Staff sign in</a>
                </div>
            </div>
        </div>
    </footer>

    @include('site.partials.cart-drawer')
</body>
</html>
