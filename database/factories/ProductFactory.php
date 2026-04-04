<?php

namespace Database\Factories;

use App\Enums\SizeType;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
	/**
	 * Define the model's default state.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array {
		return [
			'name' => $this->faker->word(),
			'size' => $this->faker->randomFloat(2, 0.1, 1000),
			'size_type' => $this->faker->randomElement(SizeType::cases()),
		];
	}
}
