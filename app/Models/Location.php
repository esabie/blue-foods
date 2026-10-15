<?php

namespace App\Models;

use App\Enums\LocationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    protected $fillable = ['type', 'user_id'];

    protected function casts(): array
    {
        return ['type' => LocationType::class];
    }

    public static function warehouse(): self
    {
        return self::where('type', LocationType::Warehouse)->firstOrFail();
    }

    public static function onlineShop(): self
    {
        return self::where('type', LocationType::OnlineShop)->firstOrFail();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return match ($this->type) {
            LocationType::Warehouse => 'Warehouse',
            LocationType::OnlineShop => 'Online shop',
            LocationType::Member => $this->user?->name ?? 'Team member',
        };
    }
}
