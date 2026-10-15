<?php

namespace App\Http\Controllers\Site;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\Payments\PaymentGateway;
use App\Services\Shop\Cart;
use App\Services\Shop\OnlineShop;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(private readonly Cart $cart, private readonly OnlineShop $shop) {}

    public function cart(): View
    {
        return view('site.cart', ['lines' => $this->cart->lines(), 'subtotal' => $this->cart->subtotal()]);
    }

    public function updateCart(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:1000']]);
        $available = $this->shop->availability([$product->id])[$product->id];

        if ($data['quantity'] > $available) {
            return back()->withErrors(['quantity' => "Only {$available} {$product->unit} of {$product->name} available."]);
        }

        $this->cart->set($product->id, $data['quantity']);

        return back();
    }

    public function show(): View|RedirectResponse
    {
        if ($this->cart->count() === 0) {
            return redirect()->route('shop.index');
        }

        return view('site.checkout', [
            'lines' => $this->cart->lines(),
            'subtotal' => $this->cart->subtotal(),
            'deliveryFee' => SiteSetting::deliveryFeePesewas(),
            'paymentMethods' => $this->paymentMethods(),
        ]);
    }

    /**
     * W4: name, phone, delivery address or collection, and payment method.
     */
    public function store(Request $request, PaymentGateway $gateway): RedirectResponse
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^\+\d{9,15}$/'],
            'delivery_method' => ['required', Rule::enum(DeliveryMethod::class)],
            'address' => ['nullable', 'required_if:delivery_method,delivery', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::in(array_map(fn ($m) => $m->value, $this->paymentMethods()))],
        ], ['phone.regex' => 'Enter a valid phone number, e.g. 024 123 4567.']);

        $order = $this->shop->placeOrder($this->cart->raw(), $data);
        $this->cart->clear();

        if (! $order->payment_method->isOnline()) {
            return redirect()->to($order->statusUrl())->with('status', 'Thank you. Your order is reserved; pay when you receive it.');
        }

        $payment = $this->shop->startPayment($order, $gateway->name());

        return redirect()->away($gateway->checkoutUrl($payment));
    }

    /**
     * @return list<PaymentMethod>
     */
    private function paymentMethods(): array
    {
        return array_values(array_filter(
            PaymentMethod::cases(),
            fn (PaymentMethod $m) => in_array($m->value, config('shop.payment_methods'), true),
        ));
    }
}
