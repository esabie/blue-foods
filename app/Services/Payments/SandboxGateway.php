<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Local stand-in for a provider until Q4 is answered. Its checkout page plays the
 * provider's role and confirms through the same OnlineShop::confirmPayment path.
 * Never available in production.
 */
class SandboxGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'sandbox';
    }

    public function checkoutUrl(Payment $payment): string
    {
        return route('shop.sandbox.show', $payment->reference);
    }

    public function verify(Payment $payment): ?ConfirmedPayment
    {
        return null;
    }

    public function parseWebhook(Request $request): ?ConfirmedPayment
    {
        throw new InvalidWebhook('The sandbox provider does not send webhooks.');
    }
}
