<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    use AppendOnly, Auditable;

    public const UPDATED_AT = null;

    protected $fillable = ['product_id', 'unit_price_pesewas', 'effective_from', 'set_by'];

    protected function casts(): array
    {
        return [
            'unit_price_pesewas' => 'integer',
            'effective_from' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function setter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
