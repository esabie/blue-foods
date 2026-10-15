<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashHandInCancellation extends Model
{
    use AppendOnly;

    public const UPDATED_AT = null;

    protected $fillable = ['hand_in_id', 'reason', 'cancelled_by'];

    public function handIn(): BelongsTo
    {
        return $this->belongsTo(CashHandIn::class, 'hand_in_id');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
