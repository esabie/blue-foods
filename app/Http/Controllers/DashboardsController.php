<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use App\Services\Metrics;
use App\Support\MetricFilter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Section 6.2: the four dashboards. Every figure comes from App\Services\Metrics.
 */
class DashboardsController extends Controller
{
    public function __construct(private readonly Metrics $metrics) {}

    /**
     * D1: management overview.
     */
    public function overview(Request $request): Response
    {
        $f = MetricFilter::fromRequest($request);
        $stock = $this->metrics->stockTotals($this->metrics->stock($f));
        $members = $this->metrics->members($f);
        $assigned = $members->sum('assigned');

        return Inertia::render('Dashboards/Overview', [
            ...$this->common($f),
            'headline' => [
                ...$this->metrics->salesTotals($f),
                ...$this->metrics->profit($f),
                'revenue_change' => $this->metrics->revenueChange($f),
                'stock_remaining' => $stock['remaining'],
                'with_team' => $stock['with_team'],
                'unassigned' => $stock['unassigned'],
                'online' => $stock['online'],
                'below_reorder' => $stock['below_reorder'],
                'team_assigned' => $assigned,
                'team_sold' => $members->sum('sold'),
                'sell_through' => $assigned > 0 ? round($members->sum('sold') / $assigned * 100, 1) : null,
            ],
            'revenueByMonth' => $this->metrics->series($f, 'month'),
            'members' => $members,
            'attention' => $this->metrics->needsAttention(),
        ]);
    }

    /**
     * D2: stock dashboard (management and warehouse).
     */
    public function stock(Request $request): Response
    {
        $f = MetricFilter::fromRequest($request);
        $stock = $this->metrics->stock($f);

        return Inertia::render('Dashboards/Stock', [
            ...$this->common($f),
            'headline' => $this->metrics->stockTotals($stock),
            'stock' => $stock,
            'movements' => $this->metrics->recentMovements($f),
        ]);
    }

    /**
     * D3: marketing team dashboard.
     */
    public function team(Request $request): Response
    {
        $f = MetricFilter::fromRequest($request);
        $members = $this->metrics->members($f);
        $selectedId = $request->integer('member') ?: $members->first()['user_id'] ?? null;
        $selected = $selectedId ? User::where('role', Role::Marketing)->find($selectedId) : null;
        $assigned = $members->sum('assigned');

        return Inertia::render('Dashboards/Team', [
            ...$this->common($f),
            'headline' => [
                'active_members' => $members->where('active', true)->count(),
                'assigned' => $assigned,
                'sold' => $members->sum('sold'),
                'held' => $members->sum('held'),
                'sell_through' => $assigned > 0 ? round($members->sum('sold') / $assigned * 100, 1) : null,
                'top_seller' => $members->firstWhere('revenue', '>', 0)['name'] ?? null,
            ],
            'members' => $members,
            'selectedMember' => $selected ? ['id' => $selected->id, 'name' => $selected->name] : null,
            'selectedStock' => $selected ? $this->metrics->memberStock($selected, $f) : [],
        ]);
    }

    /**
     * D4: sales and revenue dashboard, with the online channel split out (W10).
     */
    public function sales(Request $request): Response
    {
        $f = MetricFilter::fromRequest($request);
        $byProduct = $this->metrics->byProduct($f);

        return Inertia::render('Dashboards/Sales', [
            ...$this->common($f),
            'headline' => [
                ...$this->metrics->salesTotals($f),
                ...$this->metrics->profit($f),
                'revenue_change' => $this->metrics->revenueChange($f),
                'most_units' => $byProduct->sortByDesc('units')->first(),
                'today' => $this->metrics->today($f),
            ],
            'revenueByWeek' => $this->metrics->series($f, 'week'),
            'byProduct' => $byProduct,
            'byChannel' => $this->metrics->byChannel($f),
            'channelProfit' => $this->metrics->profitByChannel($f),
            'latest' => $this->metrics->latestSales($f),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function common(MetricFilter $f): array
    {
        return [
            'filters' => $f->toArray(),
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'generatedAt' => now()->toIso8601String(),
        ];
    }
}
