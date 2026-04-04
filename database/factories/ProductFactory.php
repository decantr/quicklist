<?php

namespace Database\Factories;

use App\Enums\Category;
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
			'size' => $this->faker->numberBetween(1, 1000),
			'size_type' => $this->faker->randomElement(SizeType::cases()),
			'category' => $this->faker->randomElement(Category::cases()),
		];
	}
}
