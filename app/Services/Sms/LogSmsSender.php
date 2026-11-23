<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Writes messages to the log (local / testing). Use SMS_DRIVER=mnotify in production.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        Log::info('SMS', ['to' => $to, 'message' => $message]);
    }
}
