<?php

namespace App\Http\Controllers\Site;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\Concerns\RespondsWithCartDrawer;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\Payments\PaymentGateway;
use App\Services\Shop\Cart;
use App\Services\Shop\OnlineShop;
use App\Services\Shop\OrderAccess;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    use RespondsWithCartDrawer;

    public function __construct(
        private readonly Cart $cart,
        private readonly OnlineShop $shop,
        private readonly OrderAccess $access,
    ) {}

    public function cart(): View
    {
        return view('site.cart', ['lines' => $this->cart->lines(), 'subtotal' => $this->cart->subtotal()]);
    }

    public function updateCart(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:1000']]);
        $available = $this->shop->availability([$product->id])[$product->id];

        if ($data['quantity'] > $available) {
            $message = "Only {$available} {$product->unit} of {$product->name} available.";

            if ($this->wantsCartJson($request)) {
                return response()->json(['message' => $message, 'errors' => ['quantity' => [$message]]], 422);
            }

            return back()->withErrors(['quantity' => $message]);
        }

        $this->cart->set($product->id, $data['quantity']);

        if ($this->wantsCartJson($request)) {
            return $this->cartDrawerJson($this->cart);
        }

        return back()->with('open_cart', true);
    }

    public function clearCart(Request $request): RedirectResponse|JsonResponse
    {
        $this->cart->clear();

        if ($this->wantsCartJson($request)) {
            return $this->cartDrawerJson($this->cart, 'Cart cleared.');
        }

        return back()->with('open_cart', true)->with('status', 'Cart cleared.');
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
        $this->access->grant($order);

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
