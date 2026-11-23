<?php

use App\Services\Sms\MnotifySmsSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

test('mNotify sends a quick SMS with the configured sender and a local Ghana number', function () {
    Log::spy();

    Http::fake([
        'api.mnotify.com/api/sms/quick*' => Http::response([
            'status' => 'success',
            'code' => '2000',
            'message' => 'messages sent successfully',
        ]),
    ]);

    $sender = new MnotifySmsSender('test-key', 'BLUE FOODS', 'https://api.mnotify.com/api');
    $sender->send('+233241234567', 'Your Blue Foods order BF123 is confirmed.');

    Http::assertSent(function ($request) {
        expect($request->url())->toContain('key=test-key')
            ->and($request['recipient'])->toBe(['0241234567'])
            ->and($request['sender'])->toBe('BLUE FOODS')
            ->and($request['message'])->toBe('Your Blue Foods order BF123 is confirmed.');

        return true;
    });

    Log::shouldHaveReceived('info')->withArgs(fn ($message, $context) => $message === 'mNotify SMS sent'
        && ($context['request']['recipient'][0] ?? null) === '0241234567'
        && ($context['request']['sender'] ?? null) === 'BLUE FOODS'
        && ($context['response']['status'] ?? null) === 'success');
});

test('mNotify throws when the API rejects the message', function () {
    Log::spy();

    Http::fake([
        'api.mnotify.com/api/sms/quick*' => Http::response([
            'status' => 'error',
            'message' => 'Insufficient credit',
        ], 200),
    ]);

    $sender = new MnotifySmsSender('test-key', 'BLUE FOODS', 'https://api.mnotify.com/api');

    expect(fn () => $sender->send('+233241234567', 'Hello'))
        ->toThrow(RuntimeException::class, 'Insufficient credit');

    Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => $message === 'mNotify SMS failed'
        && isset($context['request'], $context['response'])
        && ($context['response']['message'] ?? null) === 'Insufficient credit');
});

test('mNotify refuses to send without an API key', function () {
    $sender = new MnotifySmsSender('', 'BLUE FOODS', 'https://api.mnotify.com/api');

    expect(fn () => $sender->send('+233241234567', 'Hello'))
        ->toThrow(RuntimeException::class, 'MNOTIFY_API_KEY');
});
