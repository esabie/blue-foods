<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\StockLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['member_id' => ['nullable', 'integer']]);

        $sales = Sale::with(['member:id,name', 'product:id,name,unit', 'reversal.creator:id,name'])
            ->when($filters['member_id'] ?? null, fn ($q, $id) => $q->where('member_user_id', $id))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Sale $sale) => SaleController::present($sale));

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'members' => User::where('role', Role::Marketing)->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function reverse(Request $request, Sale $sale, StockLedger $ledger): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $ledger->reverseSale($sale, $data['reason'], $request->user());

        return back()->with('success', "Sale #{$sale->id} reversed; stock returned to {$sale->member->name}.");
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'member' => $sale->member->name,
            'product' => $sale->product->name,
            'unit' => $sale->product->unit,
            'quantity' => $sale->quantity,
            'unit_price_pesewas' => $sale->unit_price_pesewas,
            'total_pesewas' => $sale->total_pesewas,
            'customer_note' => $sale->customer_note,
            'created_at' => $sale->created_at->toIso8601String(),
            'reversed' => $sale->reversal !== null,
            'reversal_reason' => $sale->reversal?->reason,
            'reversed_by' => $sale->reversal?->creator?->name,
        ];
    }
}
