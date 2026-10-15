<?php

namespace App\Console\Commands;

use App\Enums\MovementType;
use App\Enums\PaymentStatus;
use App\Models\Product;
use App\Services\Metrics;
use App\Services\Reports;
use App\Services\StockPosition;
use App\Support\MetricFilter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * PRD 6.4 data quality checks, run daily during the pilot.
 */
class CheckStockIntegrity extends Command
{
    protected $signature = 'stock:check';

    protected $description = 'Run the PRD 6.4 data quality checks: rules B1 and B6, sales and orders against the ledger, dashboard against reports';

    public function handle(StockPosition $position, Metrics $metrics, Reports $reports): int
    {
        $failures = 0;

        $negative = $position->negativeBalances();
        foreach ($negative as $row) {
            $this->error("B1: product {$row->product_id} at location {$row->location_id} has balance {$row->quantity}");
        }
        $failures += $negative->count();

        $products = Product::with('currentPrice')->get()->keyBy('id');
        foreach ($position->byProduct($products) as $row) {
            if (! $row['reconciles']) {
                $failures++;
                $this->error("B6: {$products[$row['product_id']]->sku} does not reconcile (produced + adjustments != remaining + sold)");
            }
        }

        $salesWithoutMovement = DB::table('sales')
            ->leftJoin('stock_movements as m', 'm.id', '=', 'sales.movement_id')
            ->where(fn ($q) => $q->whereNull('m.id')->orWhere('m.type', '!=', MovementType::Sold->value))
            ->count();
        if ($salesWithoutMovement > 0) {
            $failures += $salesWithoutMovement;
            $this->error("{$salesWithoutMovement} sale(s) without a matching SOLD movement");
        }

        $onlineWithoutItem = DB::table('stock_movements as m')
            ->leftJoin('order_items as i', 'i.movement_id', '=', 'm.id')
            ->where('m.type', MovementType::SoldOnline->value)
            ->whereNull('i.id')
            ->count();
        if ($onlineWithoutItem > 0) {
            $failures += $onlineWithoutItem;
            $this->error("{$onlineWithoutItem} SOLD_ONLINE movement(s) without an order item");
        }

        $paidItemsWithoutMovement = DB::table('order_items as i')
            ->join('online_orders as o', 'o.id', '=', 'i.order_id')
            ->whereNotNull('o.paid_at')
            ->whereNull('i.movement_id')
            ->count();
        if ($paidItemsWithoutMovement > 0) {
            $failures += $paidItemsWithoutMovement;
            $this->error("{$paidItemsWithoutMovement} item(s) on paid orders without a SOLD_ONLINE movement");
        }

        $paidWithoutPayment = DB::table('online_orders as o')
            ->whereNotNull('o.paid_at')
            ->whereNotExists(fn ($q) => $q->from('payments as p')->whereColumn('p.order_id', 'o.id')->where('p.status', PaymentStatus::Paid->value))
            ->count();
        if ($paidWithoutPayment > 0) {
            $failures += $paidWithoutPayment;
            $this->error("{$paidWithoutPayment} paid order(s) without a payment record");
        }

        $f = MetricFilter::between('2000-01-01', now()->addDay()->toDateString());
        $dashboard = $metrics->salesTotals($f)['revenue_pesewas'];
        $summary = $reports->build('sales-summary', $f, 'month');
        $report = $summary['totals']['revenue'];
        $rows = collect($summary['rows'])->sum('revenue');
        if ($dashboard !== $report || $report !== $rows) {
            $failures++;
            $this->error("Dashboard revenue {$dashboard} does not equal the sales summary report ({$report}, rows {$rows})");
        }

        if ($failures === 0) {
            $this->info('All stock checks passed.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
