<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Rule B5: records are never edited or deleted; corrections are new entries.
 * The database triggers enforce the same rule for writes that bypass Eloquent.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(function ($model) {
            throw new LogicException(class_basename($model).' records are append-only and cannot be edited.');
        });

        static::deleting(function ($model) {
            throw new LogicException(class_basename($model).' records are append-only and cannot be deleted.');
        });
    }
}
