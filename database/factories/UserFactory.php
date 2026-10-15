<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+23324'.fake()->unique()->numerify('#######'),
            'role' => Role::Management,
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function management(): static
    {
        return $this->state(['role' => Role::Management]);
    }

    public function warehouse(): static
    {
        return $this->state(['role' => Role::Warehouse]);
    }

    public function marketing(): static
    {
        return $this->state(['role' => Role::Marketing]);
    }

    public function disabled(): static
    {
        return $this->state(['status' => UserStatus::Disabled]);
    }
}
