@extends('site.layout')

@section('title', 'Contact us')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <h1 class="text-3xl font-bold text-gray-900">Contact us</h1>
        <dl class="mt-8 grid gap-6 sm:grid-cols-2">
            <div class="rounded-lg border border-gray-100 p-6">
                <dt class="text-sm font-medium text-gray-500">Phone</dt>
                <dd class="mt-1 text-lg"><a href="tel:{{ preg_replace('/[^\d+]/', '', $site['contact_phone']) }}" class="text-sky-800 hover:underline">{{ $site['contact_phone'] }}</a></dd>
            </div>
            <div class="rounded-lg border border-gray-100 p-6">
                <dt class="text-sm font-medium text-gray-500">Email</dt>
                <dd class="mt-1 text-lg"><a href="mailto:{{ $site['contact_email'] }}" class="text-sky-800 hover:underline">{{ $site['contact_email'] }}</a></dd>
            </div>
            <div class="rounded-lg border border-gray-100 p-6">
                <dt class="text-sm font-medium text-gray-500">Address</dt>
                <dd class="mt-1 whitespace-pre-line text-lg">{{ $site['contact_address'] }}</dd>
            </div>
            <div class="rounded-lg border border-gray-100 p-6">
                <dt class="text-sm font-medium text-gray-500">Opening hours</dt>
                <dd class="mt-1 whitespace-pre-line text-lg">{{ $site['contact_hours'] }}</dd>
            </div>
            <div class="rounded-lg border border-gray-100 p-6 sm:col-span-2">
                <dt class="text-sm font-medium text-gray-500">Delivery and collection</dt>
                <dd class="mt-1 text-gray-700">{{ $site['delivery_areas'] }} Delivery costs {{ \App\Support\Money::format((int) $site['delivery_fee_pesewas']) }}. You can also collect your order free from {{ $site['collection_point'] }}.</dd>
            </div>
        </dl>
    </div>
@endsection
