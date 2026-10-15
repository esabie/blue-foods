<?php

use App\Models\Product;
use App\Models\ProductCost;
use App\Models\User;
use App\Services\Metrics;
use App\Services\Reports;
use App\Support\MetricFilter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Section 10 "metric tests": every section 6.1 metric checked against figures worked out by hand.
 *
 * Data set (Ghana time = UTC):
 *   05 Jan  produce A 100 (GHS 25.00), produce B 50 (GHS 10.00)
 *   06 Jan  assign A 40 + B 10 to Ama, A 20 to Kofi
 *   10 Jan  Ama sells A 5 (125.00) and B 2 (20.00)
 *   20 Jan  Kofi sells A 10 (250.00)
 *   03 Feb  Ama sells A 5 (125.00); management allocates A 10 to the online shop
 *   10 Feb  Kofi sells A 2 (50.00), reversed on 11 Feb
 *   15 Feb  online order A 3, paid (75.00; the delivery fee is not revenue)
 *   01 Mar  A's price changes to GHS 30.00; Ama sells A 4 (120.00)
 *   02 Mar  Ama returns A 6, confirmed by the warehouse
 */
function metricsDataset(): object
{
    Queue::fake();
    $at = fn (string $when) => test()->travelTo(CarbonImmutable::parse($when, 'UTC'));

    $at('2026-01-05 08:00');
    $manager = User::factory()->management()->create();
    $warehouse = User::factory()->warehouse()->create();
    $ama = User::factory()->marketing()->create(['name' => 'Ama']);
    $kofi = User::factory()->marketing()->create(['name' => 'Kofi']);
    $a = Product::factory()->priced(2500)->create(['name' => 'Smoked tilapia', 'reorder_level' => 50]);
    $b = Product::factory()->priced(1000)->create(['name' => 'Dried anchovies']);

    $at('2026-01-05 10:00');
    ledger()->produce($a, 100, $warehouse);
    ledger()->produce($b, 50, $warehouse);

    $at('2026-01-06 10:00');
    ledger()->assign($a, $ama, 40, $manager);
    ledger()->assign($b, $ama, 10, $manager);
    ledger()->assign($a, $kofi, 20, $manager);

    $at('2026-01-10 10:00');
    ledger()->recordSale($ama, $a, 5);
    ledger()->recordSale($ama, $b, 2);

    $at('2026-01-20 10:00');
    ledger()->recordSale($kofi, $a, 10);

    $at('2026-02-03 10:00');
    ledger()->recordSale($ama, $a, 5);
    ledger()->allocateOnline($a, 10, $manager);

    $at('2026-02-10 10:00');
    $mistake = ledger()->recordSale($kofi, $a, 2);
    $at('2026-02-11 10:00');
    ledger()->reverseSale($mistake, 'Entered twice', $manager);

    $at('2026-02-15 10:00');
    $order = placeOnlineOrder([$a->id => 3]);
    shop()->confirmPayment(shop()->startPayment($order, 'sandbox'), 'P1', $order->total_pesewas);

    $at('2026-03-01 09:00');
    $a->prices()->create(['unit_price_pesewas' => 3000, 'effective_from' => now(), 'set_by' => $manager->id]);
    $at('2026-03-01 10:00');
    ledger()->recordSale($ama, $a, 4);

    $at('2026-03-02 10:00');
    ledger()->confirmReturn(ledger()->requestReturn($ama, $a, 6), $warehouse);

    test()->travelBack();

    return (object) compact('manager', 'warehouse', 'ama', 'kofi', 'a', 'b', 'order');
}

function q1(array $products = []): MetricFilter
{
    return MetricFilter::between('2026-01-01', '2026-03-31', $products);
}

test('revenue, units sold, number of sales and average sale', function () {
    metricsDataset();
    $m = app(Metrics::class);

    // 125 + 20 + 250 + 125 + 75 + 120; the reversed sale is excluded.
    expect($m->salesTotals(q1()))->toBe([
        'revenue_pesewas' => 71500,
        'units_sold' => 29,
        'sales_count' => 6,               // five team sales + one paid online order
        'average_sale_pesewas' => 11917,  // 715.00 / 6 = 119.1666…
    ]);

    // February alone: Ama's 125 and the online order's 75.
    expect($m->salesTotals(MetricFilter::between('2026-02-01', '2026-02-28')))->toMatchArray([
        'revenue_pesewas' => 20000, 'units_sold' => 8, 'sales_count' => 2,
    ]);
});

test('the product filter changes every figure', function () {
    $d = metricsDataset();
    $m = app(Metrics::class);

    expect($m->salesTotals(q1([$d->b->id])))->toMatchArray(['revenue_pesewas' => 2000, 'units_sold' => 2, 'sales_count' => 1])
        ->and($m->stockTotals($m->stock(q1([$d->b->id]))))->toMatchArray(['produced' => 50, 'unassigned' => 40, 'with_team' => 8, 'online' => 0, 'sold' => 2]);

    $ama = $m->members(q1([$d->b->id]))->firstWhere('name', 'Ama');
    expect($ama)->toMatchArray(['assigned' => 10, 'sold' => 2, 'held' => 8, 'sell_through' => 20.0, 'revenue' => 2000, 'share' => 100.0]);
});

test('revenue change against the previous period of equal length', function () {
    metricsDataset();

    // February (28 days) against 4 to 31 January: (200 - 395) / 395 = -49.37%.
    expect(app(Metrics::class)->revenueChange(MetricFilter::between('2026-02-01', '2026-02-28')))->toBe(-49.4);
    // Nothing was sold in the 90 days before 1 January.
    expect(app(Metrics::class)->revenueChange(q1()))->toBeNull();
});

test('stock metrics are balances at the end of the period', function () {
    metricsDataset();
    $m = app(Metrics::class);

    // A: warehouse 100 - 70 + 6 = 36; Ama 40 - 14 - 6 = 20; Kofi 20 - 10 = 10; online 10 - 3 = 7; sold 27.
    // B: warehouse 40; Ama 8; sold 2. Value at current prices: A 73 × 30.00 + B 48 × 10.00.
    expect($m->stockTotals($m->stock(q1())))->toBe([
        'produced' => 150,
        'unassigned' => 76,
        'with_team' => 38,
        'online' => 7,
        'remaining' => 121,
        'sold' => 29,
        'value_remaining_pesewas' => 267000,
        'below_reorder' => 1, // A's warehouse 36 < reorder level 50
    ]);

    // At the end of January: warehouse A 100 - 60 and B 50 - 10; Ama A 35 and B 8, Kofi A 10; nothing online yet.
    expect($m->stockTotals($m->stock(MetricFilter::between('2026-01-01', '2026-01-31'))))->toMatchArray([
        'unassigned' => 40 + 40, 'with_team' => 35 + 8 + 10, 'online' => 0, 'sold' => 17,
    ]);
});

test('team member metrics: assigned, sold, held, sell-through and share of revenue', function () {
    metricsDataset();
    $members = app(Metrics::class)->members(q1())->keyBy('name');

    // Ama: assigned 50 - returned 6 = 44; sold 16; 16 / 44 = 36.36%; 390.00 of 715.00 = 54.5%.
    expect($members['Ama'])->toMatchArray([
        'assigned' => 44, 'sold' => 16, 'held' => 28, 'sell_through' => 36.4, 'revenue' => 39000, 'share' => 54.5, 'sales' => 4,
    ]);
    // Kofi: the reversed sale does not count; 250.00 of 715.00 = 34.97%.
    expect($members['Kofi'])->toMatchArray([
        'assigned' => 20, 'sold' => 10, 'held' => 10, 'sell_through' => 50.0, 'revenue' => 25000, 'share' => 35.0, 'sales' => 1,
    ]);
    expect($members->keys()->all())->toBe(['Ama', 'Kofi']);
});

test('W10: online sales are a separate channel', function () {
    metricsDataset();
    $m = app(Metrics::class);

    expect($m->byChannel(q1()))->toBe(['team' => 64000, 'online' => 7500]);

    $feb = $m->series(q1(), 'month')->firstWhere('period', '2026-02-01');
    expect($feb)->toMatchArray(['team_revenue' => 12500, 'online_revenue' => 7500, 'revenue' => 20000, 'units' => 8, 'sales' => 2]);
});

test('revenue by month and by product', function () {
    metricsDataset();
    $m = app(Metrics::class);

    expect($m->series(q1(), 'month')->pluck('revenue', 'label')->all())->toBe(['Jan 2026' => 39500, 'Feb 2026' => 20000, 'Mar 2026' => 12000]);
    expect($m->byProduct(q1())->map(fn ($p) => [$p['name'], $p['units'], $p['revenue'], $p['share']])->all())->toBe([
        ['Smoked tilapia', 27, 69500, 97.2],
        ['Dried anchovies', 2, 2000, 2.8],
    ]);
});

/**
 * Tilapia (A) costs 15.00 from 1 Jan and 16.00 from 1 Feb; anchovies (B) have no cost.
 * Costed sales: Jan A 5 + 10 at 15.00 = 225.00; Feb A 5 at 16.00 + online A 3 at 16.00 = 128.00;
 * Mar A 4 at 16.00 = 64.00. Cost 417.00 against costed revenue 695.00: profit 278.00, margin 40.0%.
 * The 2 anchovies (20.00) have no cost, so they are left out of profit and reported as uncosted.
 */
function withCosts(object $d): void
{
    test()->travelTo(CarbonImmutable::parse('2026-03-05 12:00', 'UTC'));
    ProductCost::create(['product_id' => $d->a->id, 'unit_cost_pesewas' => 1500, 'effective_from' => '2026-01-01 00:00:00', 'set_by' => $d->manager->id]);
    ProductCost::create(['product_id' => $d->a->id, 'unit_cost_pesewas' => 1600, 'effective_from' => '2026-02-01 00:00:00', 'set_by' => $d->manager->id]);
    test()->travelBack();
}

test('gross profit costs each sale at the cost in force when it was made', function () {
    $d = metricsDataset();
    withCosts($d);
    $m = app(Metrics::class);

    expect($m->profit(q1()))->toBe(['cost_pesewas' => 41700, 'gross_profit_pesewas' => 27800, 'margin' => 40.0, 'uncosted_units' => 2])
        ->and($m->profitByChannel(q1()))->toBe([
            'team' => ['cost_pesewas' => 36900, 'gross_profit_pesewas' => 25100, 'margin' => 40.5, 'uncosted_units' => 2],
            'online' => ['cost_pesewas' => 4800, 'gross_profit_pesewas' => 2700, 'margin' => 36.0, 'uncosted_units' => 0],
        ]);

    $products = $m->byProduct(q1())->keyBy('name');
    expect($products['Smoked tilapia'])->toMatchArray(['cost_pesewas' => 41700, 'gross_profit_pesewas' => 27800, 'margin' => 40.0, 'uncosted_units' => 0])
        ->and($products['Dried anchovies'])->toMatchArray(['cost_pesewas' => 0, 'gross_profit_pesewas' => null, 'margin' => null, 'uncosted_units' => 2]);

    $members = $m->members(q1())->keyBy('name');
    expect($members['Ama'])->toMatchArray(['cost_pesewas' => 21900, 'gross_profit_pesewas' => 15100, 'margin' => 40.8, 'uncosted_units' => 2])
        ->and($members['Kofi'])->toMatchArray(['cost_pesewas' => 15000, 'gross_profit_pesewas' => 10000, 'margin' => 40.0]);

    expect($m->series(q1(), 'month')->pluck('gross_profit_pesewas', 'label')->all())
        ->toBe(['Jan 2026' => 15000, 'Feb 2026' => 7200, 'Mar 2026' => 5600]);
});

test('profit is unknown, not 100%, when no product has a cost', function () {
    metricsDataset();

    expect(app(Metrics::class)->profit(q1([])))->toMatchArray(['gross_profit_pesewas' => null, 'margin' => null, 'uncosted_units' => 29])
        ->and(app(Metrics::class)->profit(MetricFilter::between('2025-01-01', '2025-01-31')))
        ->toBe(['cost_pesewas' => 0, 'gross_profit_pesewas' => 0, 'margin' => null, 'uncosted_units' => 0]);
});

test('R1: every report reconciles with the dashboards for the same filters', function () {
    $d = metricsDataset();

    foreach ([q1(), MetricFilter::between('2026-02-01', '2026-02-28'), q1([$d->a->id])] as $f) {
        $query = $f->toArray();
        $dashboard = $this->actingAs($d->manager)->get(route('dashboards.sales', $query))->viewData('page')['props'];
        $stockDash = $this->get(route('dashboards.stock', $query))->viewData('page')['props'];
        $teamDash = $this->get(route('dashboards.team', $query))->viewData('page')['props'];

        $reports = app(Reports::class);
        foreach (['day', 'week', 'month'] as $grouping) {
            $summary = $reports->build('sales-summary', $f, $grouping);
            expect($summary['totals']['revenue'])->toBe($dashboard['headline']['revenue_pesewas'])
                ->and($summary['totals']['units'])->toBe($dashboard['headline']['units_sold'])
                ->and($summary['totals']['sales'])->toBe($dashboard['headline']['sales_count'])
                ->and(collect($summary['rows'])->sum('revenue'))->toBe($summary['totals']['revenue'])
                ->and(collect($summary['rows'])->sum('units'))->toBe($summary['totals']['units']);
        }

        $position = $reports->build('stock-position', $f);
        foreach (['produced', 'unassigned', 'with_team', 'online', 'sold', 'value_remaining_pesewas'] as $key) {
            expect($position['totals'][$key])->toBe($stockDash['headline'][$key]);
        }

        $team = $reports->build('team-performance', $f);
        expect($team['totals']['assigned'])->toBe($teamDash['headline']['assigned'])
            ->and($team['totals']['sold'])->toBe($teamDash['headline']['sold'])
            ->and($team['totals']['held'])->toBe($teamDash['headline']['held'])
            ->and($team['totals']['sell_through'])->toBe($teamDash['headline']['sell_through']);
    }
});

test('R2: the exported spreadsheet has exactly the on-screen figures', function () {
    $d = metricsDataset();
    $query = ['from' => '2026-01-01', 'to' => '2026-03-31', 'grouping' => 'month'];

    $screen = $this->actingAs($d->manager)->get(route('reports.show', ['type' => 'sales-summary', ...$query]))
        ->assertInertia(fn (Assert $page) => $page->component('Reports/Show'))
        ->viewData('page')['props']['report'];

    $csv = $this->get(route('reports.export', ['type' => 'sales-summary', ...$query]))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=blue-foods-sales-summary-2026-01-01-to-2026-03-31.csv')
        ->streamedContent();

    $lines = array_map('str_getcsv', explode("\n", trim(substr($csv, 3))));
    expect($lines)->toBe([
        ['Month', 'Units sold', 'Number of sales', 'Team revenue (GHS)', 'Online revenue (GHS)', 'Revenue (GHS)', 'Average sale (GHS)',
            'Cost of goods (GHS)', 'Gross profit (GHS)', 'Margin', 'Units without cost'],
        ['Jan 2026', '17', '3', '395.00', '0.00', '395.00', '131.67', '', '', '', '17'],
        ['Feb 2026', '8', '2', '125.00', '75.00', '200.00', '100.00', '', '', '', '8'],
        ['Mar 2026', '4', '1', '120.00', '0.00', '120.00', '120.00', '', '', '', '4'],
        ['Total', '29', '6', '640.00', '75.00', '715.00', '119.17', '', '', '', '29'],
    ]);
    expect(collect($screen['rows'])->pluck('revenue')->all())->toBe([39500, 20000, 12000])
        ->and($screen['totals']['revenue'])->toBe(71500);
});

test('the profit reports reconcile with the dashboards and export the same figures', function () {
    $d = metricsDataset();
    withCosts($d);
    $query = ['from' => '2026-01-01', 'to' => '2026-03-31'];
    $reports = app(Reports::class);

    $dashboard = $this->actingAs($d->manager)->get(route('dashboards.sales', $query))->viewData('page')['props'];
    expect($dashboard['headline'])->toMatchArray(['gross_profit_pesewas' => 27800, 'margin' => 40.0, 'uncosted_units' => 2])
        ->and($dashboard['channelProfit']['online'])->toMatchArray(['gross_profit_pesewas' => 2700]);

    $byProduct = $reports->build('product-profit', q1());
    expect($byProduct['rows'])->toBe([
        ['name' => 'Smoked tilapia', 'units' => 27, 'revenue' => 69500, 'cost' => 41700, 'gross_profit' => 27800, 'margin' => 40.0, 'uncosted_units' => 0],
        ['name' => 'Dried anchovies', 'units' => 2, 'revenue' => 2000, 'cost' => null, 'gross_profit' => null, 'margin' => null, 'uncosted_units' => 2],
    ])->and($byProduct['totals'])->toBe([
        'name' => 'Total', 'units' => 29, 'revenue' => 71500, 'cost' => 41700, 'gross_profit' => 27800, 'margin' => 40.0, 'uncosted_units' => 2,
    ]);

    $summary = $reports->build('sales-summary', q1(), 'month');
    expect($summary['totals']['gross_profit'])->toBe($dashboard['headline']['gross_profit_pesewas'])
        ->and(collect($summary['rows'])->sum('gross_profit'))->toBe(27800)
        ->and(collect($summary['rows'])->sum('cost'))->toBe(41700);

    $team = $reports->build('team-performance', q1());
    expect($team['totals'])->toMatchArray(['cost' => 36900, 'gross_profit' => 25100, 'margin' => 40.5, 'uncosted_units' => 2])
        ->and(collect($team['rows'])->sum('gross_profit'))->toBe(25100);

    $csv = $this->get(route('reports.export', ['type' => 'product-profit', ...$query]))->assertOk()->streamedContent();
    expect(array_map('str_getcsv', explode("\n", trim(substr($csv, 3)))))->toBe([
        ['Product', 'Units sold', 'Revenue (GHS)', 'Cost of goods (GHS)', 'Gross profit (GHS)', 'Margin', 'Units without cost'],
        ['Smoked tilapia', '27', '695.00', '417.00', '278.00', '40.0', '0'],
        ['Dried anchovies', '2', '20.00', '', '', '', '2'],
        ['Total', '29', '715.00', '417.00', '278.00', '40.0', '2'],
    ]);
});

test('costs and profit never reach the warehouse or the marketing team', function () {
    $d = metricsDataset();
    withCosts($d);

    $stock = $this->actingAs($d->warehouse)->get(route('dashboards.stock', ['from' => '2026-01-01', 'to' => '2026-03-31']))->assertOk()->getContent();
    $this->get(route('products.index'))->assertForbidden();
    $this->get(route('reports.show', 'product-profit'))->assertForbidden();

    $home = $this->actingAs($d->ama)->get(route('member.home'))->assertOk()->getContent();
    $sales = $this->get(route('member.sales'))->assertOk()->getContent();

    foreach ([$stock, $home, $sales] as $page) {
        expect($page)->not->toContain('cost_pesewas')->not->toContain('gross_profit')->not->toContain('unit_cost');
    }
});

test('the stock movement report lists every movement in the period with user and reason', function () {
    $d = metricsDataset();

    $report = app(Reports::class)->build('stock-movement', MetricFilter::between('2026-02-11', '2026-02-11'));

    expect($report['rows'])->toHaveCount(1)
        ->and($report['rows'][0])->toMatchArray(['type' => 'SALE_REVERSED', 'quantity' => 2, 'to' => 'Kofi', 'user' => $d->manager->name])
        ->and($report['rows'][0]['reason'])->toContain('Entered twice');
});

test('the dashboards show the hand-worked figures', function () {
    $d = metricsDataset();
    $query = ['from' => '2026-01-01', 'to' => '2026-03-31'];

    $this->actingAs($d->manager)->get(route('dashboards.overview', $query))->assertInertia(fn (Assert $page) => $page
        ->component('Dashboards/Overview')
        ->where('headline.revenue_pesewas', 71500)
        ->where('headline.units_sold', 29)
        ->where('headline.stock_remaining', 121)
        ->where('headline.with_team', 38)
        ->where('headline.unassigned', 76)
        ->where('headline.team_sold', 26)
        ->where('headline.team_assigned', 64)
        ->where('headline.sell_through', 40.6) // 26 / 64
        ->has('revenueByMonth', 3)
    );

    $this->get(route('dashboards.team', [...$query, 'member' => $d->ama->id]))->assertInertia(fn (Assert $page) => $page
        ->where('headline.top_seller', 'Ama')
        ->where('selectedMember.name', 'Ama')
        ->where('selectedStock', [
            ['product_id' => $d->b->id, 'name' => 'Dried anchovies', 'unit' => 'pack', 'quantity' => 8],
            ['product_id' => $d->a->id, 'name' => 'Smoked tilapia', 'unit' => 'pack', 'quantity' => 20],
        ])
    );

    $this->get(route('dashboards.sales', $query))->assertInertia(fn (Assert $page) => $page
        ->where('headline.average_sale_pesewas', 11917)
        ->where('headline.most_units.name', 'Smoked tilapia')
        ->where('byChannel', ['team' => 64000, 'online' => 7500])
        ->has('latest', 6)
    );
});

test('the warehouse sees the stock dashboard only', function () {
    $d = metricsDataset();

    $this->actingAs($d->warehouse)->get(route('dashboard'))->assertRedirect(route('dashboards.stock'));
    $this->actingAs($d->manager)->get(route('dashboard'))->assertRedirect(route('dashboards.overview'));
    $this->actingAs($d->warehouse)->get(route('dashboards.sales'))->assertForbidden();
});
