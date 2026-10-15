<?php

namespace Database\Seeders;

use App\Enums\AdjustmentReason;
use App\Enums\Role;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockLedger;
use Illuminate\Database\Seeder;

/**
 * Demo data for local development, including twelve months of history (DemoHistorySeeder).
 * All accounts use the password "password".
 */
class DatabaseSeeder extends Seeder
{
    private const DESCRIPTIONS = [
        'BF-TIL-500' => "Whole tilapia, cleaned and slow-smoked over hardwood until golden.\n\nReady to add to soups and stews, or eat with kenkey and pepper. Keeps for two weeks in a cool, dry place.",
        'BF-MAC-1KG' => "Atlantic mackerel, frozen within hours of landing to lock in freshness.\n\nKeep frozen. Thaw in the fridge overnight before cooking.",
        'BF-SHR-250' => "Sun-dried shrimp with a rich, savoury flavour.\n\nGrind into shito, light soup or palava sauce. Store in an airtight container.",
        'BF-SAR-TRY' => "A tray of fresh sardines from the morning catch.\n\nKeep chilled and cook within a day. Perfect fried or grilled.",
    ];

    public function run(StockLedger $ledger): void
    {
        $manager = $this->user('Micky Kwachie', 'manager@bluefoods.test', '+233200000001', Role::Management);
        $warehouse = $this->user('Warehouse Desk', 'warehouse@bluefoods.test', '+233200000002', Role::Warehouse);
        $ama = $this->user('Ama Owusu', 'ama@bluefoods.test', '+233200000003', Role::Marketing);
        $kofi = $this->user('Kofi Mensah', 'kofi@bluefoods.test', '+233200000004', Role::Marketing);

        $products = collect([
            ['BF-TIL-500', 'Smoked tilapia 500g', 'Smoked fish', 'pack', 30, 3550],
            ['BF-MAC-1KG', 'Frozen mackerel 1kg', 'Frozen fish', 'pack', 40, 4200],
            ['BF-SHR-250', 'Dried shrimp 250g', 'Seafood', 'pack', 25, 2800],
            ['BF-SAR-TRY', 'Fresh sardines tray', 'Fresh fish', 'tray', 15, 6000],
        ])->map(function (array $row) use ($manager) {
            [$sku, $name, $category, $unit, $reorder, $price] = $row;

            $product = Product::firstOrCreate(
                ['sku' => $sku],
                ['name' => $name, 'category' => $category, 'unit' => $unit, 'reorder_level' => $reorder],
            );

            if (! $product->description) {
                $product->update(['description' => self::DESCRIPTIONS[$sku]]);
            }

            if (! $product->prices()->exists()) {
                $product->prices()->create(['unit_price_pesewas' => $price, 'effective_from' => now(), 'set_by' => $manager->id]);
            }

            return $product;
        });

        if (StockMovement::exists()) {
            $this->call(DemoCostSeeder::class);

            return;
        }

        $this->call(DemoHistorySeeder::class);
        $this->call(DemoCostSeeder::class);

        [$tilapia, $mackerel, $shrimp, $sardines] = $products->all();

        $ledger->produce($tilapia, 200, $warehouse, now()->subDays(3));
        $ledger->produce($mackerel, 150, $warehouse, now()->subDays(2));
        $ledger->produce($shrimp, 60, $warehouse, now()->subDay());
        $ledger->produce($sardines, 20, $warehouse);
        $ledger->adjust($mackerel, 4, false, AdjustmentReason::Damaged, 'Freezer door left open', $warehouse);

        $ledger->assign($tilapia, $ama, 40, $manager);
        $ledger->assign($mackerel, $ama, 25, $manager);
        $ledger->assign($tilapia, $kofi, 30, $manager);
        $ledger->assign($shrimp, $kofi, 20, $manager);
        $ledger->allocateOnline($tilapia, 50, $manager);
        $ledger->allocateOnline($sardines, 8, $manager);

        $ledger->recordSale($ama, $tilapia, 6, 'Auntie Esi, Madina market');
        $ledger->recordSale($ama, $mackerel, 3);
        $mistake = $ledger->recordSale($kofi, $tilapia, 5);
        $ledger->recordSale($kofi, $shrimp, 4, '024 111 2222');
        $ledger->reverseSale($mistake, 'Recorded against the wrong product', $manager);

        $ledger->requestReturn($kofi, $shrimp, 6);

        $this->call(DemoCashSeeder::class);
    }

    private function user(string $name, string $email, string $phone, Role $role): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'phone' => $phone, 'role' => $role, 'password' => 'password'],
        );
    }
}
