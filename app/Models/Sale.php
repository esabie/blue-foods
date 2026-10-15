<?php

namespace App\Models;

use App\Enums\MovementType;
use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    use AppendOnly, Auditable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'movement_id', 'member_user_id', 'product_id', 'quantity',
        'unit_price_pesewas', 'total_pesewas', 'customer_note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_pesewas' => 'integer',
            'total_pesewas' => 'integer',
        ];
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'movement_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_user_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The SALE_REVERSED movement that cancels this sale, if any.
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(StockMovement::class, 'ref_movement_id', 'movement_id')
            ->where('type', MovementType::SaleReversed);
    }

    public function scopeNotReversed(Builder $query): void
    {
        $query->whereDoesntHave('reversal');
    }
}
