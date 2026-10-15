<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Arr;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLog::record($model, 'created', null, $model->auditable($model->getAttributes()));
        });

        static::updated(function ($model) {
            $after = $model->auditable($model->getChanges());

            if ($after === []) {
                return;
            }

            $before = Arr::only($model->getRawOriginal(), array_keys($after));

            AuditLog::record($model, 'updated', $before, $after);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function auditable(array $attributes): array
    {
        return Arr::except($attributes, [
            'password', 'remember_token', 'updated_at', 'failed_login_attempts',
        ]);
    }
}
