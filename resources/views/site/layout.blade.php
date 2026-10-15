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
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    @vite('resources/css/site.css')
</head>
<body class="flex min-h-screen flex-col bg-white font-sans text-gray-800 antialiased">
    <header class="border-b border-gray-100 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-2 px-4 py-4 sm:gap-4 sm:px-6">
            <a href="{{ route('site.home') }}" class="flex shrink-0 items-center gap-1.5 text-base font-bold text-sky-800 sm:gap-2 sm:text-lg">
                <svg class="h-7 w-7 sm:h-8 sm:w-8" viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M3 16c4-6 10-8 15-6l6-5v8l5 3-5 3v8l-6-5c-5 2-11 0-15-6z" fill="currentColor"/><circle cx="11" cy="15" r="1.5" fill="#fff"/></svg>
                <span class="max-[399px]:sr-only">Blue Foods</span>
            </a>
            <nav class="flex min-w-0 items-center gap-0.5 text-sm font-medium sm:gap-4">
                @foreach (['site.home' => 'Home', 'shop.index' => 'Shop', 'site.about' => 'About us', 'site.contact' => 'Contact'] as $name => $label)
                    <a href="{{ route($name) }}" @class([
                        'whitespace-nowrap rounded px-1 py-1 sm:px-2',
                        'hidden sm:inline' => $name === 'site.home',
                        'text-sky-800 underline underline-offset-8 decoration-2' => request()->routeIs($name) || ($name === 'shop.index' && request()->routeIs('shop.show')),
                        'text-gray-600 hover:text-sky-800' => ! (request()->routeIs($name) || ($name === 'shop.index' && request()->routeIs('shop.show'))),
                    ])>{{ $label }}</a>
                @endforeach
                <a href="{{ route('cart.show') }}" class="ml-1 inline-flex shrink-0 items-center gap-1 rounded-full bg-sky-50 px-2.5 py-1.5 text-sky-800 hover:bg-sky-100 sm:px-3">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/></svg>
                    <span class="sr-only sm:not-sr-only">Cart</span>
                    @if ($cartCount > 0)
                        <span class="rounded-full bg-sky-700 px-1.5 text-xs text-white">{{ $cartCount }}</span>
                    @endif
                </a>
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

    <footer class="mt-16 border-t border-gray-100 bg-gray-50">
        <div class="mx-auto grid max-w-6xl gap-6 px-4 py-10 text-sm text-gray-600 sm:grid-cols-3 sm:px-6">
            <div>
                <p class="font-semibold text-gray-800">Blue Foods</p>
                <p class="mt-2">{{ $site['contact_address'] }}</p>
            </div>
            <div>
                <p class="font-semibold text-gray-800">Contact</p>
                <p class="mt-2"><a href="tel:{{ preg_replace('/[^\d+]/', '', $site['contact_phone']) }}" class="hover:text-sky-800">{{ $site['contact_phone'] }}</a></p>
                <p><a href="mailto:{{ $site['contact_email'] }}" class="hover:text-sky-800">{{ $site['contact_email'] }}</a></p>
            </div>
            <div>
                <p class="font-semibold text-gray-800">Opening hours</p>
                <p class="mt-2">{{ $site['contact_hours'] }}</p>
            </div>
        </div>
        <div class="border-t border-gray-100 py-4 text-center text-xs text-gray-400">
            &copy; {{ now()->year }} Blue Foods. Your details are used only to process your order.
            <a href="{{ route('login') }}" class="ml-2 hover:text-gray-600">Staff sign in</a>
        </div>
    </footer>
</body>
</html>
