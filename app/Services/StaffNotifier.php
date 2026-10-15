<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\ReturnStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\CashHandIn;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\StaffAlert;
use App\Support\Money;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Decides who hears about what under the bell. Sent inside the caller's
 * transaction, so a rolled-back operation leaves no notification behind.
 */
class StaffNotifier
{
    private bool $muted = false;

    /**
     * Run without sending anything, e.g. while seeding months of demo history.
     */
    public function quietly(Closure $callback): mixed
    {
        $previous = $this->muted;
        $this->muted = true;

        try {
            return $callback();
        } finally {
            $this->muted = $previous;
        }
    }

    public function orderToPack(OnlineOrder $order): void
    {
        $payment = $order->payment_method === PaymentMethod::PayOnDelivery ? 'pay on delivery' : 'paid';

        $this->send(
            $this->staff(Role::Management, Role::Warehouse),
            'New online order to pack',
            "{$order->reference} · {$order->customer_name} · ".Money::format($order->total_pesewas)." ({$payment})",
            route('orders.index', ['tab' => 'to_pack'], absolute: false),
        );
    }

    public function refundNeeded(OnlineOrder $order): void
    {
        $this->send(
            $this->staff(Role::Management),
            'Refund needed',
            "Order {$order->reference} was paid after its stock was released. Refund ".Money::format($order->total_pesewas)." to {$order->customer_name}.",
            route('orders.index', ['tab' => 'cancelled'], absolute: false),
        );
    }

    public function returnRequested(ReturnRequest $return): void
    {
        $this->send(
            $this->staff(Role::Management, Role::Warehouse),
            'Return to confirm',
            "{$return->member->name} is handing back {$return->quantity} {$return->product->unit} of {$return->product->name}.",
            route('returns.index', absolute: false),
        );
    }

    public function returnDecided(ReturnRequest $return): void
    {
        $confirmed = $return->status === ReturnStatus::Confirmed;
        $body = ($confirmed ? 'The warehouse received' : 'The warehouse did not accept')
            ." your return of {$return->quantity} × {$return->product->name}."
            .(! $confirmed && filled($return->decision_note) ? " Note: {$return->decision_note}" : '');

        $this->send(
            collect([$return->member]),
            $confirmed ? 'Return received' : 'Return not accepted',
            $body,
            route('member.home', absolute: false),
        );
    }

    public function stockAssigned(StockMovement $movement, User $member): void
    {
        $this->send(
            collect([$member]),
            'Stock assigned to you',
            "{$movement->quantity} {$movement->product->unit} of {$movement->product->name} at ".Money::format($movement->unit_price_pesewas).' each.',
            route('member.home', absolute: false),
        );
    }

    public function saleReversed(Sale $sale, string $reason): void
    {
        $this->send(
            collect([$sale->member]),
            'Sale reversed',
            "Your sale of {$sale->quantity} × {$sale->product->name} was reversed: {$reason}",
            route('member.sales', absolute: false),
        );
    }

    public function handInRecorded(CashHandIn $handIn, int $stillOwed): void
    {
        $how = $handIn->method->label().($handIn->reference ? ", ref {$handIn->reference}" : '');

        $this->send(
            collect([$handIn->member]),
            'Cash hand-in recorded',
            "{$handIn->receiver->name} received ".Money::format($handIn->amount_pesewas)." from you ({$how}). Still to hand in: ".Money::format(max(0, $stillOwed)).'.',
            route('member.home', absolute: false),
        );
    }

    public function handInCancelled(CashHandIn $handIn, string $reason): void
    {
        $this->send(
            collect([$handIn->member]),
            'Cash hand-in cancelled',
            'Your hand-in of '.Money::format($handIn->amount_pesewas).' on '
                .$handIn->created_at->setTimezone(config('inventory.display_timezone'))->format('j M')
                ." was cancelled: {$reason}",
            route('member.home', absolute: false),
        );
    }

    /**
     * Only when the unassigned balance first drops below the reorder level, not on every later movement.
     */
    public function lowStockIfCrossed(Product $product, int $before, int $after): void
    {
        $level = $product->reorder_level;

        if ($level < 1 || $before < $level || $after >= $level) {
            return;
        }

        $this->send(
            $this->staff(Role::Management, Role::Warehouse),
            'Low stock',
            "{$product->name}: {$after} {$product->unit} unassigned, below the reorder level of {$level}.",
            route('stock.index', absolute: false),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function staff(Role ...$roles)
    {
        return User::human()
            ->whereIn('role', $roles)
            ->where('status', UserStatus::Active)
            ->get();
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function send($users, string $title, string $body, string $url): void
    {
        if ($this->muted || $users->isEmpty()) {
            return;
        }

        Notification::send($users, new StaffAlert($title, $body, $url));
    }
}
