<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public const MAX_ATTEMPTS = 5;

    public const LOCK_MINUTES = 15;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * U2: sign in with email and password; five failed attempts lock the account for 15 minutes.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $user = User::where('email', mb_strtolower(trim($this->string('email'))))->first();

        if ($user?->isLocked()) {
            $minutes = (int) ceil(now()->diffInSeconds($user->locked_until) / 60);

            throw ValidationException::withMessages([
                'email' => "Too many failed attempts. This account is locked for {$minutes} more minute(s).",
            ]);
        }

        if ($user === null || ! Hash::check($this->string('password'), $user->password)) {
            if ($user !== null) {
                $this->recordFailedAttempt($user);
            }

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['email' => 'This account has been disabled.']);
        }

        $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null, 'last_login_at' => now()])->saveQuietly();

        Auth::login($user, $this->boolean('remember'));
    }

    private function recordFailedAttempt(User $user): void
    {
        $attempts = $user->failed_login_attempts + 1;

        $user->forceFill($attempts >= self::MAX_ATTEMPTS
            ? ['failed_login_attempts' => 0, 'locked_until' => now()->addMinutes(self::LOCK_MINUTES)]
            : ['failed_login_attempts' => $attempts]
        )->save();
    }
}
