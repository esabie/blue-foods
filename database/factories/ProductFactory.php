<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->unique()->bothify('BF-###??')),
            'name' => fake()->unique()->words(2, true),
            'category' => fake()->randomElement(['Fish', 'Seafood', 'Smoked fish']),
            'unit' => 'pack',
            'reorder_level' => 0,
            'status' => ProductStatus::Active,
        ];
    }

    /**
     * Give the product a current price, in pesewas.
     */
    public function priced(int $pesewas = 2500): static
    {
        return $this->afterCreating(fn (Product $product) => $product->prices()->create([
            'unit_price_pesewas' => $pesewas,
            'effective_from' => now()->subMinute(),
        ]));
    }

    public function retired(): static
    {
        return $this->state(['status' => ProductStatus::Retired]);
    }
}
