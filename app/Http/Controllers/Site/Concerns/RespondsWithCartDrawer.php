<?php

namespace App\Http\Controllers\Site\Concerns;

use App\Services\Shop\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait RespondsWithCartDrawer
{
    protected function wantsCartJson(Request $request): bool
    {
        return $request->wantsJson() || $request->ajax();
    }

    protected function cartDrawerJson(Cart $cart, ?string $status = null): JsonResponse
    {
        return response()->json([
            'count' => $cart->count(),
            'html' => view('site.partials.cart-drawer')->render(),
            'status' => $status,
        ]);
    }
}
