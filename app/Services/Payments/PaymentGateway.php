<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * A Ghana payment provider (open question Q4). Orders are marked paid only from
 * details the provider itself confirms: a signed webhook or a server-side verify call.
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Where to send the customer to pay.
     */
    public function checkoutUrl(Payment $payment): string;

    /**
     * Ask the provider whether this payment succeeded.
     */
    public function verify(Payment $payment): ?ConfirmedPayment;

    /**
     * Parse a provider callback. Returns null for events that are not a confirmed payment.
     *
     * @throws InvalidWebhook when the callback cannot be trusted
     */
    public function parseWebhook(Request $request): ?ConfirmedPayment;
}
