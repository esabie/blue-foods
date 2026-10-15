<?php

namespace App\Services;

use App\Enums\LocationType;
use App\Enums\MovementType;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-side figures derived from the ledger (PRD S3 and section 6.1).
 */
class StockPosition
{
    /**
     * One row per product: where every unit is, and whether rule B6 holds.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function byProduct(?Collection $products = null): Collection
    {
        $products ??= Product::with('currentPrice')->orderBy('name')->get();

        $balances = DB::table('stock_balances as b')
            ->join('locations as l', 'l.id', '=', 'b.location_id')
            ->select('b.product_id', 'l.type', DB::raw('SUM(b.quantity) as quantity'))
            ->groupBy('b.product_id', 'l.type')
            ->get()
            ->groupBy('product_id');

        $totals = DB::table('stock_movements')
            ->select('product_id', 'type', DB::raw('SUM(quantity) as quantity'))
            ->groupBy('product_id', 'type')
            ->get()
            ->groupBy('product_id');

        return $products->map(function (Product $product) use ($balances, $totals) {
            $balance = fn (LocationType $type) => (int) ($balances->get($product->id)?->firstWhere('type', $type->value)?->quantity ?? 0);
            $total = fn (MovementType $type) => (int) ($totals->get($product->id)?->firstWhere('type', $type->value)?->quantity ?? 0);

            $unassigned = $balance(LocationType::Warehouse);
            $withTeam = $balance(LocationType::Member);
            $online = $balance(LocationType::OnlineShop);
            $sold = $total(MovementType::Sold) + $total(MovementType::SoldOnline) - $total(MovementType::SaleReversed);
            $produced = $total(MovementType::Produced);
            $adjusted = $total(MovementType::AdjustedIn) - $total(MovementType::AdjustedOut);
            $remaining = $unassigned + $withTeam + $online;
            $price = $product->currentPrice?->unit_price_pesewas ?? 0;

            return [
                'product_id' => $product->id,
                'produced' => $produced,
                'adjusted' => $adjusted,
                'unassigned' => $unassigned,
                'with_team' => $withTeam,
                'online' => $online,
                'sold' => $sold,
                'remaining' => $remaining,
                'value_remaining_pesewas' => $remaining * $price,
                'low_stock' => $product->reorder_level > 0 && $unassigned < $product->reorder_level,
                'reconciles' => $produced + $adjusted === $remaining + $sold,
            ];
        })->values();
    }

    /**
     * Products held at a location, with a positive balance.
     *
     * @return Collection<int, object>
     */
    public function holdings(int $locationId): Collection
    {
        return DB::table('stock_balances as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->where('b.location_id', $locationId)
            ->where('b.quantity', '>', 0)
            ->orderBy('p.name')
            ->get(['p.id as product_id', 'p.name', 'p.sku', 'p.unit', 'b.quantity']);
    }

    /**
     * Rule B1 check: any location with a negative balance.
     *
     * @return Collection<int, object>
     */
    public function negativeBalances(): Collection
    {
        return DB::table('stock_balances')->where('quantity', '<', 0)->get();
    }
}
