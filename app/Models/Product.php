<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use LogicException;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use Auditable, HasFactory;

    protected $fillable = ['sku', 'name', 'description', 'photo_path', 'category', 'unit', 'reorder_level', 'status'];

    protected $attributes = [
        'status' => 'active',
        'reorder_level' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'reorder_level' => 'integer',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class)->orderByDesc('effective_from')->orderByDesc('id');
    }

    /**
     * The newest price whose effective date has been reached.
     */
    public function currentPrice(): HasOne
    {
        return $this->hasOne(ProductPrice::class)->ofMany(
            ['effective_from' => 'max', 'id' => 'max'],
            fn (Builder $query) => $query->where('effective_from', '<=', now()),
        );
    }

    public function costs(): HasMany
    {
        return $this->hasMany(ProductCost::class)->orderByDesc('effective_from')->orderByDesc('id');
    }

    public function currentCost(): HasOne
    {
        return $this->hasOne(ProductCost::class)->ofMany(
            ['effective_from' => 'max', 'id' => 'max'],
            fn (Builder $query) => $query->where('effective_from', '<=', now()),
        );
    }

    public function currentPricePesewas(): int
    {
        $price = $this->currentPrice()->first()
            ?? throw new LogicException("Product {$this->sku} has no price set.");

        return $price->unit_price_pesewas;
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function isActive(): bool
    {
        return $this->status === ProductStatus::Active;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', ProductStatus::Active);
    }

    /**
     * Products the website can sell: active and with a price in effect.
     */
    public function scopeSellable(Builder $query): void
    {
        $query->active()->whereHas('currentPrice')->with('currentPrice');
    }
}
