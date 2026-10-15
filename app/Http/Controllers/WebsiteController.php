<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * W1: management edits website text and photos, and shop settings (Q5), without a developer.
 */
class WebsiteController extends Controller
{
    private const TEXT_FIELDS = [
        'home_title', 'home_intro', 'about_title', 'about_body', 'contact_phone', 'contact_email',
        'contact_address', 'contact_hours', 'delivery_areas', 'collection_point', 'seo_description',
    ];

    private const IMAGE_FIELDS = ['home_image', 'about_image'];

    public function edit(): Response
    {
        $values = SiteSetting::values();

        return Inertia::render('Website/Edit', [
            'values' => [
                ...collect($values)->only(self::TEXT_FIELDS)->all(),
                'delivery_fee' => Money::toCedis((int) $values['delivery_fee_pesewas']),
            ],
            'images' => collect(self::IMAGE_FIELDS)->mapWithKeys(fn ($key) => [
                $key => $values[$key] ? Storage::disk('public')->url($values[$key]) : null,
            ]),
            'siteUrl' => route('site.home'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            ...collect(self::TEXT_FIELDS)->mapWithKeys(fn ($key) => [$key => ['nullable', 'string', 'max:5000']])->all(),
            'delivery_fee' => ['required', 'string', 'regex:'.Money::PATTERN],
            ...collect(self::IMAGE_FIELDS)->mapWithKeys(fn ($key) => [$key => ['nullable', 'image', 'max:4096']])->all(),
        ]);

        $values = collect($data)->only(self::TEXT_FIELDS)->all();
        $values['delivery_fee_pesewas'] = (string) Money::toPesewas($data['delivery_fee']);

        foreach (self::IMAGE_FIELDS as $key) {
            if ($request->hasFile($key)) {
                $values[$key] = $request->file($key)->store('site', 'public');
            }
        }

        SiteSetting::put($values);

        return back()->with('success', 'Website updated.');
    }
}
