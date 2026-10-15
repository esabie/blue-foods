<?php

namespace App\Http\Controllers;

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockLedger;
use App\Services\StockPosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * S3: per product, unassigned, with the marketing team, in the online shop and sold.
     */
    public function index(Request $request, StockPosition $position): Response
    {
        $products = Product::with('currentPrice')->orderBy('name')->get();
        $rows = $position->byProduct($products)->keyBy('product_id');

        return Inertia::render('Stock/Index', [
            'products' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'unit' => $product->unit,
                'status' => $product->status->value,
                'reorder_level' => $product->reorder_level,
                'price_pesewas' => $product->currentPrice?->unit_price_pesewas,
                ...$rows[$product->id],
            ]),
            'members' => User::where('role', Role::Marketing)
                ->where('status', UserStatus::Active)
                ->orderBy('name')
                ->get(['id', 'name']),
            'recentMovements' => $this->presentMovements(
                StockMovement::with(['product:id,name,unit', 'from.user:id,name', 'to.user:id,name', 'creator:id,name'])
                    ->latest('id')
                    ->limit(10)
                    ->get()
            ),
            'adjustmentReasons' => array_column(AdjustmentReason::cases(), 'value'),
            'can' => [
                'assign' => Gate::allows('assign-stock'),
                'allocateOnline' => $request->user()->hasRole(Role::Management),
            ],
        ]);
    }

    public function movements(Request $request): Response
    {
        $filters = $request->validate([
            'product_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(MovementType::class)],
        ]);

        $movements = StockMovement::with(['product:id,name,unit', 'from.user:id,name', 'to.user:id,name', 'creator:id,name'])
            ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->where('product_id', $id))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Stock/Movements', [
            'movements' => $movements->setCollection($this->presentMovements($movements->getCollection())),
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'types' => collect(MovementType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
            'filters' => $filters,
        ]);
    }

    public function produce(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'produced_on' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $this->ledger->produce($product, $data['quantity'], $request->user(), Carbon::parse($data['produced_on']));

        return back()->with('success', "Recorded {$data['quantity']} {$product->unit} of {$product->name} produced.");
    }

    public function adjust(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'note' => ['nullable', 'string', 'max:500', 'required_if:reason,other'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $this->ledger->adjust(
            $product,
            $data['quantity'],
            $data['direction'] === 'in',
            AdjustmentReason::from($data['reason']),
            $data['note'] ?? null,
            $request->user(),
        );

        return back()->with('success', "Adjusted {$product->name} {$data['direction']} by {$data['quantity']}.");
    }

    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'member_id' => ['required', 'integer', 'exists:users,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $member = User::findOrFail($data['member_id']);
        $this->ledger->assign($product, $member, $data['quantity'], $request->user());

        return back()->with('success', "Assigned {$data['quantity']} {$product->unit} of {$product->name} to {$member->name}.");
    }

    public function allocateOnline(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $this->ledger->allocateOnline($product, $data['quantity'], $request->user());

        return back()->with('success', "Allocated {$data['quantity']} {$product->unit} of {$product->name} to the online shop.");
    }

    /**
     * @param  Collection<int, StockMovement>  $movements
     */
    private function presentMovements($movements)
    {
        return $movements->map(fn (StockMovement $m) => [
            'id' => $m->id,
            'type' => $m->type->value,
            'type_label' => $m->type->label(),
            'product' => $m->product->name,
            'unit' => $m->product->unit,
            'quantity' => $m->quantity,
            'from' => $m->from?->label(),
            'to' => $m->to?->label(),
            'unit_price_pesewas' => $m->unit_price_pesewas,
            'reason' => $m->reason,
            'note' => $m->note,
            'user' => $m->creator->name,
            'occurred_at' => $m->occurred_at->toIso8601String(),
            'created_at' => $m->created_at->toIso8601String(),
        ]);
    }
}
