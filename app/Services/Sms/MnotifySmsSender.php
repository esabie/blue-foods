<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends SMS via mNotify (BMS) quick SMS API.
 *
 * @see https://readthedocs.mnotify.com/
 */
class MnotifySmsSender implements SmsSender
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $sender,
        private readonly string $baseUrl,
    ) {}

    public function send(string $to, string $message): void
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('mNotify API key is not configured (MNOTIFY_API_KEY).');
        }

        if ($this->sender === '') {
            throw new RuntimeException('mNotify sender ID is not configured (MNOTIFY_SENDER).');
        }

        $recipient = $this->localGhanaNumber($to);
        $endpoint = rtrim($this->baseUrl, '/').'/sms/quick';
        $request = [
            'recipient' => [$recipient],
            'sender' => $this->sender,
            'message' => $message,
            'is_schedule' => false,
            'schedule_date' => '',
        ];

        $response = Http::asJson()
            ->acceptJson()
            ->post($endpoint.'?key='.urlencode($this->apiKey), $request);

        $context = [
            'to' => $to,
            'endpoint' => $endpoint,
            'http_status' => $response->status(),
            'request' => $request,
            'response' => $response->json() ?? ['raw' => $response->body()],
        ];

        if (! $response->successful() || $response->json('status') !== 'success') {
            Log::warning('mNotify SMS failed', $context);

            throw new RuntimeException(
                'mNotify could not send SMS: '.$response->json('message', $response->body())
            );
        }

        Log::info('mNotify SMS sent', $context);
    }

    /**
     * mNotify examples use local Ghana numbers (024…), not E.164.
     */
    private function localGhanaNumber(string $to): string
    {
        $digits = preg_replace('/\D/', '', $to) ?? '';

        if (str_starts_with($digits, '233') && strlen($digits) >= 12) {
            return '0'.substr($digits, 3);
        }

        return $digits;
    }
}
