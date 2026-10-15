<?php

namespace App\Http\Controllers;

use App\Enums\ReturnStatus;
use App\Models\ReturnRequest;
use App\Services\StockLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReturnController extends Controller
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function index(): Response
    {
        $present = fn (ReturnRequest $r) => [
            'id' => $r->id,
            'member' => $r->member->name,
            'product' => $r->product->name,
            'unit' => $r->product->unit,
            'quantity' => $r->quantity,
            'status' => $r->status->value,
            'requested_at' => $r->requested_at->toIso8601String(),
            'decided_by' => $r->decider?->name,
            'decided_at' => $r->confirmed_at?->toIso8601String(),
            'decision_note' => $r->decision_note,
        ];

        $with = ['member:id,name', 'product:id,name,unit', 'decider:id,name'];

        return Inertia::render('Returns/Index', [
            'pending' => ReturnRequest::with($with)
                ->where('status', ReturnStatus::Requested)
                ->oldest('requested_at')
                ->get()
                ->map($present),
            'recent' => ReturnRequest::with($with)
                ->where('status', '!=', ReturnStatus::Requested)
                ->latest('confirmed_at')
                ->limit(30)
                ->get()
                ->map($present),
        ]);
    }

    public function confirm(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        $this->ledger->confirmReturn($returnRequest, $request->user());

        return back()->with('success', 'Return confirmed; stock is back in the warehouse.');
    }

    public function reject(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        $this->ledger->rejectReturn($returnRequest, $request->user(), $data['note'] ?? null);

        return back()->with('success', 'Return rejected.');
    }
}
