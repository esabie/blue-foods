<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Writes messages to the log until an SMS provider is chosen.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        Log::info('SMS', ['to' => $to, 'message' => $message]);
    }
}
