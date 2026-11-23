<?php

namespace App\Http\Controllers;

use App\Enums\StockRequestStatus;
use App\Models\Location;
use App\Models\StockRequest;
use App\Services\CashBook;
use App\Services\StockLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockRequestController extends Controller
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly CashBook $cash,
    ) {}

    public function index(): Response
    {
        $warehouseId = Location::warehouse()->id;
        $with = ['member:id,name', 'product:id,name,unit', 'decider:id,name', 'movement:id,quantity'];

        $present = function (StockRequest $r) use ($warehouseId) {
            $unassigned = $this->ledger->balance($r->product_id, $warehouseId);
            $assigned = $r->movement?->quantity;

            return [
                'id' => $r->id,
                'member' => $r->member->name,
                'member_id' => $r->member_user_id,
                'product_id' => $r->product_id,
                'product' => $r->product->name,
                'unit' => $r->product->unit,
                'quantity' => $assigned ?? $r->quantity,
                'requested_quantity' => $r->quantity,
                'member_note' => $r->member_note,
                'unassigned' => $unassigned,
                'max_assignable' => min($r->quantity, $unassigned),
                'can_fulfill' => $unassigned >= 1,
                'cash_overdue' => $this->cash->isOverdue($r->member),
                'status' => $r->status->value,
                'requested_at' => $r->requested_at->toIso8601String(),
                'decided_by' => $r->decider?->name,
                'decided_at' => $r->confirmed_at?->toIso8601String(),
                'decision_note' => $r->decision_note,
            ];
        };

        return Inertia::render('StockRequests/Index', [
            'pending' => StockRequest::with($with)
                ->where('status', StockRequestStatus::Requested)
                ->oldest('requested_at')
                ->get()
                ->map($present),
            'recent' => StockRequest::with($with)
                ->where('status', '!=', StockRequestStatus::Requested)
                ->latest('confirmed_at')
                ->limit(30)
                ->get()
                ->map($present),
        ]);
    }

    public function confirm(Request $request, StockRequest $stockRequest): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        $this->ledger->confirmStockRequest($stockRequest, $request->user(), $data['quantity']);

        return back()->with('success', 'Stock assigned to the team member.');
    }

    public function reject(Request $request, StockRequest $stockRequest): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:255'],
        ], [
            'note.required' => 'Explain why the stock cannot be assigned yet.',
        ]);

        $this->ledger->rejectStockRequest($stockRequest, $request->user(), $data['note']);

        return back()->with('success', 'Stock request declined; the team member has been notified.');
    }
}
