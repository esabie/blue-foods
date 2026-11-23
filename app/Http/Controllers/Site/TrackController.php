<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\OnlineOrder;
use App\Services\Shop\OrderAccess;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackController extends Controller
{
    public function __construct(private readonly OrderAccess $access) {}

    public function show(): View
    {
        return view('site.track');
    }

    /**
     * Reference + phone stands in for a login; wrong pairs get a generic error
     * so order numbers cannot be probed for free.
     */
    public function lookup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:32'],
            'phone' => ['required', 'string', 'max:30', fn ($a, $v, $f) => Phone::looksLikePhone($v) || $f('Enter a valid phone number.')],
        ]);

        $phone = Phone::normalize($data['phone']);
        $reference = strtoupper(trim($data['reference']));

        $order = OnlineOrder::query()
            ->where('reference', $reference)
            ->where('phone', $phone)
            ->first();

        if ($order === null) {
            return back()
                ->withInput()
                ->withErrors(['reference' => 'We could not find an order with that number and phone. Check both and try again.']);
        }

        $this->access->grant($order);

        return redirect()->to($order->statusUrl());
    }
}
