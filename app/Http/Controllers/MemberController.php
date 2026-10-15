<?php

namespace App\Http\Controllers;

use App\Enums\ReturnStatus;
use App\Models\CashHandIn;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Sale;
use App\Services\CashBook;
use App\Services\StockLedger;
use App\Services\StockPosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The marketing team's phone screens: own stock, sales and returns only (L1, L5, A3).
 */
class MemberController extends Controller
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function home(Request $request, StockPosition $position, CashBook $cash): Response
    {
        $member = $request->user();
        $outstanding = $cash->outstanding($member);
        $handedIn = (int) CashHandIn::notCancelled()->where('member_user_id', $member->id)->sum('amount_pesewas');
        $owingSince = $outstanding > 0 ? $cash->owingSince($member->id, $handedIn) : null;
        $holdings = $position->holdings($member->location->id);
        $prices = Product::with('currentPrice')->findMany($holdings->pluck('product_id'))->keyBy('id');

        $pending = ReturnRequest::with('product:id,name,unit')
            ->where('member_user_id', $member->id)
            ->where('status', ReturnStatus::Requested)
            ->get();

        $today = Sale::where('member_user_id', $member->id)
            ->notReversed()
            ->where('created_at', '>=', now()->startOfDay());

        $month = Sale::where('member_user_id', $member->id)
            ->notReversed()
            ->where('created_at', '>=', now()->startOfMonth());

        return Inertia::render('Member/Home', [
            'stock' => $holdings->map(fn ($row) => [
                'product_id' => $row->product_id,
                'name' => $row->name,
                'sku' => $row->sku,
                'unit' => $row->unit,
                'held' => (int) $row->quantity,
                'pending_return' => (int) $pending->where('product_id', $row->product_id)->sum('quantity'),
                'available' => (int) $row->quantity - (int) $pending->where('product_id', $row->product_id)->sum('quantity'),
                'price_pesewas' => $prices[$row->product_id]->currentPrice?->unit_price_pesewas,
            ]),
            'today' => [
                'sales' => (clone $today)->count(),
                'units' => (int) (clone $today)->sum('quantity'),
                'revenue_pesewas' => (int) (clone $today)->sum('total_pesewas'),
            ],
            'month_revenue_pesewas' => (int) $month->sum('total_pesewas'),
            'cash' => [
                'outstanding_pesewas' => $outstanding,
                'owing_since' => $owingSince?->toIso8601String(),
                'overdue' => $owingSince !== null && $owingSince->lt($cash->dueBefore()),
            ],
            'recentHandIns' => CashHandIn::with(['member:id,name', 'receiver:id,name', 'cancellation.canceller:id,name'])
                ->where('member_user_id', $member->id)
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (CashHandIn $h) => CashController::present($h)),
            'pendingReturns' => $pending->map(fn (ReturnRequest $r) => [
                'id' => $r->id,
                'product' => $r->product->name,
                'unit' => $r->product->unit,
                'quantity' => $r->quantity,
                'requested_at' => $r->requested_at->toIso8601String(),
            ]),
            'recentSales' => Sale::with(['member:id,name', 'product:id,name,unit', 'reversal'])
                ->where('member_user_id', $member->id)
                ->latest()
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (Sale $sale) => SaleController::present($sale)),
        ]);
    }

    public function sales(Request $request): Response
    {
        $sales = Sale::with(['member:id,name', 'product:id,name,unit', 'reversal'])
            ->where('member_user_id', $request->user()->id)
            ->latest()
            ->latest('id')
            ->paginate(30)
            ->through(fn (Sale $sale) => SaleController::present($sale));

        return Inertia::render('Member/Sales', ['sales' => $sales]);
    }

    /**
     * L1/L2: price and total come from the system; only product, quantity and an optional note are accepted.
     */
    public function storeSale(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'customer_note' => ['nullable', 'string', 'max:255'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $sale = $this->ledger->recordSale($request->user(), $product, $data['quantity'], $data['customer_note'] ?? null);

        return redirect()->route('member.home')->with('success', "Sale saved: {$sale->quantity} {$product->unit} of {$product->name}.");
    }

    public function storeReturn(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $this->ledger->requestReturn($request->user(), $product, $data['quantity']);

        return redirect()->route('member.home')->with('success', "Return of {$data['quantity']} {$product->unit} of {$product->name} requested. The warehouse will confirm when received.");
    }
}
