<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Shop\Cart;
use App\Services\Shop\OnlineShop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function __construct(private readonly OnlineShop $shop) {}

    /**
     * W2: active products with photo, price and availability from the online shop balance.
     */
    public function index(Request $request): View
    {
        $category = $request->string('category')->toString() ?: null;

        $products = Product::sellable()
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderBy('name')
            ->get();

        return view('site.shop', [
            'products' => $products,
            'availability' => $this->shop->availability($products->pluck('id')->all()),
            'categories' => Product::sellable()->distinct()->orderBy('category')->pluck('category'),
            'category' => $category,
        ]);
    }

    public function show(Product $product): View
    {
        $product->load('currentPrice');
        abort_unless($product->isActive() && $product->currentPrice, 404);

        return view('site.product', [
            'product' => $product,
            'available' => $this->shop->availability([$product->id])[$product->id],
        ]);
    }

    /**
     * W3: cannot add more than the online shop balance.
     */
    public function addToCart(Request $request, Cart $cart): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $product = Product::sellable()->findOrFail($data['product_id']);
        $available = $this->shop->availability([$product->id])[$product->id];
        $inCart = $cart->raw()[$product->id] ?? 0;

        if ($inCart + $data['quantity'] > $available) {
            return back()->withErrors(['quantity' => $available === 0
                ? "{$product->name} is out of stock."
                : "Only {$available} {$product->unit} of {$product->name} available".($inCart ? " ({$inCart} already in your cart)." : '.')]);
        }

        $cart->add($product->id, $data['quantity']);

        return redirect()->route('cart.show')->with('status', "Added {$data['quantity']} × {$product->name} to your cart.");
    }
}
