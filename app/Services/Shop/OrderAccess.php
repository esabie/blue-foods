<?php

namespace App\Services\Shop;

use App\Models\OnlineOrder;
use Illuminate\Http\Request;

/**
 * Customer order pages use the session instead of putting access_token in the URL.
 * A one-time ?token= query is still accepted so old links can be redeemed, then
 * the browser is redirected to a clean path.
 */
class OrderAccess
{
    private const SESSION_KEY = 'shop.order_access';

    public function grant(OnlineOrder $order): void
    {
        $allowed = session(self::SESSION_KEY, []);
        $allowed[$order->id] = $order->access_token;
        session([self::SESSION_KEY => $allowed]);
    }

    public function allows(OnlineOrder $order): bool
    {
        $token = session(self::SESSION_KEY, [])[$order->id] ?? null;

        return is_string($token) && hash_equals($order->access_token, $token);
    }

    /**
     * Accept a legacy or SMS token once, then use the session.
     */
    public function authorize(Request $request, OnlineOrder $order): bool
    {
        if ($this->allows($order)) {
            return true;
        }

        $token = (string) ($request->query('token') ?: $request->input('token'));
        if ($token !== '' && hash_equals($order->access_token, $token)) {
            $this->grant($order);

            return true;
        }

        return false;
    }
}
