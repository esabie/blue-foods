<?php

namespace App\Services;

use App\Enums\LocationType;
use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReturnStatus;
use App\Enums\Role;
use App\Models\Location;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Support\MetricFilter;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PRD section 6.1. Every dashboard, report and export takes its figures from here,
 * so the same number is calculated the same way everywhere.
 *
 * Sales figures come from the effective_sales view (team and online sales, excluding
 * reversed ones) and respect the period by movement date. Stock figures are balances
 * at the end of the period.
 */
class Metrics
{
    private const SALES_COUNT_SQL = "COUNT(DISTINCT CASE WHEN channel = 'team' THEN movement_id END) + COUNT(DISTINCT order_id)";

    /** The product's cost per unit in force when the sale was made; NULL if it had no cost yet. */
    private const UNIT_COST_SQL = '(SELECT pc.unit_cost_pesewas FROM product_costs pc
        WHERE pc.product_id = effective_sales.product_id AND pc.effective_from <= effective_sales.occurred_at
        ORDER BY pc.effective_from DESC, pc.id DESC LIMIT 1)';

    private const PROFIT_SQL = 'COALESCE(SUM(revenue_pesewas), 0) AS revenue, COALESCE(SUM(quantity), 0) AS units,
        COALESCE(SUM(quantity * unit_cost), 0) AS cost,
        COALESCE(SUM(CASE WHEN unit_cost IS NULL THEN revenue_pesewas ELSE 0 END), 0) AS uncosted_revenue,
        COALESCE(SUM(CASE WHEN unit_cost IS NULL THEN quantity ELSE 0 END), 0) AS uncosted_units';

    public function sales(MetricFilter $f): Builder
    {
        return DB::table('effective_sales')
            ->whereBetween('occurred_at', [$f->from, $f->to])
            ->when($f->productIds, fn ($q, $ids) => $q->whereIn('product_id', $ids));
    }

    /**
     * The same sales with each one's unit cost alongside (column unit_cost, table alias es).
     */
    public function costedSales(MetricFilter $f): Builder
    {
        return DB::query()->fromSub(
            $this->sales($f)->select('effective_sales.*')->selectRaw(self::UNIT_COST_SQL.' AS unit_cost'),
            'es',
        );
    }

    /**
     * Cost of goods sold and gross profit (revenue − cost) for the period.
     *
     * @return array{cost_pesewas: int, gross_profit_pesewas: int|null, margin: float|null, uncosted_units: int}
     */
    public function profit(MetricFilter $f): array
    {
        return $this->profitFields($this->costedSales($f)->selectRaw(self::PROFIT_SQL)->first());
    }

    /**
     * @return array{team: array<string, mixed>, online: array<string, mixed>}
     */
    public function profitByChannel(MetricFilter $f): array
    {
        $rows = $this->costedSales($f)->groupBy('channel')->selectRaw('channel, '.self::PROFIT_SQL)->get()->keyBy('channel');

        return [
            'team' => $this->profitFields($rows['team'] ?? null),
            'online' => $this->profitFields($rows['online'] ?? null),
        ];
    }

    /**
     * Revenue, units sold, number of sales and average sale value.
     *
     * @return array{revenue_pesewas: int, units_sold: int, sales_count: int, average_sale_pesewas: int}
     */
    public function salesTotals(MetricFilter $f): array
    {
        $row = $this->sales($f)
            ->selectRaw('COALESCE(SUM(revenue_pesewas), 0) AS revenue, COALESCE(SUM(quantity), 0) AS units, '.self::SALES_COUNT_SQL.' AS sales')
            ->first();

        return $this->totals((int) $row->revenue, (int) $row->units, (int) $row->sales);
    }

    /**
     * (Revenue this period − revenue previous period of equal length) ÷ revenue previous period, as a percentage.
     */
    public function revenueChange(MetricFilter $f): ?float
    {
        $current = $this->salesTotals($f)['revenue_pesewas'];
        $previous = $this->salesTotals($f->previous())['revenue_pesewas'];

        return $previous === 0 ? null : round(($current - $previous) / $previous * 100, 1);
    }

    /**
     * Sales grouped by day, week (from Monday) or month, including empty periods, split by channel (W10).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function series(MetricFilter $f, string $grouping): Collection
    {
        $tz = config('inventory.display_timezone');

        $daily = $this->costedSales($f)
            ->selectRaw('DATE(occurred_at) AS day, channel, '.self::SALES_COUNT_SQL.' AS sales, '.self::PROFIT_SQL)
            ->groupByRaw('DATE(occurred_at), channel')
            ->get();

        $bucket = fn (CarbonImmutable $day) => match ($grouping) {
            'day' => $day->toDateString(),
            'week' => $day->startOfWeek(CarbonImmutable::MONDAY)->toDateString(),
            'month' => $day->startOfMonth()->toDateString(),
        };

        $buckets = [];
        foreach (CarbonPeriod::create($f->from->setTimezone($tz)->startOfDay(), '1 day', $f->to->setTimezone($tz)) as $day) {
            $key = $bucket(CarbonImmutable::instance($day));
            $buckets[$key] ??= ['period' => $key, 'team_revenue' => 0, 'online_revenue' => 0, 'units' => 0, 'sales' => 0, 'cost' => 0, 'uncosted_revenue' => 0, 'uncosted_units' => 0];
        }

        foreach ($daily as $row) {
            $key = $bucket(CarbonImmutable::parse($row->day, $tz));
            if (! isset($buckets[$key])) {
                continue;
            }
            $buckets[$key][$row->channel.'_revenue'] += (int) $row->revenue;
            foreach (['units', 'sales', 'cost', 'uncosted_revenue', 'uncosted_units'] as $field) {
                $buckets[$key][$field] += (int) $row->{$field};
            }
        }

        return collect($buckets)->values()->map(function (array $b) use ($grouping) {
            $date = CarbonImmutable::parse($b['period']);
            $revenue = $b['team_revenue'] + $b['online_revenue'];
            $profit = $this->profitFields((object) [...$b, 'revenue' => $revenue]);

            return [
                ...collect($b)->except(['cost', 'uncosted_revenue'])->all(),
                ...$profit,
                'label' => match ($grouping) {
                    'day' => $date->format('d M Y'),
                    'week' => 'Week of '.$date->format('d M Y'),
                    'month' => $date->format('M Y'),
                },
                'short_label' => match ($grouping) {
                    'day' => $date->format('d M'),
                    'week' => $date->format('d M'),
                    'month' => $date->format('M y'),
                },
                'revenue' => $revenue,
                'average' => $this->average($revenue, $b['sales']),
            ];
        });
    }

    /**
     * Revenue and units by product, with each product's share of revenue.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function byProduct(MetricFilter $f): Collection
    {
        $rows = $this->costedSales($f)
            ->join('products as p', 'p.id', '=', 'es.product_id')
            ->groupBy('es.product_id', 'p.name')
            ->selectRaw('es.product_id, p.name, '.self::PROFIT_SQL)
            ->get();

        $total = (int) $rows->sum('revenue');

        return $rows->map(fn ($r) => [
            'product_id' => (int) $r->product_id,
            'name' => $r->name,
            'revenue' => (int) $r->revenue,
            'units' => (int) $r->units,
            'share' => $this->percent((int) $r->revenue, $total),
            ...$this->profitFields($r),
        ])->sortByDesc('revenue')->values();
    }

    /**
     * W10: revenue by channel (marketing team and online).
     *
     * @return array{team: int, online: int}
     */
    public function byChannel(MetricFilter $f): array
    {
        $rows = $this->sales($f)->groupBy('channel')->selectRaw('channel, SUM(revenue_pesewas) AS revenue')->pluck('revenue', 'channel');

        return ['team' => (int) ($rows['team'] ?? 0), 'online' => (int) ($rows['online'] ?? 0)];
    }

    /**
     * Stock per product: flows in the period and balances at the end of it.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function stock(MetricFilter $f): Collection
    {
        $locations = Location::all()->keyBy('id');
        $balances = $this->balances($f);

        $flows = DB::table('stock_movements')
            ->whereBetween('occurred_at', [$f->from, $f->to])
            ->when($f->productIds, fn ($q, $ids) => $q->whereIn('product_id', $ids))
            ->groupBy('product_id', 'type')
            ->selectRaw('product_id, type, SUM(quantity) AS qty')
            ->get()
            ->groupBy('product_id');

        $sold = $this->sales($f)->groupBy('product_id')->selectRaw('product_id, SUM(quantity) AS units')->pluck('units', 'product_id');

        $products = Product::with('currentPrice')
            ->when($f->productIds, fn ($q, $ids) => $q->whereIn('id', $ids))
            ->orderBy('name')
            ->get();

        return $products->map(function (Product $product) use ($locations, $balances, $flows, $sold) {
            $at = ['warehouse' => 0, 'member' => 0, 'online_shop' => 0];
            foreach ($balances->get($product->id, []) as $locationId => $qty) {
                $at[$locations[$locationId]->type->value] += $qty;
            }

            $flow = fn (MovementType $type) => (int) ($flows->get($product->id)?->firstWhere('type', $type->value)?->qty ?? 0);
            $remaining = $at['warehouse'] + $at['member'] + $at['online_shop'];
            $price = $product->currentPrice?->unit_price_pesewas ?? 0;

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'status' => $product->status->value,
                'reorder_level' => $product->reorder_level,
                'price_pesewas' => $price,
                'produced' => $flow(MovementType::Produced),
                'adjusted' => $flow(MovementType::AdjustedIn) - $flow(MovementType::AdjustedOut),
                'unassigned' => $at['warehouse'],
                'with_team' => $at['member'],
                'online' => $at['online_shop'],
                'sold' => (int) ($sold[$product->id] ?? 0),
                'remaining' => $remaining,
                'value_remaining_pesewas' => $remaining * $price,
                'low_stock' => $product->isActive() && $product->reorder_level > 0 && $at['warehouse'] < $product->reorder_level,
            ];
        })->reject(fn ($row) => $row['status'] === 'retired' && $row['remaining'] === 0 && $row['sold'] === 0 && $row['produced'] === 0)
            ->values();
    }

    /**
     * Headline stock figures, summed over products.
     *
     * @param  Collection<int, array<string, mixed>>  $stock
     * @return array<string, int>
     */
    public function stockTotals(Collection $stock): array
    {
        return [
            'produced' => $stock->sum('produced'),
            'unassigned' => $stock->sum('unassigned'),
            'with_team' => $stock->sum('with_team'),
            'online' => $stock->sum('online'),
            'remaining' => $stock->sum('remaining'),
            'sold' => $stock->sum('sold'),
            'value_remaining_pesewas' => $stock->sum('value_remaining_pesewas'),
            'below_reorder' => $stock->where('low_stock', true)->count(),
        ];
    }

    /**
     * Per marketing team member: assigned, sold, still held, sell-through, revenue and share.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function members(MetricFilter $f): Collection
    {
        $members = User::where('role', Role::Marketing)->with('location')->orderBy('name')->get();
        $balances = $this->balances($f);
        $totalRevenue = $this->salesTotals($f)['revenue_pesewas'];

        $movementSum = fn (MovementType $type, string $column) => DB::table('stock_movements')
            ->where('type', $type)
            ->whereBetween('occurred_at', [$f->from, $f->to])
            ->when($f->productIds, fn ($q, $ids) => $q->whereIn('product_id', $ids))
            ->groupBy($column)
            ->selectRaw("{$column} AS location_id, SUM(quantity) AS qty")
            ->pluck('qty', 'location_id');

        $assigned = $movementSum(MovementType::Assigned, 'to_location_id');
        $returned = $movementSum(MovementType::Returned, 'from_location_id');

        $sales = $this->costedSales($f)
            ->where('channel', 'team')
            ->groupBy('member_user_id')
            ->selectRaw('member_user_id, COUNT(*) AS sales, '.self::PROFIT_SQL)
            ->get()
            ->keyBy('member_user_id');

        $lastSale = DB::table('effective_sales')
            ->where('channel', 'team')
            ->where('occurred_at', '<=', $f->to)
            ->when($f->productIds, fn ($q, $ids) => $q->whereIn('product_id', $ids))
            ->groupBy('member_user_id')
            ->selectRaw('member_user_id, MAX(occurred_at) AS last_sale')
            ->pluck('last_sale', 'member_user_id');

        return $members->map(function (User $member) use ($assigned, $returned, $sales, $lastSale, $balances, $totalRevenue) {
            $locationId = $member->location?->id;
            $netAssigned = (int) ($assigned[$locationId] ?? 0) - (int) ($returned[$locationId] ?? 0);
            $sold = (int) ($sales[$member->id]->units ?? 0);
            $revenue = (int) ($sales[$member->id]->revenue ?? 0);
            $held = $balances->reduce(fn ($sum, $byLocation) => $sum + ($byLocation[$locationId] ?? 0), 0);

            return [
                'user_id' => $member->id,
                'name' => $member->name,
                'active' => $member->isActive(),
                'assigned' => $netAssigned,
                'sold' => $sold,
                'held' => $held,
                'sell_through' => $netAssigned > 0 ? round($sold / $netAssigned * 100, 1) : null,
                'revenue' => $revenue,
                'share' => $this->percent($revenue, $totalRevenue),
                'sales' => (int) ($sales[$member->id]->sales ?? 0),
                ...$this->profitFields($sales[$member->id] ?? null),
                'last_sale_at' => isset($lastSale[$member->id]) ? CarbonImmutable::parse($lastSale[$member->id], 'UTC')->toIso8601String() : null,
            ];
        })->filter(fn ($m) => $m['active'] || $m['assigned'] || $m['sold'] || $m['held'])
            ->sortByDesc('revenue')
            ->values();
    }

    /**
     * One member's stock by product at the end of the period.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function memberStock(User $member, MetricFilter $f): Collection
    {
        $locationId = $member->location?->id;
        $balances = $this->balances($f);
        $products = Product::whereIn('id', $balances->keys())->orderBy('name')->get();

        return $products->map(fn (Product $p) => [
            'product_id' => $p->id,
            'name' => $p->name,
            'unit' => $p->unit,
            'quantity' => $balances[$p->id][$locationId] ?? 0,
        ])->filter(fn ($row) => $row['quantity'] > 0)->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function latestSales(MetricFilter $f, int $limit = 15): Collection
    {
        return $this->sales($f)
            ->join('products as p', 'p.id', '=', 'effective_sales.product_id')
            ->leftJoin('users as u', 'u.id', '=', 'effective_sales.member_user_id')
            ->leftJoin('online_orders as o', 'o.id', '=', 'effective_sales.order_id')
            ->orderByDesc('effective_sales.occurred_at')
            ->orderByDesc('effective_sales.movement_id')
            ->limit($limit)
            ->get([
                'effective_sales.movement_id', 'effective_sales.occurred_at', 'effective_sales.channel',
                'effective_sales.quantity', 'effective_sales.revenue_pesewas', 'p.name as product',
                'p.unit', 'u.name as member', 'o.customer_name', 'o.reference',
            ])
            ->map(fn ($r) => [
                'id' => (int) $r->movement_id,
                'occurred_at' => CarbonImmutable::parse($r->occurred_at, 'UTC')->toIso8601String(),
                'channel' => $r->channel,
                'who' => $r->channel === 'team' ? $r->member : "{$r->customer_name} ({$r->reference})",
                'product' => $r->product,
                'unit' => $r->unit,
                'quantity' => (int) $r->quantity,
                'revenue' => (int) $r->revenue_pesewas,
            ]);
    }

    /**
     * Sales recorded today (Ghana time), respecting only the product filter.
     *
     * @return array{revenue_pesewas: int, units_sold: int, sales_count: int, average_sale_pesewas: int}
     */
    public function today(MetricFilter $f): array
    {
        $tz = config('inventory.display_timezone');
        $today = CarbonImmutable::now($tz)->toDateString();

        return $this->salesTotals(MetricFilter::between($today, $today, $f->productIds));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function recentMovements(MetricFilter $f, int $limit = 12): Collection
    {
        return DB::table('stock_movements as m')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->join('users as u', 'u.id', '=', 'm.created_by')
            ->leftJoin('locations as lf', 'lf.id', '=', 'm.from_location_id')
            ->leftJoin('users as uf', 'uf.id', '=', 'lf.user_id')
            ->leftJoin('locations as lt', 'lt.id', '=', 'm.to_location_id')
            ->leftJoin('users as ut', 'ut.id', '=', 'lt.user_id')
            ->whereBetween('m.occurred_at', [$f->from, $f->to])
            ->when($f->productIds, fn ($q, $ids) => $q->whereIn('m.product_id', $ids))
            ->orderByDesc('m.occurred_at')
            ->orderByDesc('m.id')
            ->when($limit > 0, fn ($q) => $q->limit($limit))
            ->get([
                'm.id', 'm.occurred_at', 'm.type', 'm.quantity', 'm.reason', 'm.note', 'p.name as product',
                'u.name as user', 'lf.type as from_type', 'uf.name as from_member', 'lt.type as to_type', 'ut.name as to_member',
            ])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'occurred_at' => CarbonImmutable::parse($r->occurred_at, 'UTC')->toIso8601String(),
                'type' => $r->type,
                'type_label' => MovementType::from($r->type)->label(),
                'product' => $r->product,
                'quantity' => (int) $r->quantity,
                'from' => $this->locationLabel($r->from_type, $r->from_member),
                'to' => $this->locationLabel($r->to_type, $r->to_member),
                'user' => $r->user,
                'reason' => trim(implode(' — ', array_filter([$r->reason, $r->note]))) ?: null,
            ]);
    }

    /**
     * Things management should act on now (overview dashboard).
     *
     * @return list<array{tone: string, text: string, route: string}>
     */
    public function needsAttention(): array
    {
        $items = [];
        $stock = app(StockPosition::class)->byProduct();
        $products = Product::whereIn('id', $stock->pluck('product_id'))->get()->keyBy('id');

        foreach ($stock->where('low_stock', true) as $row) {
            if ($products[$row['product_id']]->isActive()) {
                $items[] = ['tone' => 'amber', 'text' => "{$products[$row['product_id']]->name}: {$row['unassigned']} unassigned, below reorder level {$products[$row['product_id']]->reorder_level}", 'route' => 'stock.index'];
            }
        }

        foreach ($stock->where('reconciles', false) as $row) {
            $items[] = ['tone' => 'red', 'text' => "{$products[$row['product_id']]->name} does not reconcile (rule B6)", 'route' => 'stock.index'];
        }

        $pendingReturns = ReturnRequest::where('status', ReturnStatus::Requested)->count();
        if ($pendingReturns) {
            $items[] = ['tone' => 'blue', 'text' => "{$pendingReturns} return(s) waiting for the warehouse", 'route' => 'returns.index'];
        }

        $toPack = OnlineOrder::where('status', OrderStatus::Paid)
            ->orWhere(fn ($q) => $q->where('status', OrderStatus::PendingPayment)->where('payment_method', PaymentMethod::PayOnDelivery))
            ->count();
        if ($toPack) {
            $items[] = ['tone' => 'blue', 'text' => "{$toPack} online order(s) to pack", 'route' => 'orders.index'];
        }

        $refunds = OnlineOrder::where('needs_refund', true)->count();
        if ($refunds) {
            $items[] = ['tone' => 'red', 'text' => "{$refunds} online order(s) paid after their stock was released; refund needed", 'route' => 'orders.index'];
        }

        $tz = config('inventory.display_timezone');
        foreach (app(CashBook::class)->positions()->where('overdue', true) as $p) {
            $since = CarbonImmutable::parse($p['owing_since'])->setTimezone($tz)->format('j M');
            $items[] = ['tone' => 'amber', 'text' => "{$p['name']} has ".Money::format($p['outstanding_pesewas'])." to hand in, from sales since {$since}", 'route' => 'cash.index'];
        }

        $weekAgo = CarbonImmutable::now()->subDays(7);
        foreach ($this->members(MetricFilter::between('2000-01-01', now()->toDateString())) as $m) {
            $quiet = $m['last_sale_at'] === null || CarbonImmutable::parse($m['last_sale_at'])->lt($weekAgo);
            if ($m['active'] && $m['held'] > 0 && $quiet) {
                $items[] = ['tone' => 'gray', 'text' => "{$m['name']} holds {$m['held']} units with no sale in 7 days", 'route' => 'dashboards.team'];
            }
        }

        return $items;
    }

    /**
     * Balance per product and location at the end of the period.
     *
     * @return Collection<int, array<int, int>> product_id => [location_id => quantity]
     */
    private function balances(MetricFilter $f): Collection
    {
        $side = fn (string $column) => DB::table('stock_movements')
            ->whereNotNull($column)
            ->where('occurred_at', '<=', $f->to)
            ->when($f->productIds, fn ($q, $ids) => $q->whereIn('product_id', $ids))
            ->groupBy('product_id', $column)
            ->selectRaw("product_id, {$column} AS location_id, SUM(quantity) AS qty")
            ->get();

        $result = [];
        foreach ($side('to_location_id') as $r) {
            $result[(int) $r->product_id][(int) $r->location_id] = ($result[(int) $r->product_id][(int) $r->location_id] ?? 0) + (int) $r->qty;
        }
        foreach ($side('from_location_id') as $r) {
            $result[(int) $r->product_id][(int) $r->location_id] = ($result[(int) $r->product_id][(int) $r->location_id] ?? 0) - (int) $r->qty;
        }

        return collect($result);
    }

    private function locationLabel(?string $type, ?string $member): ?string
    {
        return match ($type) {
            null => null,
            LocationType::Warehouse->value => 'Warehouse',
            LocationType::OnlineShop->value => 'Online shop',
            default => $member,
        };
    }

    /**
     * @return array{revenue_pesewas: int, units_sold: int, sales_count: int, average_sale_pesewas: int}
     */
    private function totals(int $revenue, int $units, int $sales): array
    {
        return [
            'revenue_pesewas' => $revenue,
            'units_sold' => $units,
            'sales_count' => $sales,
            'average_sale_pesewas' => $this->average($revenue, $sales),
        ];
    }

    /**
     * Profit counts only sales of products that had a cost at the time; a product with no cost is not
     * treated as free. Units sold without a cost are reported so the gap is visible.
     *
     * @return array{cost_pesewas: int, gross_profit_pesewas: int|null, margin: float|null, uncosted_units: int}
     */
    private function profitFields(?object $row): array
    {
        $units = (int) ($row->units ?? 0);
        $uncostedUnits = (int) ($row->uncosted_units ?? 0);
        $costedRevenue = (int) ($row->revenue ?? 0) - (int) ($row->uncosted_revenue ?? 0);
        $cost = (int) ($row->cost ?? 0);
        $profit = $units > 0 && $uncostedUnits === $units ? null : $costedRevenue - $cost;

        return [
            'cost_pesewas' => $cost,
            'gross_profit_pesewas' => $profit,
            'margin' => $profit === null ? null : $this->percent($profit, $costedRevenue),
            'uncosted_units' => $uncostedUnits,
        ];
    }

    private function average(int $revenue, int $count): int
    {
        return $count === 0 ? 0 : intdiv($revenue + intdiv($count, 2), $count);
    }

    private function percent(int $part, int $total): ?float
    {
        return $total === 0 ? null : round($part / $total * 100, 1);
    }
}
