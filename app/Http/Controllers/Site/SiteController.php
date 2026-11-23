<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Shop\OnlineShop;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function home(OnlineShop $shop): View
    {
        $products = Product::sellable()->orderBy('name')->limit(8)->get();

        return view('site.home', [
            'products' => $products,
            'availability' => $shop->availability($products->pluck('id')->all()),
        ]);
    }

    public function about(): View
    {
        return view('site.about');
    }

    public function contact(): View
    {
        return view('site.contact');
    }

    public function privacy(): View
    {
        return view('site.privacy');
    }

    public function terms(): View
    {
        return view('site.terms');
    }

    public function faq(): View
    {
        return view('site.faq');
    }

    /**
     * W12: sitemap for search engines.
     */
    public function sitemap(): Response
    {
        $urls = collect([
            route('site.home'),
            route('shop.index'),
            route('shop.track'),
            route('site.about'),
            route('site.contact'),
            route('site.privacy'),
            route('site.terms'),
            route('site.faq'),
        ])->merge(Product::sellable()->orderBy('name')->get()->map(fn (Product $p) => route('shop.show', $p->sku)));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".view('site.sitemap', ['urls' => $urls])->render();

        return response($xml)->header('Content-Type', 'application/xml');
    }
}
