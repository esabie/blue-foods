<?php

namespace App\Models;

use App\Enums\MovementType;
use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use AppendOnly, Auditable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id', 'type', 'quantity', 'from_location_id', 'to_location_id',
        'unit_price_pesewas', 'reason', 'note', 'ref_movement_id', 'created_by', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'integer',
            'unit_price_pesewas' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'ref_movement_id');
    }
}
