@extends('site.layout')

@section('title', 'Terms and conditions')
@section('description', 'Terms for shopping with Blue Foods online — orders, payment, delivery, collection and returns.')

@section('content')
    <div class="bg-[var(--bf-sand)]">
        <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
            <div class="fade-up">
                <p class="text-sm font-medium uppercase tracking-[0.18em] text-sky-800/70">Legal</p>
                <h1 class="mt-1 font-display text-3xl font-semibold tracking-tight text-sky-950 sm:text-4xl">Terms and conditions</h1>
                <p class="mt-2 text-sm text-gray-500">Last updated {{ now()->format('j F Y') }}</p>
                <div class="mt-4">@include('site.partials.legal-nav')</div>
            </div>

            <div class="fade-up fade-up-delay-1 prose-site mt-8 space-y-6 text-sm leading-relaxed text-gray-700 sm:text-base">
                <p>By placing an order on the Blue Foods website you agree to these terms. If you do not agree, please do not use the shop.</p>

                <section>
                    <h2 class="font-display text-lg font-semibold text-sky-950">Orders</h2>
                    <p class="mt-2">An order is an offer to buy the products listed in your cart at the prices shown. We confirm acceptance by SMS and by updating your order status. We may cancel an unpaid order if payment is not completed while stock is held.</p>
                </section>

                <section>
                    <h2 class="font-display text-lg font-semibold text-sky-950">Prices and payment</h2>
                    <p class="mt-2">Prices are in Ghana cedis (GHS) and include the amounts shown at checkout. Delivery has a separate fee when you choose delivery; collection is free. You may pay online (for example mobile money) or pay on delivery/collection where that option is offered.</p>
                </section>

                <section>
                    <h2 class="font-display text-lg font-semibold text-sky-950">Delivery and collection</h2>
                    <p class="mt-2">{{ $site['delivery_areas'] }} Delivery costs {{ \App\Support\Money::format((int) $site['delivery_fee_pesewas']) }}. You may also collect free from {{ $site['collection_point'] }}.</p>
                    <p class="mt-2">Delivery times are estimates. Please make sure someone can receive the order or that we can reach you on the phone number you provided.</p>
                </section>

                <section>
                    <h2 class="font-display text-lg font-semibold text-sky-950">Product quality</h2>
                    <p class="mt-2">Our products are perishable. Please store and use them as directed on the pack or product page. If something arrives damaged or not as described, contact us promptly with your order number so we can help.</p>
                </section>

                <section>
                    <h2 class="font-display text-lg font-semibold text-sky-950">Cancellations and refunds</h2>
                    <p class="mt-2">Unpaid orders may be cancelled by us if the payment hold expires. If you were charged for an order we could not fulfil, we will arrange a refund. Refund timing depends on your payment provider.</p>
                </section>

                <section>
                    <h2 class="font-display text-lg font-semibold text-sky-950">Website use</h2>
                    <p class="mt-2">You may use this site to browse and buy for personal or household use. Do not misuse the site, attempt unauthorised access, or interfere with other customers’ orders.</p>
                </section>

                <section>
                    <h2 class="font-display text-lg font-semibold text-sky-950">Contact</h2>
                    <p class="mt-2">
                        For order help, see our <a href="{{ route('site.faq') }}" class="font-medium text-sky-800 hover:underline">FAQ</a>,
                        <a href="{{ route('shop.track') }}" class="font-medium text-sky-800 hover:underline">track your order</a>,
                        or reach us at
                        <a href="mailto:{{ $site['contact_email'] }}" class="font-medium text-sky-800 hover:underline">{{ $site['contact_email'] }}</a>
                        /
                        <a href="tel:{{ preg_replace('/[^\d+]/', '', $site['contact_phone']) }}" class="font-medium text-sky-800 hover:underline">{{ $site['contact_phone'] }}</a>.
                    </p>
                </section>
            </div>
        </div>
    </div>
@endsection
