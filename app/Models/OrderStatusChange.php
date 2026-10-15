<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusChange extends Model
{
    use AppendOnly;

    public const UPDATED_AT = null;

    protected $fillable = ['order_id', 'from_status', 'to_status', 'user_id', 'note'];

    protected function casts(): array
    {
        return [
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
