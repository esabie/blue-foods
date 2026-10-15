<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\ProductPrice;
use App\Services\StockPosition;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(StockPosition $position): Response
    {
        $products = Product::with(['prices.setter:id,name', 'costs.setter:id,name', 'currentPrice'])
            ->orderBy('status')
            ->orderBy('name')
            ->get();
        $stock = $position->byProduct($products)->keyBy('product_id');
        $sold30 = DB::table('effective_sales')
            ->where('occurred_at', '>=', now()->subDays(30))
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) AS units')
            ->pluck('units', 'product_id');

        $products = $products
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'category' => $product->category,
                'unit' => $product->unit,
                'description' => $product->description,
                'photo_url' => $product->photoUrl(),
                'reorder_level' => $product->reorder_level,
                'status' => $product->status->value,
                'created_at' => $product->created_at?->toIso8601String(),
                'price_pesewas' => $product->prices->first(fn ($p) => $p->effective_from->lte(now()))?->unit_price_pesewas,
                'price_history' => $product->prices->map(fn (ProductPrice $price) => [
                    'id' => $price->id,
                    'unit_price_pesewas' => $price->unit_price_pesewas,
                    'effective_from' => $price->effective_from->toIso8601String(),
                    'set_by' => $price->setter?->name,
                ]),
                'cost_pesewas' => $product->costs->first(fn ($c) => $c->effective_from->lte(now()))?->unit_cost_pesewas,
                'cost_history' => $product->costs->map(fn (ProductCost $cost) => [
                    'id' => $cost->id,
                    'unit_cost_pesewas' => $cost->unit_cost_pesewas,
                    'effective_from' => $cost->effective_from->toIso8601String(),
                    'set_by' => $cost->setter?->name,
                    'set_at' => $cost->created_at?->toIso8601String(),
                ]),
                'stock' => [
                    ...collect($stock[$product->id])->only(['unassigned', 'with_team', 'online', 'remaining', 'low_stock'])->all(),
                    'sold_30_days' => (int) ($sold30[$product->id] ?? 0),
                ],
            ]);

        return Inertia::render('Products/Index', ['products' => $products]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            ...$this->detailRules(),
            'sku' => ['required', 'string', 'max:50', 'unique:products,sku'],
            'price' => ['required', 'string', 'regex:'.Money::PATTERN],
            'cost' => ['nullable', 'string', 'regex:'.Money::PATTERN],
            'photo' => ['required', 'image', 'max:4096'],
        ]);

        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::create([...collect($data)->except(['price', 'cost', 'photo'])->all(), 'status' => ProductStatus::Active]);

            $product->prices()->create([
                'unit_price_pesewas' => Money::toPesewas($data['price']),
                'effective_from' => now(),
                'set_by' => $request->user()->id,
            ]);

            if (filled($data['cost'] ?? null)) {
                $product->costs()->create([
                    'unit_cost_pesewas' => Money::toPesewas($data['cost']),
                    'effective_from' => now(),
                    'set_by' => $request->user()->id,
                ]);
            }

            return $product;
        });

        $product->update(['photo_path' => $request->file('photo')->store('products', 'public')]);

        return back()->with('success', "Product {$data['name']} created.");
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            ...$this->detailRules(),
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($product->id)],
        ]);

        $product->update($data);

        return back()->with('success', "Product {$product->name} updated.");
    }

    /**
     * P2: a new price applies to new assignments and sales only; history is kept.
     */
    public function changePrice(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'price' => ['required', 'string', 'regex:'.Money::PATTERN],
        ]);

        $pesewas = Money::toPesewas($data['price']);

        if ($product->currentPrice?->unit_price_pesewas !== $pesewas) {
            $product->prices()->create([
                'unit_price_pesewas' => $pesewas,
                'effective_from' => now(),
                'set_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', "Price for {$product->name} is now ".Money::format($pesewas).'.');
    }

    /**
     * A new cost per unit. Unlike a price it may apply from an earlier date, so the first cost
     * entered can cover past sales; sales are costed at the cost in force when they were made.
     */
    public function changeCost(Request $request, Product $product): RedirectResponse
    {
        $tz = config('inventory.display_timezone');
        $data = $request->validate([
            'cost' => ['required', 'string', 'regex:'.Money::PATTERN],
            'effective_from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now($tz)->toDateString()],
        ], [
            'effective_from.before_or_equal' => 'A cost cannot start in the future.',
        ]);

        $pesewas = Money::toPesewas($data['cost']);
        $from = filled($data['effective_from'] ?? null) && $data['effective_from'] !== now($tz)->toDateString()
            ? CarbonImmutable::parse($data['effective_from'], $tz)->startOfDay()->utc()
            : now();

        $product->costs()->create([
            'unit_cost_pesewas' => $pesewas,
            'effective_from' => $from,
            'set_by' => $request->user()->id,
        ]);

        return back()->with('success', "Cost for {$product->name} is now ".Money::format($pesewas).' from '
            .CarbonImmutable::parse($from)->setTimezone($tz)->format('j M Y').'.');
    }

    /**
     * W1/W3: product photo for the website, replaceable without a developer.
     */
    public function photo(Request $request, Product $product): RedirectResponse
    {
        $request->validate(['photo' => ['required', 'image', 'max:4096']]);

        $old = $product->photo_path;
        $product->update(['photo_path' => $request->file('photo')->store('products', 'public')]);

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return back()->with('success', "Photo updated for {$product->name}.");
    }

    public function retire(Product $product): RedirectResponse
    {
        $product->update(['status' => ProductStatus::Retired]);

        return back()->with('success', "{$product->name} retired.");
    }

    public function reactivate(Product $product): RedirectResponse
    {
        $product->update(['status' => ProductStatus::Active]);

        return back()->with('success', "{$product->name} reactivated.");
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function detailRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:5000'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:10000000'],
        ];
    }
}
