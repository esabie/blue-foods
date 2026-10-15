<?php

namespace App\Models;

use App\Enums\LocationType;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'status',
        'password',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'failed_login_attempts',
        'locked_until',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'status' => UserStatus::class,
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Every marketing team member holds stock in their own location.
        static::saved(function (User $user) {
            if ($user->role === Role::Marketing && ! $user->location()->exists()) {
                $user->location()->create(['type' => LocationType::Member]);
            }
        });
    }

    public const SYSTEM_EMAIL = 'online-shop@system.bluefoods.internal';

    /**
     * The disabled account that records online-shop movements; it can never sign in.
     */
    public static function systemAccount(): self
    {
        return self::firstOrCreate(['email' => self::SYSTEM_EMAIL], [
            'name' => 'Online shop',
            'role' => Role::Management,
            'status' => UserStatus::Disabled,
            'password' => Str::random(64),
        ]);
    }

    public function scopeHuman(Builder $query): void
    {
        $query->where(fn ($q) => $q->whereNull('email')->orWhere('email', '!=', self::SYSTEM_EMAIL));
    }

    public function location(): HasOne
    {
        return $this->hasOne(Location::class);
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
