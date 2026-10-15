<?php

namespace App\Services\Shop;

use App\Models\Product;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * The customer's cart, kept in the session: product id => quantity.
 */
class Cart
{
    private const KEY = 'cart';

    public function __construct(private readonly Session $session, private readonly OnlineShop $shop) {}

    /**
     * @return array<int, int>
     */
    public function raw(): array
    {
        return $this->session->get(self::KEY, []);
    }

    public function set(int $productId, int $quantity): void
    {
        $cart = $this->raw();

        if ($quantity <= 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = $quantity;
        }

        $this->session->put(self::KEY, $cart);
    }

    public function add(int $productId, int $quantity): void
    {
        $this->set($productId, ($this->raw()[$productId] ?? 0) + $quantity);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    public function count(): int
    {
        return array_sum($this->raw());
    }

    /**
     * Cart lines with current price and availability; retired products drop out.
     *
     * @return Collection<int, array{product: Product, quantity: int, available: int, unit_price_pesewas: int, total_pesewas: int}>
     */
    public function lines(): Collection
    {
        $raw = $this->raw();
        $products = Product::sellable()->findMany(array_keys($raw));
        $available = $this->shop->availability($products->pluck('id')->all());

        return $products->map(fn (Product $p) => [
            'product' => $p,
            'quantity' => $raw[$p->id],
            'available' => $available[$p->id] ?? 0,
            'unit_price_pesewas' => $p->currentPrice->unit_price_pesewas,
            'total_pesewas' => $raw[$p->id] * $p->currentPrice->unit_price_pesewas,
        ])->values();
    }

    public function subtotal(): int
    {
        return $this->lines()->sum('total_pesewas');
    }
}
