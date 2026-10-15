@extends('site.layout')

@section('title', $site['about_title'])

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <div @class(['grid gap-10', 'md:grid-cols-2' => $site['about_image']])>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $site['about_title'] }}</h1>
                <div class="prose-site mt-6 text-lg leading-relaxed text-gray-700">
                    @foreach (preg_split('/\n\s*\n/', (string) $site['about_body']) as $para)
                        <p>{!! nl2br(e($para)) !!}</p>
                    @endforeach
                </div>
            </div>
            @if ($site['about_image'])
                <img src="{{ Storage::disk('public')->url($site['about_image']) }}" alt="{{ $site['about_title'] }}" class="w-full rounded-lg object-cover">
            @endif
        </div>
    </div>
@endsection
