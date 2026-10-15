<?php

namespace App\Models;

use App\Enums\HandInMethod;
use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Money a marketing team member handed in for their sales (cash or mobile money).
 */
class CashHandIn extends Model
{
    use AppendOnly, Auditable;

    public const UPDATED_AT = null;

    protected $fillable = ['member_user_id', 'amount_pesewas', 'method', 'reference', 'note', 'received_by'];

    protected function casts(): array
    {
        return [
            'amount_pesewas' => 'integer',
            'method' => HandInMethod::class,
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_user_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function cancellation(): HasOne
    {
        return $this->hasOne(CashHandInCancellation::class, 'hand_in_id');
    }

    public function scopeNotCancelled(Builder $query): void
    {
        $query->whereDoesntHave('cancellation');
    }
}
