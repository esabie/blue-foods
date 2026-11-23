<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionLot extends Model
{
    protected $fillable = [
        'product_id',
        'production_movement_id',
        'lot_code',
        'produced_on',
        'expires_on',
        'quantity',
        'remaining',
        'expiring_alerted_at',
        'expired_alerted_at',
    ];

    protected function casts(): array
    {
        return [
            'produced_on' => 'date',
            'expires_on' => 'date',
            'quantity' => 'integer',
            'remaining' => 'integer',
            'expiring_alerted_at' => 'datetime',
            'expired_alerted_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productionMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'production_movement_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isPast();
    }

    public function isExpiringSoon(?int $withinDays = null): bool
    {
        if ($this->expires_on === null || $this->isExpired()) {
            return false;
        }

        $days = $withinDays ?? (int) config('inventory.lot_expiring_days');

        return $this->expires_on->lte(now()->addDays($days)->startOfDay());
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('remaining', '>', 0);
    }

    public function scopeWithExpiry(Builder $query): void
    {
        $query->whereNotNull('expires_on');
    }
}
