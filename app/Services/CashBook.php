<?php

namespace App\Services;

use App\Enums\HandInMethod;
use App\Enums\Role;
use App\Exceptions\CashRuleException;
use App\Models\AuditLog;
use App\Models\CashHandIn;
use App\Models\CashHandInCancellation;
use App\Models\Location;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The money side of team sales. Every sale a member records is money they owe;
 * every hand-in management receives reduces it. Reversed sales and cancelled
 * hand-ins drop out, so the balance is always derived, never stored.
 *
 * Locking: recording or cancelling a hand-in locks the member's stock location,
 * the same row the ledger locks for sales and reversals, so the balance read
 * here cannot change underneath the check.
 */
class CashBook
{
    public function __construct(private readonly StaffNotifier $notifier) {}

    public function outstanding(User $member): int
    {
        return $this->sold($member->id) - $this->handedIn($member->id);
    }

    /**
     * When the oldest sale not yet covered by hand-ins was made; hand-ins pay off the oldest sales first.
     */
    public function owingSince(int $memberId, int $handedIn): ?CarbonImmutable
    {
        $running = 0;

        foreach (Sale::notReversed()->where('member_user_id', $memberId)->orderBy('created_at')->orderBy('id')->select(['id', 'created_at', 'total_pesewas'])->cursor() as $sale) {
            $running += $sale->total_pesewas;

            if ($running > $handedIn) {
                return CarbonImmutable::instance($sale->created_at);
            }
        }

        return null;
    }

    /**
     * Each marketing team member's sales, hand-ins and what they still owe, largest debt first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function positions(): Collection
    {
        $sold = Sale::notReversed()->groupBy('member_user_id')
            ->selectRaw('member_user_id, SUM(total_pesewas) AS total')
            ->pluck('total', 'member_user_id');

        $handIns = CashHandIn::notCancelled()->groupBy('member_user_id')
            ->selectRaw('member_user_id, SUM(amount_pesewas) AS total, MAX(created_at) AS last_at')
            ->get()
            ->keyBy('member_user_id');

        $dueBefore = $this->dueBefore();

        return User::where('role', Role::Marketing)->orderBy('name')->get()
            ->map(function (User $member) use ($sold, $handIns, $dueBefore) {
                $soldTotal = (int) ($sold[$member->id] ?? 0);
                $handedIn = (int) ($handIns[$member->id]->total ?? 0);
                $since = $soldTotal > $handedIn ? $this->owingSince($member->id, $handedIn) : null;
                $lastAt = $handIns[$member->id]->last_at ?? null;

                return [
                    'user_id' => $member->id,
                    'name' => $member->name,
                    'active' => $member->isActive(),
                    'sold_pesewas' => $soldTotal,
                    'handed_in_pesewas' => $handedIn,
                    'outstanding_pesewas' => $soldTotal - $handedIn,
                    'owing_since' => $since?->toIso8601String(),
                    'overdue' => $since !== null && $since->lt($dueBefore),
                    'last_hand_in_at' => $lastAt ? CarbonImmutable::parse($lastAt, 'UTC')->toIso8601String() : null,
                ];
            })
            ->filter(fn ($p) => $p['active'] || $p['sold_pesewas'] || $p['handed_in_pesewas'])
            ->sortByDesc('outstanding_pesewas')
            ->values();
    }

    /**
     * Sales made before this moment should have been handed in by now.
     */
    public function dueBefore(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays((int) config('inventory.cash_due_days'));
    }

    public function record(User $member, int $amountPesewas, HandInMethod $method, ?string $reference, ?string $note, User $actor): CashHandIn
    {
        if ($member->role !== Role::Marketing) {
            throw CashRuleException::for('member_id', 'Only marketing team members hand in cash for sales.');
        }
        if ($amountPesewas < 1) {
            throw CashRuleException::for('amount', 'Enter an amount above zero.');
        }

        $reference = filled($reference) ? trim($reference) : null;
        if ($method === HandInMethod::MobileMoney && $reference === null) {
            throw CashRuleException::for('reference', 'Enter the mobile money transaction ID.');
        }

        return DB::transaction(function () use ($member, $amountPesewas, $method, $reference, $note, $actor) {
            $this->lock($member);

            $owed = $this->outstanding($member);
            if ($amountPesewas > $owed) {
                throw CashRuleException::for('amount', $owed > 0
                    ? "{$member->name} only owes ".Money::format($owed).'.'
                    : "{$member->name} has nothing to hand in.");
            }

            if ($method === HandInMethod::MobileMoney) {
                $duplicate = CashHandIn::notCancelled()->where('method', $method)->where('reference', $reference)->first();
                if ($duplicate) {
                    throw CashRuleException::for('reference', "This transaction ID is already recorded (hand-in #{$duplicate->id}).");
                }
            }

            $handIn = CashHandIn::create([
                'member_user_id' => $member->id,
                'amount_pesewas' => $amountPesewas,
                'method' => $method,
                'reference' => $reference,
                'note' => filled($note) ? trim($note) : null,
                'received_by' => $actor->id,
            ]);
            $this->notifier->handInRecorded($handIn, $owed - $amountPesewas);

            return $handIn;
        });
    }

    /**
     * A mistaken hand-in is cancelled by a new record; the original stays visible.
     */
    public function cancel(CashHandIn $handIn, string $reason, User $actor): CashHandInCancellation
    {
        if (blank($reason)) {
            throw CashRuleException::for('reason', 'A reason is required to cancel a hand-in.');
        }

        return DB::transaction(function () use ($handIn, $reason, $actor) {
            $this->lock($handIn->member);

            if ($handIn->cancellation()->exists()) {
                throw CashRuleException::for('reason', 'This hand-in has already been cancelled.');
            }

            $cancellation = CashHandInCancellation::create([
                'hand_in_id' => $handIn->id,
                'reason' => trim($reason),
                'cancelled_by' => $actor->id,
            ]);

            AuditLog::record($handIn, 'cancelled', ['hand_in_id' => $handIn->id], [
                'cancellation_id' => $cancellation->id,
                'reason' => $cancellation->reason,
            ]);
            $this->notifier->handInCancelled($handIn, $cancellation->reason);

            return $cancellation;
        });
    }

    private function sold(int $memberId): int
    {
        return (int) Sale::notReversed()->where('member_user_id', $memberId)->sum('total_pesewas');
    }

    private function handedIn(int $memberId): int
    {
        return (int) CashHandIn::notCancelled()->where('member_user_id', $memberId)->sum('amount_pesewas');
    }

    private function lock(User $member): void
    {
        Location::where('user_id', $member->id)->lockForUpdate()->firstOrFail();
    }
}
