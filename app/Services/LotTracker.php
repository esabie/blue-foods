<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Production lots created on produce(); warehouse outbound uses FIFO
 * (soonest expiry first, then oldest production).
 *
 * Lot codes always start with the production date (Ymd) so staff can read
 * age at a glance when working the queue.
 */
class LotTracker
{
    public function __construct(private readonly StaffNotifier $notifier) {}

    public function openForProduce(
        Product $product,
        StockMovement $movement,
        int $quantity,
        Carbon $producedOn,
        ?Carbon $expiresOn = null,
    ): ProductionLot {
        return ProductionLot::create([
            'product_id' => $product->id,
            'production_movement_id' => $movement->id,
            'lot_code' => $this->codeFor($product, $producedOn, $movement->id),
            'produced_on' => $producedOn->toDateString(),
            'expires_on' => $expiresOn?->toDateString(),
            'quantity' => $quantity,
            'remaining' => $quantity,
        ]);
    }

    /**
     * Date-first code, e.g. 20261018-TILAPIA-42 — sorts chronologically and
     * stays unique per product via the production movement id.
     */
    public function codeFor(Product $product, Carbon $producedOn, int $movementId): string
    {
        return sprintf('%s-%s-%d', $producedOn->format('Ymd'), $product->sku, $movementId);
    }

    /**
     * Consume up to $quantity from open lots at the warehouse (FIFO).
     * Legacy stock with no lots is allowed through without error.
     *
     * @return list<array{lot: ProductionLot, quantity: int}>
     */
    public function consume(Product $product, int $quantity): array
    {
        if ($quantity <= 0) {
            return [];
        }

        $lots = $this->openLots($product->id)->lockForUpdate()->get();
        $left = $quantity;
        $taken = [];

        foreach ($lots as $lot) {
            if ($left === 0) {
                break;
            }

            $use = min($left, $lot->remaining);
            if ($use <= 0) {
                continue;
            }

            $lot->decrement('remaining', $use);
            $taken[] = ['lot' => $lot->fresh(), 'quantity' => $use];
            $left -= $use;
        }

        return $taken;
    }

    /**
     * Return stock to lots (e.g. confirmed member return): fill oldest open lots first, then the newest lot.
     */
    public function restock(Product $product, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $lots = $this->openLots($product->id, onlyWithRoom: true)->lockForUpdate()->get();
        $left = $quantity;

        foreach ($lots as $lot) {
            if ($left === 0) {
                break;
            }

            $room = $lot->quantity - $lot->remaining;
            if ($room <= 0) {
                continue;
            }

            $add = min($left, $room);
            $lot->increment('remaining', $add);
            $left -= $add;
        }

        if ($left > 0) {
            $latest = ProductionLot::where('product_id', $product->id)->orderByDesc('id')->lockForUpdate()->first();
            if ($latest) {
                $latest->increment('remaining', $left);
                $latest->increment('quantity', $left);
            }
        }
    }

    /**
     * @return Collection<int, ProductionLot>
     */
    public function remainingByProduct(?int $productId = null): Collection
    {
        return ProductionLot::query()
            ->with('product:id,sku,name,unit')
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->where('remaining', '>', 0)
            ->orderByRaw('expires_on is null')
            ->orderBy('expires_on')
            ->orderBy('produced_on')
            ->orderBy('id')
            ->get();
    }

    /**
     * One-shot staff alerts for open lots that just entered the warning window
     * or have expired with stock still on hand. Safe to run daily.
     *
     * @return array{expiring: int, expired: int}
     */
    public function alertExpiring(): array
    {
        $days = (int) config('inventory.lot_expiring_days');
        $horizon = now()->addDays($days)->toDateString();
        $today = now()->toDateString();

        $expiring = 0;
        $expired = 0;

        $soon = ProductionLot::query()
            ->open()
            ->withExpiry()
            ->with('product:id,name,unit')
            ->whereNull('expiring_alerted_at')
            ->whereDate('expires_on', '>', $today)
            ->whereDate('expires_on', '<=', $horizon)
            ->orderBy('expires_on')
            ->get();

        foreach ($soon as $lot) {
            $this->notifier->lotExpiringSoon($lot);
            $lot->update(['expiring_alerted_at' => now()]);
            $expiring++;
        }

        $past = ProductionLot::query()
            ->open()
            ->withExpiry()
            ->with('product:id,name,unit')
            ->whereNull('expired_alerted_at')
            ->whereDate('expires_on', '<=', $today)
            ->orderBy('expires_on')
            ->get();

        foreach ($past as $lot) {
            $this->notifier->lotExpired($lot);
            $lot->update(['expired_alerted_at' => now()]);
            $expired++;
        }

        return ['expiring' => $expiring, 'expired' => $expired];
    }

    private function openLots(int $productId, bool $onlyWithRoom = false)
    {
        return ProductionLot::query()
            ->where('product_id', $productId)
            ->when(
                $onlyWithRoom,
                fn ($q) => $q->whereColumn('remaining', '<', 'quantity'),
                fn ($q) => $q->where('remaining', '>', 0),
            )
            ->orderByRaw('expires_on is null')
            ->orderBy('expires_on')
            ->orderBy('produced_on')
            ->orderBy('id');
    }
}
