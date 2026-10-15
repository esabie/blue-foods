<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineOrder extends Model
{
    use Auditable;

    protected $fillable = [
        'reference', 'access_token', 'customer_name', 'phone', 'address', 'delivery_method',
        'payment_method', 'status', 'subtotal_pesewas', 'delivery_fee_pesewas', 'total_pesewas',
        'reserved_until', 'paid_at', 'needs_refund',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'delivery_method' => DeliveryMethod::class,
            'payment_method' => PaymentMethod::class,
            'subtotal_pesewas' => 'integer',
            'delivery_fee_pesewas' => 'integer',
            'total_pesewas' => 'integer',
            'reserved_until' => 'datetime',
            'paid_at' => 'datetime',
            'needs_refund' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id')->latest('id');
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(OrderStatusChange::class, 'order_id')->orderBy('id');
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    /**
     * W6/W9: stock is held while a customer pays online (15 minutes) or until a
     * pay-on-delivery order is completed or cancelled.
     */
    public function scopeHoldingStock(Builder $query): void
    {
        $query->where('status', '!=', OrderStatus::Cancelled)
            ->whereNull('paid_at')
            ->where(fn (Builder $q) => $q
                ->where('payment_method', PaymentMethod::PayOnDelivery)
                ->orWhere('reserved_until', '>', now()));
    }

    public function statusUrl(): string
    {
        return route('shop.orders.show', ['order' => $this->reference, 'token' => $this->access_token]);
    }
}
