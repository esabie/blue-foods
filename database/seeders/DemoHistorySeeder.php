<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Exceptions\StockRuleException;
use App\Models\Location;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Shop\OnlineShop;
use App\Services\StaffNotifier;
use App\Services\StockLedger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Twelve months of backdated demo activity so the dashboards and reports have history:
 * weekly production and assignments, daily team sales, online orders, reversals and returns.
 * Every entry goes through the ledger and shop services, so all business rules hold.
 */
class DemoHistorySeeder extends Seeder
{
    private const WEEKLY_PRODUCTION = [
        'BF-TIL-500' => 140,
        'BF-MAC-1KG' => 100,
        'BF-SHR-250' => 45,
        'BF-SAR-TRY' => 35,
    ];

    public function run(StockLedger $ledger, OnlineShop $shop, StaffNotifier $notifier): void
    {
        if (StockMovement::where('occurred_at', '<', now()->subDays(30))->exists()) {
            $this->command?->info('Demo history already present; skipping.');

            return;
        }

        try {
            $notifier->quietly(fn () => DB::transaction(fn () => $this->seed($ledger, $shop)));
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Balance at the simulated moment. The ledger checks the all-time balance, which also counts
     * any real activity dated after this history; staying within the as-of balance keeps every
     * past balance at zero or above (B1).
     */
    private function available(StockLedger $ledger, int $productId, int $locationId, ?int $memberId = null): int
    {
        $in = StockMovement::where('product_id', $productId)->where('to_location_id', $locationId)->where('occurred_at', '<=', now())->sum('quantity');
        $out = StockMovement::where('product_id', $productId)->where('from_location_id', $locationId)->where('occurred_at', '<=', now())->sum('quantity');
        $pending = $memberId ? $ledger->pendingReturnQuantity($memberId, $productId) : 0;

        return max(0, min((int) $in - (int) $out, $ledger->balance($productId, $locationId) - $pending));
    }

    private function seed(StockLedger $ledger, OnlineShop $shop): void
    {

        // SMS jobs from backdated orders should not be sent.
        config(['queue.connections.seeding' => ['driver' => 'null'], 'queue.default' => 'seeding']);
        mt_srand(2026);

        $manager = User::where('role', Role::Management)->where('email', 'manager@bluefoods.test')->first()
            ?? User::where('role', Role::Management)->human()->firstOrFail();
        $warehouse = User::where('role', Role::Warehouse)->firstOrFail();
        $members = collect([
            ['Ama Owusu', 'ama@bluefoods.test', '+233200000003'],
            ['Kofi Mensah', 'kofi@bluefoods.test', '+233200000004'],
            ['Yaa Asante', 'yaa@bluefoods.test', '+233200000005'],
            ['Kwabena Boateng', 'kwabena@bluefoods.test', '+233200000006'],
        ])->map(fn ($m) => User::firstOrCreate(['email' => $m[1]], ['name' => $m[0], 'phone' => $m[2], 'role' => Role::Marketing, 'password' => 'password']));

        $products = Product::whereIn('sku', array_keys(self::WEEKLY_PRODUCTION))->with('currentPrice')->get()->keyBy('sku');
        $warehouseId = Location::warehouse()->id;
        $onlineId = Location::onlineShop()->id;

        $start = CarbonImmutable::now()->subMonths(12)->startOfWeek();
        $end = CarbonImmutable::now()->subDays(2)->startOfDay();

        // Price history: 10% lower a year ago, today's price from six months ago.
        foreach ($products as $product) {
            $current = $product->currentPrice->unit_price_pesewas;
            $product->prices()->create(['unit_price_pesewas' => (int) (round($current * 0.9 / 50) * 50), 'effective_from' => $start->subDay(), 'set_by' => $manager->id]);
            $product->prices()->create(['unit_price_pesewas' => $current, 'effective_from' => CarbonImmutable::now()->subMonths(6), 'set_by' => $manager->id]);
        }

        // The simulated clock only moves forward, so entries are created in time order and
        // every balance check sees exactly the stock that existed at that moment.
        Carbon::setTestNow($start->setTime(6, 0));

        $sales = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $at = fn (int $hour, int $minute = 0) => Carbon::setTestNow(max($day->setTime($hour, $minute + mt_rand(0, 9)), now()->addMinute()));

            if ($day->isMonday()) {
                $at(7);
                foreach ($products as $sku => $product) {
                    $ledger->produce($product, self::WEEKLY_PRODUCTION[$sku] + mt_rand(-15, 15), $warehouse);
                }

                $at(9);
                foreach ($members as $member) {
                    foreach ($products->random(mt_rand(2, 3)) as $product) {
                        $free = $this->available($ledger, $product->id, $warehouseId) - $product->reorder_level;
                        $qty = min($free, mt_rand(12, 30));
                        if ($qty > 0) {
                            $ledger->assign($product, $member, $qty, $manager);
                        }
                    }
                }

                if ($day->day <= 7) {
                    $at(10);
                    foreach ($products as $product) {
                        $qty = min($this->available($ledger, $product->id, $warehouseId) - $product->reorder_level, mt_rand(15, 30));
                        if ($qty > 0) {
                            $ledger->allocateOnline($product, $qty, $manager);
                        }
                    }
                }
            }

            if (! $day->isSunday()) {
                $events = [];
                foreach ($members as $i => $member) {
                    for ($n = mt_rand(0, 3 + $i % 2); $n > 0; $n--) {
                        $events[] = [mt_rand(9 * 60, 17 * 60 + 30), $member];
                    }
                }
                usort($events, fn ($a, $b) => $a[0] <=> $b[0]);

                foreach ($events as [$minuteOfDay, $member]) {
                    $at(intdiv($minuteOfDay, 60), $minuteOfDay % 60);
                    $held = $products->filter(fn ($p) => $this->available($ledger, $p->id, $member->location->id, $member->id) > 0);
                    if ($held->isEmpty()) {
                        continue;
                    }
                    $product = $held->random();
                    $qty = min($this->available($ledger, $product->id, $member->location->id, $member->id), mt_rand(1, 6));
                    $sales[] = $ledger->recordSale($member, $product, $qty, mt_rand(1, 4) === 1 ? '024 '.mt_rand(100, 999).' '.mt_rand(1000, 9999) : null);
                }
            }

            if (mt_rand(1, 10) <= 6) {
                $at(18, mt_rand(0, 30));
                $this->onlineOrder($shop, $ledger, $products, $onlineId, $warehouse);
            }

            if ($day->day === 15 && $sales !== []) {
                $at(17, 45);
                $sale = $sales[array_rand(array_slice($sales, -20, null, true))];
                if (! $sale->reversal()->exists()) {
                    $ledger->reverseSale($sale, 'Customer returned the goods the same day', $manager);
                }
            }

            if ($day->isFriday() && mt_rand(1, 3) === 1) {
                $member = $members->random();
                $held = $products->filter(fn ($p) => $this->available($ledger, $p->id, $member->location->id, $member->id) >= 3);
                if ($held->isNotEmpty()) {
                    $at(15);
                    $request = $ledger->requestReturn($member, $held->random(), mt_rand(1, 3));
                    $at(17);
                    $ledger->confirmReturn($request, $warehouse);
                }
            }
        }

        Carbon::setTestNow();
        $shop->releaseExpired();

        // Today's queue: a paid order to pack, a pay-on-delivery order and a packed one waiting to go out.
        $tilapia = $products['BF-TIL-500'];
        $mackerel = $products['BF-MAC-1KG'];
        foreach ([$tilapia, $mackerel] as $product) {
            if ($shop->availability([$product->id])[$product->id] < 5) {
                $ledger->produce($product, 20, $warehouse);
                $ledger->allocateOnline($product, 10, $manager);
            }
        }
        $customer = fn (string $name, string $method, string $delivery) => [
            'customer_name' => $name, 'phone' => '+233244'.mt_rand(100000, 999999), 'address' => $delivery === 'delivery' ? '14 Ring Road East, Accra' : null,
            'delivery_method' => $delivery, 'payment_method' => $method,
        ];
        $paid = $shop->placeOrder([$tilapia->id => 2, $mackerel->id => 1], $customer('Esi Badu', 'mobile_money', 'delivery'));
        $shop->confirmPayment($shop->startPayment($paid, 'sandbox'), 'DEMO-TODAY-1', $paid->total_pesewas);
        $shop->placeOrder([$mackerel->id => 2], $customer('Kwesi Appiah', 'pay_on_delivery', 'collection'));
        $packed = $shop->placeOrder([$tilapia->id => 1], $customer('Afua Sarpong', 'card', 'delivery'));
        $shop->confirmPayment($shop->startPayment($packed, 'sandbox'), 'DEMO-TODAY-2', $packed->total_pesewas);
        $shop->pack($packed->fresh(), $warehouse);

        $this->command?->info(sprintf('Demo history: %d team sales, %d online orders.', Sale::count(), OnlineOrder::count()));
    }

    private function onlineOrder(OnlineShop $shop, StockLedger $ledger, $products, int $onlineId, User $warehouse): void
    {
        $items = [];
        foreach ($products->random(mt_rand(1, 2)) as $product) {
            $free = $shop->availability([$product->id])[$product->id];
            $held = $ledger->balance($product->id, $onlineId) - $free;
            $available = min($free, $this->available($ledger, $product->id, $onlineId) - $held);
            if ($available > 0) {
                $items[$product->id] = min($available, mt_rand(1, 3));
            }
        }
        if ($items === []) {
            return;
        }

        $roll = mt_rand(1, 10);
        $method = match (true) {
            $roll <= 5 => 'mobile_money',
            $roll <= 7 => 'card',
            default => 'pay_on_delivery',
        };
        $delivery = mt_rand(1, 3) === 1 ? 'collection' : 'delivery';
        $names = ['Efua Mensah', 'Kojo Antwi', 'Akosua Darko', 'Yaw Owusu', 'Abena Ofori', 'Kwame Asare', 'Adwoa Boakye', 'Nana Addo'];

        try {
            $order = $shop->placeOrder($items, [
                'customer_name' => $names[array_rand($names)],
                'phone' => '+23324'.mt_rand(1000000, 9999999),
                'address' => $delivery === 'delivery' ? mt_rand(1, 60).' '.['Ring Road', 'Spintex Road', 'Oxford Street', 'Liberation Road'][mt_rand(0, 3)].', Accra' : null,
                'delivery_method' => $delivery,
                'payment_method' => $method,
            ]);
        } catch (StockRuleException) {
            return;
        }

        if ($method !== 'pay_on_delivery') {
            if (mt_rand(1, 8) === 1) {
                return; // abandoned at the payment page; released after 15 minutes
            }
            $payment = $shop->startPayment($order, 'sandbox');
            Carbon::setTestNow(now()->addMinutes(mt_rand(1, 6)));
            $shop->confirmPayment($payment, 'DEMO-'.$payment->id, $payment->amount_pesewas);
        }

        Carbon::setTestNow(now()->addHours(mt_rand(1, 2)));
        $order->refresh();
        $shop->pack($order, $warehouse);
        Carbon::setTestNow(now()->addHours(mt_rand(1, 4)));
        $order->refresh();
        $shop->complete($order, $warehouse, cashReceived: $order->status === OrderStatus::Packed && ! $order->isPaid());
    }
}
