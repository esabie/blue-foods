<?php

namespace App\Services;

use App\Models\CashHandIn;
use App\Support\MetricFilter;
use App\Support\Money;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * R1: the reports. Each returns columns, rows and totals in one shape that the
 * screen and the spreadsheet export both use, so exported figures equal on-screen ones (R2).
 */
class Reports
{
    public const TYPES = [
        'sales-summary' => 'Sales summary',
        'product-profit' => 'Profit by product',
        'team-performance' => 'Marketing team performance',
        'stock-position' => 'Stock position',
        'stock-movement' => 'Stock movement',
        'cash-hand-ins' => 'Cash hand-ins',
    ];

    public const GROUPINGS = ['day', 'week', 'month'];

    private const PROFIT_COLUMNS = [
        ['key' => 'cost', 'label' => 'Cost of goods', 'format' => 'money'],
        ['key' => 'gross_profit', 'label' => 'Gross profit', 'format' => 'money'],
        ['key' => 'margin', 'label' => 'Margin', 'format' => 'percent'],
        ['key' => 'uncosted_units', 'label' => 'Units without cost', 'format' => 'int'],
    ];

    public function __construct(private readonly Metrics $metrics) {}

    /**
     * @return array{type: string, title: string, columns: list<array{key: string, label: string, format: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null}
     */
    public function build(string $type, MetricFilter $f, string $grouping = 'month'): array
    {
        $report = match ($type) {
            'sales-summary' => $this->salesSummary($f, $grouping),
            'product-profit' => $this->productProfit($f),
            'team-performance' => $this->teamPerformance($f),
            'stock-position' => $this->stockPosition($f),
            'stock-movement' => $this->stockMovement($f),
            'cash-hand-ins' => $this->cashHandIns($f),
            default => throw new InvalidArgumentException("Unknown report {$type}"),
        };

        return ['type' => $type, 'title' => self::TYPES[$type], ...$report];
    }

    /**
     * @return list<list<string>>
     */
    public function toCsvRows(array $report): array
    {
        $columns = $report['columns'];
        $lines = [array_map(fn ($c) => $c['label'].($c['format'] === 'money' ? ' (GHS)' : ''), $columns)];

        foreach ($report['rows'] as $row) {
            $lines[] = array_map(fn ($c) => $this->csvValue($row[$c['key']] ?? null, $c['format']), $columns);
        }

        if ($report['totals'] !== null) {
            $lines[] = array_map(fn ($c) => $this->csvValue($report['totals'][$c['key']] ?? null, $c['format']), $columns);
        }

        return $lines;
    }

    private function salesSummary(MetricFilter $f, string $grouping): array
    {
        $rows = $this->metrics->series($f, $grouping)->map(fn ($r) => [
            'period' => $r['label'],
            'units' => $r['units'],
            'sales' => $r['sales'],
            'team_revenue' => $r['team_revenue'],
            'online_revenue' => $r['online_revenue'],
            'revenue' => $r['revenue'],
            'average' => $r['average'],
            ...$this->profitCells($r),
        ]);

        $totals = $this->metrics->salesTotals($f);
        $channels = $this->metrics->byChannel($f);

        return [
            'columns' => [
                ['key' => 'period', 'label' => ucfirst($grouping), 'format' => 'text'],
                ['key' => 'units', 'label' => 'Units sold', 'format' => 'int'],
                ['key' => 'sales', 'label' => 'Number of sales', 'format' => 'int'],
                ['key' => 'team_revenue', 'label' => 'Team revenue', 'format' => 'money'],
                ['key' => 'online_revenue', 'label' => 'Online revenue', 'format' => 'money'],
                ['key' => 'revenue', 'label' => 'Revenue', 'format' => 'money'],
                ['key' => 'average', 'label' => 'Average sale', 'format' => 'money'],
                ...self::PROFIT_COLUMNS,
            ],
            'rows' => $rows->all(),
            'totals' => [
                'period' => 'Total',
                'units' => $totals['units_sold'],
                'sales' => $totals['sales_count'],
                'team_revenue' => $channels['team'],
                'online_revenue' => $channels['online'],
                'revenue' => $totals['revenue_pesewas'],
                'average' => $totals['average_sale_pesewas'],
                ...$this->profitCells($this->metrics->profit($f)),
            ],
        ];
    }

    /**
     * Cost and profit cells. Where every unit sold lacks a cost, cost and profit are blank, not zero.
     */
    private function profitCells(array $p): array
    {
        $unknown = $p['gross_profit_pesewas'] === null;

        return [
            'cost' => $unknown ? null : $p['cost_pesewas'],
            'gross_profit' => $p['gross_profit_pesewas'],
            'margin' => $p['margin'],
            'uncosted_units' => $p['uncosted_units'],
        ];
    }

    /**
     * Each product's units, revenue, cost of goods and gross profit. A sale is costed at the cost in force
     * on the day it was made; units with no cost are left out of cost and profit and counted separately.
     */
    private function productProfit(MetricFilter $f): array
    {
        $rows = $this->metrics->byProduct($f)->map(fn ($p) => [
            'name' => $p['name'],
            'units' => $p['units'],
            'revenue' => $p['revenue'],
            ...$this->profitCells($p),
        ]);
        $totals = $this->metrics->salesTotals($f);

        return [
            'columns' => [
                ['key' => 'name', 'label' => 'Product', 'format' => 'text'],
                ['key' => 'units', 'label' => 'Units sold', 'format' => 'int'],
                ['key' => 'revenue', 'label' => 'Revenue', 'format' => 'money'],
                ...self::PROFIT_COLUMNS,
            ],
            'rows' => $rows->all(),
            'totals' => [
                'name' => 'Total',
                'units' => $totals['units_sold'],
                'revenue' => $totals['revenue_pesewas'],
                ...$this->profitCells($this->metrics->profit($f)),
            ],
        ];
    }

    private function teamPerformance(MetricFilter $f): array
    {
        $members = $this->metrics->members($f);
        $assigned = $members->sum('assigned');
        $sold = $members->sum('sold');
        $revenue = $members->sum('revenue');
        $total = $this->metrics->salesTotals($f)['revenue_pesewas'];

        return [
            'columns' => [
                ['key' => 'name', 'label' => 'Team member', 'format' => 'text'],
                ['key' => 'assigned', 'label' => 'Assigned', 'format' => 'int'],
                ['key' => 'sold', 'label' => 'Sold', 'format' => 'int'],
                ['key' => 'held', 'label' => 'Still held', 'format' => 'int'],
                ['key' => 'sell_through', 'label' => 'Sell-through', 'format' => 'percent'],
                ['key' => 'revenue', 'label' => 'Revenue', 'format' => 'money'],
                ['key' => 'share', 'label' => 'Share of revenue', 'format' => 'percent'],
                ...self::PROFIT_COLUMNS,
            ],
            'rows' => $members->map(fn ($m) => [
                ...collect($m)->only(['name', 'assigned', 'sold', 'held', 'sell_through', 'revenue', 'share'])->all(),
                ...$this->profitCells($m),
            ])->all(),
            'totals' => [
                'name' => 'Total',
                'assigned' => $assigned,
                'sold' => $sold,
                'held' => $members->sum('held'),
                'sell_through' => $assigned > 0 ? round($sold / $assigned * 100, 1) : null,
                'revenue' => $revenue,
                'share' => $total === 0 ? null : round($revenue / $total * 100, 1),
                ...$this->profitCells($this->metrics->profitByChannel($f)['team']),
            ],
        ];
    }

    private function stockPosition(MetricFilter $f): array
    {
        $stock = $this->metrics->stock($f);
        $totals = $this->metrics->stockTotals($stock);

        return [
            'columns' => [
                ['key' => 'name', 'label' => 'Product', 'format' => 'text'],
                ['key' => 'produced', 'label' => 'Produced', 'format' => 'int'],
                ['key' => 'unassigned', 'label' => 'Not yet assigned', 'format' => 'int'],
                ['key' => 'with_team', 'label' => 'With team', 'format' => 'int'],
                ['key' => 'online', 'label' => 'Online', 'format' => 'int'],
                ['key' => 'sold', 'label' => 'Sold', 'format' => 'int'],
                ['key' => 'value_remaining_pesewas', 'label' => 'Value remaining', 'format' => 'money'],
            ],
            'rows' => $stock->map(fn ($s) => collect($s)->only(['name', 'produced', 'unassigned', 'with_team', 'online', 'sold', 'value_remaining_pesewas'])->all())->all(),
            'totals' => ['name' => 'Total', ...collect($totals)->only(['produced', 'unassigned', 'with_team', 'online', 'sold', 'value_remaining_pesewas'])->all()],
        ];
    }

    private function stockMovement(MetricFilter $f): array
    {
        return [
            'columns' => [
                ['key' => 'occurred_at', 'label' => 'Date and time', 'format' => 'datetime'],
                ['key' => 'type_label', 'label' => 'Type', 'format' => 'text'],
                ['key' => 'product', 'label' => 'Product', 'format' => 'text'],
                ['key' => 'quantity', 'label' => 'Quantity', 'format' => 'int'],
                ['key' => 'from', 'label' => 'From', 'format' => 'text'],
                ['key' => 'to', 'label' => 'To', 'format' => 'text'],
                ['key' => 'user', 'label' => 'User', 'format' => 'text'],
                ['key' => 'reason', 'label' => 'Reason', 'format' => 'text'],
            ],
            'rows' => $this->metrics->recentMovements($f, 0)->all(),
            'totals' => null,
        ];
    }

    /**
     * Every hand-in received in the period, oldest first. A cancelled hand-in keeps its row but its
     * amount moves to the Cancelled column, so the Amount column always adds up to money actually received.
     * Products do not apply to cash.
     */
    private function cashHandIns(MetricFilter $f): array
    {
        $handIns = CashHandIn::with(['member:id,name', 'receiver:id,name', 'cancellation.canceller:id,name'])
            ->whereBetween('created_at', [$f->from, $f->to])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $rows = $handIns->map(function (CashHandIn $h) {
            $cancelled = $h->cancellation !== null;

            return [
                'id' => $h->id,
                'received_at' => $h->created_at->toIso8601String(),
                'member' => $h->member->name,
                'method' => $h->method->label(),
                'reference' => $h->reference,
                'received_by' => $h->receiver->name,
                'amount' => $cancelled ? null : $h->amount_pesewas,
                'cancelled' => $cancelled ? $h->amount_pesewas : null,
                'note' => $cancelled
                    ? trim("Cancelled by {$h->cancellation->canceller->name}: {$h->cancellation->reason}".($h->note ? " ({$h->note})" : ''))
                    : $h->note,
            ];
        });

        return [
            'columns' => [
                ['key' => 'id', 'label' => 'Hand-in #', 'format' => 'text'],
                ['key' => 'received_at', 'label' => 'Date and time', 'format' => 'datetime'],
                ['key' => 'member', 'label' => 'Team member', 'format' => 'text'],
                ['key' => 'method', 'label' => 'Method', 'format' => 'text'],
                ['key' => 'reference', 'label' => 'Transaction ID', 'format' => 'text'],
                ['key' => 'received_by', 'label' => 'Received by', 'format' => 'text'],
                ['key' => 'amount', 'label' => 'Amount', 'format' => 'money'],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'format' => 'money'],
                ['key' => 'note', 'label' => 'Note', 'format' => 'text'],
            ],
            'rows' => $rows->all(),
            'totals' => [
                'id' => 'Total',
                'amount' => (int) $rows->sum('amount'),
                'cancelled' => (int) $rows->sum('cancelled'),
            ],
        ];
    }

    private function csvValue(mixed $value, string $format): string
    {
        if ($value === null) {
            return '';
        }

        return match ($format) {
            'money' => ($value < 0 ? '-' : '').Money::toCedis(abs((int) $value)),
            'percent' => number_format((float) $value, 1, '.', ''),
            'datetime' => CarbonImmutable::parse($value)->setTimezone(config('inventory.display_timezone'))->format('Y-m-d H:i'),
            default => (string) $value,
        };
    }
}
