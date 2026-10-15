<?php

namespace App\Services\Sms;

interface SmsSender
{
    public function send(string $to, string $message): void;
}
