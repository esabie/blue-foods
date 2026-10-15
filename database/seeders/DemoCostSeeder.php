<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo unit costs: one from before the sales history starts, and a cost rise four months ago on
 * most products so the cost history and a dip in margin show. Does nothing once any cost exists.
 */
class DemoCostSeeder extends Seeder
{
    /** SKU => [first cost, cost from four months ago], in pesewas. */
    private const COSTS = [
        'BF-TIL-500' => [1950, 2150],
        'BF-MAC-1KG' => [2600, 2750],
        'BF-SHR-250' => [1400, 1400],
        'BF-SAR-TRY' => [3900, 4200],
    ];

    public function run(): void
    {
        if (ProductCost::exists()) {
            return;
        }

        $manager = User::where('role', Role::Management)->orderBy('id')->first();
        $firstMovement = DB::table('stock_movements')->min('occurred_at');
        $start = ($firstMovement ? CarbonImmutable::parse($firstMovement) : CarbonImmutable::now())->subDay()->startOfDay();
        $rise = CarbonImmutable::now()->subMonths(4)->startOfDay();

        foreach (Product::whereIn('sku', array_keys(self::COSTS))->get() as $product) {
            [$first, $later] = self::COSTS[$product->sku];

            $product->costs()->create(['unit_cost_pesewas' => $first, 'effective_from' => $start, 'set_by' => $manager?->id]);

            if ($later !== $first && $rise->greaterThan($start)) {
                $product->costs()->create(['unit_cost_pesewas' => $later, 'effective_from' => $rise, 'set_by' => $manager?->id]);
            }
        }
    }
}
