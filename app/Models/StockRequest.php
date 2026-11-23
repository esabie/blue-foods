<?php

namespace App\Models;

use App\Enums\StockRequestStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockRequest extends Model
{
    use Auditable;

    public $timestamps = false;

    protected $fillable = [
        'member_user_id', 'product_id', 'quantity', 'member_note', 'status', 'requested_at',
        'confirmed_by', 'confirmed_at', 'decision_note', 'movement_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => StockRequestStatus::class,
            'quantity' => 'integer',
            'requested_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_user_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'movement_id');
    }
}
