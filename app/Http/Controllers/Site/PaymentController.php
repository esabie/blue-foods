<?php

namespace App\Http\Controllers\Site;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\OnlineOrder;
use App\Models\Payment;
use App\Services\Payments\InvalidWebhook;
use App\Services\Payments\PaymentGateway;
use App\Services\Shop\OnlineShop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private readonly OnlineShop $shop, private readonly PaymentGateway $gateway) {}

    /**
     * Order status page; the token in the link stands in for an account.
     */
    public function order(Request $request, OnlineOrder $order): View
    {
        abort_unless(hash_equals($order->access_token, (string) $request->query('token')), 404);
        $order->load(['items.product', 'statusChanges']);

        return view('site.order', ['order' => $order]);
    }

    public function pay(Request $request, OnlineOrder $order): RedirectResponse
    {
        abort_unless(hash_equals($order->access_token, (string) $request->input('token')), 404);

        $payment = $this->shop->startPayment($order, $this->gateway->name());

        return redirect()->away($this->gateway->checkoutUrl($payment));
    }

    /**
     * The customer comes back from the provider. We confirm with the provider server-side;
     * the redirect itself proves nothing.
     */
    public function returned(Payment $payment): RedirectResponse
    {
        if ($payment->status === PaymentStatus::Pending && ($confirmed = $this->gateway->verify($payment))) {
            $this->shop->confirmPayment($payment, $confirmed->providerRef, $confirmed->amountPesewas);
        }

        $order = $payment->order->fresh();

        return redirect()->to($order->statusUrl())->with('status', $order->isPaid()
            ? 'Payment received. Thank you!'
            : 'We have not received confirmation of your payment yet. This page will update once it arrives.');
    }

    /**
     * W5: the provider's confirmed callback is what marks an order paid.
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            $confirmed = $this->gateway->parseWebhook($request);
        } catch (InvalidWebhook $e) {
            Log::warning('Rejected payment webhook', ['reason' => $e->getMessage(), 'ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        if ($confirmed !== null && ($payment = Payment::where('reference', $confirmed->reference)->first())) {
            $this->shop->confirmPayment($payment, $confirmed->providerRef, $confirmed->amountPesewas);
        }

        return response()->json(['received' => true]);
    }

    public function sandbox(Payment $payment): View
    {
        $this->ensureSandbox();

        return view('site.sandbox-pay', ['payment' => $payment->load('order')]);
    }

    /**
     * Plays the provider's confirmed callback for local testing.
     */
    public function sandboxComplete(Request $request, Payment $payment): RedirectResponse
    {
        $this->ensureSandbox();

        if ($request->input('outcome') === 'success') {
            $this->shop->confirmPayment($payment, 'SANDBOX-'.$payment->id, $payment->amount_pesewas);
        } else {
            $this->shop->failPayment($payment, 'Declined in sandbox');
        }

        return redirect()->route('shop.payments.return', $payment->reference);
    }

    private function ensureSandbox(): void
    {
        abort_unless($this->gateway->name() === 'sandbox' && ! app()->isProduction(), 404);
    }
}
