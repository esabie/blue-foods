<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'product_id', 'quantity', 'unit_price_pesewas', 'total_pesewas', 'movement_id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_pesewas' => 'integer',
            'total_pesewas' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OnlineOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
