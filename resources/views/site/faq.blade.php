@extends('site.layout')

@section('title', 'FAQ')
@section('description', 'Answers to common questions about ordering, payment, delivery and tracking with Blue Foods.')

@section('content')
    <div class="bg-[var(--bf-sand)]">
        <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
            <div class="fade-up">
                <p class="text-sm font-medium uppercase tracking-[0.18em] text-sky-800/70">Help</p>
                <h1 class="mt-1 font-display text-3xl font-semibold tracking-tight text-sky-950 sm:text-4xl">Frequently asked questions</h1>
                <p class="mt-2 text-sm text-gray-500">Quick answers about shopping with Blue Foods.</p>
                <div class="mt-4">@include('site.partials.legal-nav')</div>
            </div>

            <div class="fade-up fade-up-delay-1 mt-8 space-y-3">
                @php
                    $faqs = [
                        ['How do I place an order?', 'Add products to your cart, go to checkout, enter your name and phone, choose delivery or collection, and select how you want to pay. You will get an SMS with your order number.'],
                        ['How do I track my order?', 'Use Track order with the order number from your SMS and the same phone you used at checkout. You can also keep the status page open after you place the order.'],
                        ['What payment methods do you accept?', 'We offer mobile money for online payment, and pay on delivery or collection when that option is available at checkout.'],
                        ['How long is stock held while I pay?', 'For online payment, your items are held for a short time while you complete payment. If the time runs out, the items may be released for other customers.'],
                        ['Where do you deliver?', $site['delivery_areas'].' Delivery costs '.\App\Support\Money::format((int) $site['delivery_fee_pesewas']).'.'],
                        ['Can I collect instead?', 'Yes. Collection is free from '.$site['collection_point'].'. We will text you when your order is packed and ready.'],
                        ['What are your opening hours?', $site['contact_hours']],
                        ['What if my order is wrong or damaged?', 'Contact us as soon as you can with your order number and a short description of the issue. We will work with you on a replacement or refund where appropriate.'],
                        ['Do you store my payment details?', 'No. Online payments are handled by our payment provider. See our Privacy policy for more detail.'],
                    ];
                @endphp

                @foreach ($faqs as [$question, $answer])
                    <details class="group rounded-2xl border border-sky-950/10 bg-white px-5 py-4 shadow-sm shadow-sky-950/5 open:pb-5">
                        <summary class="cursor-pointer list-none font-display text-base font-semibold text-sky-950 marker:content-none [&::-webkit-details-marker]:hidden">
                            <span class="flex items-start justify-between gap-3">
                                <span>{{ $question }}</span>
                                <span class="mt-0.5 shrink-0 text-sky-800 transition group-open:rotate-45" aria-hidden="true">+</span>
                            </span>
                        </summary>
                        <p class="mt-3 text-sm leading-relaxed text-gray-600">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>

            <p class="fade-up fade-up-delay-2 mt-10 text-sm text-gray-500">
                Still need help?
                <a href="{{ route('site.contact') }}" class="font-medium text-sky-800 hover:underline">Contact us</a>
                or call
                <a href="tel:{{ preg_replace('/[^\d+]/', '', $site['contact_phone']) }}" class="font-medium text-sky-800 hover:underline">{{ $site['contact_phone'] }}</a>.
            </p>
        </div>
    </div>
@endsection
