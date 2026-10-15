<?php

namespace App\Services\Payments;

final class ConfirmedPayment
{
    public function __construct(
        public readonly string $reference,
        public readonly string $providerRef,
        public readonly int $amountPesewas,
    ) {}
}
