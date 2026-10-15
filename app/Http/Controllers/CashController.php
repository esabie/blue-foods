<?php

namespace App\Http\Controllers;

use App\Enums\HandInMethod;
use App\Enums\Role;
use App\Models\CashHandIn;
use App\Models\User;
use App\Services\CashBook;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Management records the money team members hand in for their sales, and sees who still owes what.
 */
class CashController extends Controller
{
    public function __construct(private readonly CashBook $cash) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate(['member_id' => ['nullable', 'integer']]);
        $positions = $this->cash->positions();

        $tz = config('inventory.display_timezone');
        $received = fn (CarbonImmutable $from) => (int) CashHandIn::notCancelled()->where('created_at', '>=', $from->utc())->sum('amount_pesewas');

        return Inertia::render('Cash/Index', [
            'totals' => [
                'outstanding_pesewas' => $positions->sum(fn ($p) => max(0, $p['outstanding_pesewas'])),
                'owing' => $positions->where('outstanding_pesewas', '>', 0)->count(),
                'overdue' => $positions->where('overdue', true)->count(),
                'today_pesewas' => $received(CarbonImmutable::now($tz)->startOfDay()),
                'month_pesewas' => $received(CarbonImmutable::now($tz)->startOfMonth()),
            ],
            'dueDays' => (int) config('inventory.cash_due_days'),
            'positions' => $positions,
            'handIns' => CashHandIn::with(['member:id,name', 'receiver:id,name', 'cancellation.canceller:id,name'])
                ->when($filters['member_id'] ?? null, fn ($q, $id) => $q->where('member_user_id', $id))
                ->latest('id')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (CashHandIn $h) => self::present($h)),
            'members' => User::where('role', Role::Marketing)->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', Role::Marketing->value)],
            'amount' => ['required', 'string', 'regex:'.Money::PATTERN],
            'method' => ['required', Rule::enum(HandInMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.regex' => 'Enter an amount in cedis, e.g. 250 or 250.50.',
        ]);

        $member = User::findOrFail($data['member_id']);
        $handIn = $this->cash->record(
            $member,
            Money::toPesewas($data['amount']),
            HandInMethod::from($data['method']),
            $data['reference'] ?? null,
            $data['note'] ?? null,
            $request->user(),
        );

        return back()->with('success', 'Recorded '.Money::format($handIn->amount_pesewas)." from {$member->name}.");
    }

    public function cancel(Request $request, CashHandIn $handIn): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->cash->cancel($handIn, $data['reason'], $request->user());

        return back()->with('success', "Hand-in #{$handIn->id} cancelled; {$handIn->member->name} owes that amount again.");
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(CashHandIn $h): array
    {
        return [
            'id' => $h->id,
            'member' => $h->member->name,
            'amount_pesewas' => $h->amount_pesewas,
            'method' => $h->method->value,
            'method_label' => $h->method->label(),
            'reference' => $h->reference,
            'note' => $h->note,
            'received_by' => $h->receiver->name,
            'created_at' => $h->created_at->toIso8601String(),
            'cancelled' => $h->cancellation !== null,
            'cancel_reason' => $h->cancellation?->reason,
            'cancelled_by' => $h->cancellation?->canceller?->name,
        ];
    }
}
