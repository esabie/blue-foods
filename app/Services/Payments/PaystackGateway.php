<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Paystack (mobile money and card in GHS). Amounts are sent in pesewas, Paystack's subunit.
 */
class PaystackGateway implements PaymentGateway
{
    public function __construct(private readonly string $secretKey, private readonly string $baseUrl) {}

    public function name(): string
    {
        return 'paystack';
    }

    public function checkoutUrl(Payment $payment): string
    {
        $order = $payment->order;

        $response = $this->http()->post('/transaction/initialize', [
            'email' => preg_replace('/\D/', '', $order->phone).'@'.config('shop.customer_email_domain'),
            'amount' => $payment->amount_pesewas,
            'currency' => 'GHS',
            'reference' => $payment->reference,
            'callback_url' => route('shop.payments.return', $payment->reference),
            'channels' => [$payment->method === PaymentMethod::Card ? 'card' : 'mobile_money'],
            'metadata' => ['order' => $order->reference, 'customer' => $order->customer_name, 'phone' => $order->phone],
        ]);

        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException('Paystack could not start the payment: '.$response->json('message', 'unknown error'));
        }

        return $response->json('data.authorization_url');
    }

    public function verify(Payment $payment): ?ConfirmedPayment
    {
        $response = $this->http()->get('/transaction/verify/'.rawurlencode($payment->reference));

        if (! $response->successful() || $response->json('data.status') !== 'success' || $response->json('data.currency') !== 'GHS') {
            return null;
        }

        return new ConfirmedPayment($payment->reference, (string) $response->json('data.id'), (int) $response->json('data.amount'));
    }

    public function parseWebhook(Request $request): ?ConfirmedPayment
    {
        if ($this->secretKey === '') {
            throw new InvalidWebhook('Paystack secret key is not configured.');
        }

        $signature = (string) $request->header('x-paystack-signature');
        $expected = hash_hmac('sha512', $request->getContent(), $this->secretKey);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            throw new InvalidWebhook('Invalid Paystack signature.');
        }

        if ($request->input('event') !== 'charge.success' || $request->input('data.status') !== 'success' || $request->input('data.currency') !== 'GHS') {
            return null;
        }

        return new ConfirmedPayment(
            (string) $request->input('data.reference'),
            (string) $request->input('data.id'),
            (int) $request->input('data.amount'),
        );
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)->withToken($this->secretKey)->acceptJson()->timeout(15);
    }
}
