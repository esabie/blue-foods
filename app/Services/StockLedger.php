<?php

namespace App\Services;

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Enums\ReturnStatus;
use App\Enums\Role;
use App\Exceptions\StockRuleException;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The only way stock moves. Every balance is derived from the movement ledger,
 * and every rule in PRD section 4 is checked here inside a database transaction.
 *
 * Locking: each operation that takes stock out of a location first locks that
 * location's row (SELECT ... FOR UPDATE) and only then reads balances. The lock
 * must be the first read in the transaction: on MySQL the consistent snapshot is
 * taken at the first plain read, so reading anything earlier would give a stale
 * balance and allow overselling (rule B7).
 */
class StockLedger
{
    public function __construct(private readonly StaffNotifier $notifier) {}

    public function produce(Product $product, int $quantity, User $actor, ?CarbonInterface $producedOn = null): StockMovement
    {
        $this->ensurePositive($quantity);
        $this->ensureActive($product);

        $occurredAt = ($producedOn === null || $producedOn->isToday()) ? now() : $producedOn->copy()->startOfDay();

        return $this->move(MovementType::Produced, $product, $quantity, $actor, [
            'to_location_id' => Location::warehouse()->id,
            'occurred_at' => $occurredAt,
        ]);
    }

    public function adjust(
        Product $product,
        int $quantity,
        bool $increase,
        AdjustmentReason $reason,
        ?string $note,
        User $actor,
    ): StockMovement {
        $this->ensurePositive($quantity);

        if ($reason === AdjustmentReason::Other && blank($note)) {
            throw StockRuleException::for('note', 'Explain the adjustment when the reason is "other".');
        }

        $warehouse = Location::warehouse();

        if ($increase) {
            return $this->move(MovementType::AdjustedIn, $product, $quantity, $actor, [
                'to_location_id' => $warehouse->id,
                'reason' => $reason->value,
                'note' => $note,
            ]);
        }

        return DB::transaction(function () use ($product, $quantity, $reason, $note, $actor, $warehouse) {
            $this->lock($warehouse->id);
            $this->ensureAvailable($product, $warehouse->id, $quantity, 'in the warehouse');

            $movement = $this->move(MovementType::AdjustedOut, $product, $quantity, $actor, [
                'from_location_id' => $warehouse->id,
                'reason' => $reason->value,
                'note' => $note,
            ]);
            $this->warnIfLow($product, $warehouse->id, $quantity);

            return $movement;
        });
    }

    /**
     * A1/A2: move unassigned stock to a team member at the price in force now.
     */
    public function assign(Product $product, User $member, int $quantity, User $actor): StockMovement
    {
        $this->ensurePositive($quantity);
        $this->ensureActive($product);
        $memberLocation = $this->memberLocation($member);
        $warehouse = Location::warehouse();

        return DB::transaction(function () use ($product, $member, $quantity, $actor, $warehouse, $memberLocation) {
            $this->lock($warehouse->id);
            $this->ensureAvailable($product, $warehouse->id, $quantity, 'unassigned');

            $movement = $this->move(MovementType::Assigned, $product, $quantity, $actor, [
                'from_location_id' => $warehouse->id,
                'to_location_id' => $memberLocation->id,
                'unit_price_pesewas' => $product->currentPricePesewas(),
            ]);
            $this->notifier->stockAssigned($movement, $member);
            $this->warnIfLow($product, $warehouse->id, $quantity);

            return $movement;
        });
    }

    /**
     * A5: the online shop is a stock location like a team member.
     */
    public function allocateOnline(Product $product, int $quantity, User $actor): StockMovement
    {
        $this->ensurePositive($quantity);
        $this->ensureActive($product);
        $warehouse = Location::warehouse();
        $online = Location::onlineShop();

        return DB::transaction(function () use ($product, $quantity, $actor, $warehouse, $online) {
            $this->lock($warehouse->id);
            $this->ensureAvailable($product, $warehouse->id, $quantity, 'unassigned');

            $movement = $this->move(MovementType::AllocatedOnline, $product, $quantity, $actor, [
                'from_location_id' => $warehouse->id,
                'to_location_id' => $online->id,
                'unit_price_pesewas' => $product->currentPricePesewas(),
            ]);
            $this->warnIfLow($product, $warehouse->id, $quantity);

            return $movement;
        });
    }

    /**
     * L1/L2: a team member sells from their own stock at the current fixed price.
     */
    public function recordSale(User $member, Product $product, int $quantity, ?string $customerNote = null): Sale
    {
        $this->ensurePositive($quantity);
        $location = $this->memberLocation($member);

        return DB::transaction(function () use ($member, $product, $quantity, $customerNote, $location) {
            $this->lock($location->id);

            $available = $this->balance($product->id, $location->id)
                - $this->pendingReturnQuantity($member->id, $product->id);

            if ($quantity > $available) {
                throw StockRuleException::for('quantity', "You only have {$available} available to sell.");
            }

            $unitPrice = $product->currentPricePesewas();

            $movement = $this->move(MovementType::Sold, $product, $quantity, $member, [
                'from_location_id' => $location->id,
                'unit_price_pesewas' => $unitPrice,
            ]);

            return Sale::create([
                'movement_id' => $movement->id,
                'member_user_id' => $member->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price_pesewas' => $unitPrice,
                'total_pesewas' => $quantity * $unitPrice,
                'customer_note' => $customerNote,
            ]);
        });
    }

    /**
     * L4: a reversal is a new ledger entry; the original sale is kept.
     */
    public function reverseSale(Sale $sale, string $reason, User $actor): StockMovement
    {
        if (blank($reason)) {
            throw StockRuleException::for('reason', 'A reason is required to reverse a sale.');
        }

        $location = $this->memberLocation($sale->member, mustBeActive: false);

        return DB::transaction(function () use ($sale, $reason, $actor, $location) {
            $this->lock($location->id);

            if ($sale->reversal()->exists()) {
                throw StockRuleException::for('reason', 'This sale has already been reversed.');
            }

            $movement = $this->move(MovementType::SaleReversed, $sale->product, $sale->quantity, $actor, [
                'to_location_id' => $location->id,
                'unit_price_pesewas' => $sale->unit_price_pesewas,
                'reason' => $reason,
                'ref_movement_id' => $sale->movement_id,
            ]);

            AuditLog::record($sale, 'reversed', ['sale_id' => $sale->id], [
                'reversal_movement_id' => $movement->id,
                'reason' => $reason,
            ]);
            $this->notifier->saleReversed($sale, $reason);

            return $movement;
        });
    }

    /**
     * A3: a member asks to hand back unsold stock. Nothing moves until the warehouse confirms.
     */
    public function requestReturn(User $member, Product $product, int $quantity): ReturnRequest
    {
        $this->ensurePositive($quantity);
        $location = $this->memberLocation($member);

        return DB::transaction(function () use ($member, $product, $quantity, $location) {
            $this->lock($location->id);

            $available = $this->balance($product->id, $location->id)
                - $this->pendingReturnQuantity($member->id, $product->id);

            if ($quantity > $available) {
                throw StockRuleException::for('quantity', "You can return at most {$available}.");
            }

            $return = ReturnRequest::create([
                'member_user_id' => $member->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'status' => ReturnStatus::Requested,
                'requested_at' => now(),
            ]);
            $this->notifier->returnRequested($return);

            return $return;
        });
    }

    /**
     * A4: stock moves back to unassigned only when the warehouse confirms receipt.
     */
    public function confirmReturn(ReturnRequest $request, User $actor): StockMovement
    {
        $location = $this->memberLocation($request->member, mustBeActive: false);
        $warehouse = Location::warehouse();

        return DB::transaction(function () use ($request, $actor, $location, $warehouse) {
            $request = ReturnRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->lock($location->id);

            if ($request->status !== ReturnStatus::Requested) {
                throw StockRuleException::for('return', 'This return has already been '.$request->status->value.'.');
            }

            $this->ensureAvailable($request->product, $location->id, $request->quantity, 'with this team member');

            $movement = $this->move(MovementType::Returned, $request->product, $request->quantity, $actor, [
                'from_location_id' => $location->id,
                'to_location_id' => $warehouse->id,
            ]);

            $request->update([
                'status' => ReturnStatus::Confirmed,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'movement_id' => $movement->id,
            ]);
            $this->notifier->returnDecided($request);

            return $movement;
        });
    }

    public function rejectReturn(ReturnRequest $request, User $actor, ?string $note = null): ReturnRequest
    {
        return DB::transaction(function () use ($request, $actor, $note) {
            $request = ReturnRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== ReturnStatus::Requested) {
                throw StockRuleException::for('return', 'This return has already been '.$request->status->value.'.');
            }

            $request->update([
                'status' => ReturnStatus::Rejected,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'decision_note' => $note,
            ]);
            $this->notifier->returnDecided($request);

            return $request;
        });
    }

    public function balance(int $productId, int $locationId): int
    {
        $in = StockMovement::where('product_id', $productId)->where('to_location_id', $locationId)->sum('quantity');
        $out = StockMovement::where('product_id', $productId)->where('from_location_id', $locationId)->sum('quantity');

        return (int) $in - (int) $out;
    }

    public function pendingReturnQuantity(int $memberId, int $productId): int
    {
        return (int) ReturnRequest::where('member_user_id', $memberId)
            ->where('product_id', $productId)
            ->where('status', ReturnStatus::Requested)
            ->sum('quantity');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function move(MovementType $type, Product $product, int $quantity, User $actor, array $attributes): StockMovement
    {
        return StockMovement::create([
            'type' => $type,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'created_by' => $actor->id,
            'occurred_at' => now(),
            ...$attributes,
        ]);
    }

    private function warnIfLow(Product $product, int $warehouseId, int $quantityTaken): void
    {
        $after = $this->balance($product->id, $warehouseId);
        $this->notifier->lowStockIfCrossed($product, $after + $quantityTaken, $after);
    }

    private function lock(int $locationId): void
    {
        Location::whereKey($locationId)->lockForUpdate()->firstOrFail();
    }

    private function ensureAvailable(Product $product, int $locationId, int $quantity, string $where): void
    {
        $balance = $this->balance($product->id, $locationId);

        if ($quantity > $balance) {
            throw StockRuleException::for('quantity', "Only {$balance} {$product->unit} {$where}.");
        }
    }

    private function ensurePositive(int $quantity): void
    {
        if ($quantity < 1) {
            throw StockRuleException::for('quantity', 'Quantity must be at least 1.');
        }
    }

    private function ensureActive(Product $product): void
    {
        if (! $product->isActive()) {
            throw StockRuleException::for('product_id', 'This product has been retired.');
        }
    }

    /**
     * Stock already held by a disabled member can still be returned or reversed back to them.
     */
    private function memberLocation(User $member, bool $mustBeActive = true): Location
    {
        if ($member->role !== Role::Marketing || ($mustBeActive && ! $member->isActive())) {
            throw StockRuleException::for('member_id', 'Stock can only be held by an active marketing team member.');
        }

        return $member->location()->firstOrFail();
    }
}
