<?php

namespace App\Jobs;

use App\Services\Sms\SmsSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * W7: order and payment confirmations, sent within a minute by the queue worker.
 */
class SendSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public readonly string $to, public readonly string $message) {}

    public function handle(SmsSender $sms): void
    {
        $sms->send($this->to, $this->message);
    }
}
