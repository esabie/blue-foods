@extends('site.layout')

@section('title', 'Privacy policy')
@section('description', 'How Blue Foods collects, uses and protects your personal information when you shop with us.')

@section('content')
    <div class="bg-[var(--bf-sand)]">
        <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
            <div class="fade-up">
                <p class="text-sm font-medium uppercase tracking-[0.18em] text-sky-800/70">Legal</p>
                <h1 class="mt-1 font-display text-3xl font-semibold tracking-tight text-sky-950 sm:text-4xl">Privacy policy</h1>
                <p class="mt-2 text-sm text-gray-500">Last updated {{ now()->format('j F Y') }}</p>
                <div class="mt-4">@include('site.partials.legal-nav')</div>
            </div>

            <div class="fade-up fade-up-delay-1 prose-site mt-8 space-y-6 text-sm leading-relaxed text-gray-700 sm:text-base">
                <p>Blue Foods (“we”, “us”) respects your privacy. This policy explains what we collect when you use our website and online shop, and how we use it.</p>

                <section>
                    <h2 class="font-display text-lg font-semibold text-sky-950">Information we collect</h2>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li>Order details you provide at checkout: name, phone number, delivery address (if you choose delivery), and payment method.</li>
                        <li>Order and payment status needed to fulfil and support your purchase.</li>
                        <li>Technical data such as basic device and browser information used to keep the site working securely.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="font-display text-lg font-semibold text-sky-950">How we use your information</h2>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li>To process, pack, deliver or prepare your order for collection.</li>
                        <li>To send order updates by SMS to the phone number you gave us.</li>
                        <li>To answer questions and handle refunds or complaints.</li>
                        <li>To improve our products, website and service.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="font-display text-lg font-semibold text-sky-950">Payments</h2>
                    <p class="mt-2">Online payments are handled by our payment provider. We do not store your full mobile money PIN or card details on our servers.</p>
                </section>

                <section>
                    <h2 class="font-display text-lg font-semibold text-sky-950">Sharing</h2>
                    <p class="mt-2">We share information only as needed to run the shop — for example with our payment provider, SMS provider, and delivery partners. We do not sell your personal information.</p>
                </section>

                <section>
                    <h2 class="font-display text-lg font-semibold text-sky-950">How long we keep data</h2>
                    <p class="mt-2">We keep order records for as long as needed for fulfilment, accounting, and to resolve disputes, then delete or anonymise them when they are no longer required.</p>
                </section>

                <section>
                    <h2 class="font-display text-lg font-semibold text-sky-950">Your choices</h2>
                    <p class="mt-2">You can ask us what data we hold about your orders, or request a correction, by contacting us. You can track an existing order with your order number and phone on our <a href="{{ route('shop.track') }}" class="font-medium text-sky-800 hover:underline">Track order</a> page.</p>
                </section>

                <section>
                    <h2 class="font-display text-lg font-semibold text-sky-950">Contact</h2>
                    <p class="mt-2">
                        Questions about privacy:
                        <a href="mailto:{{ $site['contact_email'] }}" class="font-medium text-sky-800 hover:underline">{{ $site['contact_email'] }}</a>
                        or
                        <a href="tel:{{ preg_replace('/[^\d+]/', '', $site['contact_phone']) }}" class="font-medium text-sky-800 hover:underline">{{ $site['contact_phone'] }}</a>.
                    </p>
                </section>
            </div>
        </div>
    </div>
@endsection
